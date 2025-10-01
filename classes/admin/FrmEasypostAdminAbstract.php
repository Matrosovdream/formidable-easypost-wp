<?php
if ( ! defined('ABSPATH') ) { exit; }

abstract class FrmEasypostAdminAbstract {

    protected const OPTION_NAME       = 'frm_easypost';
    protected const MENU_SLUG_TOP     = 'frm-easypost';
    protected const MENU_SLUG_SETTINGS= 'frm-easypost-settings';
    protected const MENU_SLUG_ADDRESSES= 'frm-easypost-service-addresses';

    /** Cached settings */
    protected array $settings = [];

    /** Ensure we bootstrap only once */
    private static bool $booted = false;

    public function __construct() {
        // Shared admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * BOOTSTRAP: hook once, then instantiate Settings + Addresses and add "Settings" quick link.
     * This replaces the bootstrap you had in the main plugin file.
     */
    public static function boot(): void {
        if ( self::$booted ) return;
        self::$booted = true;

        add_action('plugins_loaded', static function () {
            // Instantiate admin pages
            if ( class_exists('FrmEasypostAdminSettings') ) {
                new FrmEasypostAdminSettings();
            }
            if ( class_exists('FrmEasypostAdminAddresses') ) {
                new FrmEasypostAdminAddresses();
            }

            // Add Plugins screen quick link ("Settings")
            if ( defined('FRM_EAP_PLUGIN_FILE') ) {
                add_filter('plugin_action_links_' . plugin_basename(FRM_EAP_PLUGIN_FILE), static function(array $links): array {
                    $url = admin_url('admin.php?page=' . self::MENU_SLUG_SETTINGS);
                    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Settings','frm-easypost') . '</a>';
                    return $links;
                });
            }
        });
    }

    /* ----------------- rest of the class stays the same ----------------- */

    protected function ensure_settings(): void { if (!empty($this->settings)) return; $this->settings = $this->get_settings(); }

    protected function defaults(): array {
        return [
            'api_key'=>'','carrier_accounts'=>[],'smarty_auth_id'=>'','smarty_auth_token'=>'',
            'service_addresses'=>[],'label_message1'=>'','label_message2'=>'',
            'allowed_carriers'=>[],'usps_timezone'=>0,'void_statuses'=>[],'void_after_days'=>0,
        ];
    }

    protected function get_settings(): array {
        $opts = wp_parse_args( get_option(self::OPTION_NAME, []), $this->defaults() );
        if ( defined('EASYPOST_API_KEY') )   { $opts['api_key'] = (string)EASYPOST_API_KEY; }
        if ( defined('SMARTY_AUTH_ID') )     { $opts['smarty_auth_id'] = (string)SMARTY_AUTH_ID; }
        if ( defined('SMARTY_AUTH_TOKEN') )  { $opts['smarty_auth_token'] = (string)SMARTY_AUTH_TOKEN; }
        foreach (['carrier_accounts','service_addresses','allowed_carriers'] as $k) {
            if (!isset($opts[$k]) || !is_array($opts[$k])) { $opts[$k] = []; }
        }
        return $opts;
    }

    /** Single place to sanitize the full options array (except when admin-post service addresses path is used) */
    public function sanitize_settings(array $input): array {
        $output = $this->get_settings();

        // EasyPost api_key
        if ( isset($input['api_key']) && ! $this->is_locked('api_key') ) {
            $output['api_key'] = sanitize_text_field( wp_unslash($input['api_key']) );
        }

        // Smarty creds
        if ( isset($input['smarty_auth_id']) && ! $this->is_locked('smarty_auth_id') ) {
            $output['smarty_auth_id'] = sanitize_text_field( $input['smarty_auth_id'] );
        }
        if ( isset($input['smarty_auth_token']) && ! $this->is_locked('smarty_auth_token') ) {
            $output['smarty_auth_token'] = sanitize_text_field( $input['smarty_auth_token'] );
        }

        // Carrier accounts
        if ( isset($input['carrier_accounts']) && is_array($input['carrier_accounts']) ) {
            $rows = array_values(array_filter($input['carrier_accounts'], function($row){
                return is_array($row) && ( !empty($row['code']) || !empty($row['id']) || !empty($row['packages']) );
            }));
            $clean = [];
            foreach ($rows as $row) {
                $code = isset($row['code']) ? sanitize_key($row['code']) : '';
                $id   = isset($row['id']) ? sanitize_text_field($row['id']) : '';
                $pkgs = isset($row['packages']) ? (string)$row['packages'] : '';
                $pkgList  = array_filter(array_map('trim', explode(',', $pkgs)));
                $pkgsNorm = implode(', ', array_map('sanitize_text_field', $pkgList));
                if ($code !== '' || $id !== '' || $pkgsNorm !== '') {
                    $clean[] = ['code'=>$code,'id'=>$id,'packages'=>$pkgsNorm];
                }
            }
            $output['carrier_accounts'] = $clean;
        }

        // service_addresses
        if ( isset( $input['service_addresses'] ) && is_array( $input['service_addresses'] ) ) {
            $rows = array_values( array_filter( $input['service_addresses'], function( $row ) {
                return is_array( $row ) && ( ! empty( $row['name'] ) || ! empty( $row['street1'] ) );
            } ) );

            $clean = [];
            foreach ( $rows as $row ) {
                // normalize CSV: service_states
                $svcList  = array_filter( array_map( 'trim', explode( ',', (string)($row['service_states'] ?? '') ) ) );
                $svcNorm  = implode( ', ', array_map( 'sanitize_text_field', $svcList ) );

                // NEW: normalize CSV: tags
                $tagList  = array_filter( array_map( 'trim', explode( ',', (string)($row['tags'] ?? '') ) ) );
                $tagsNorm = implode( ', ', array_map( 'sanitize_text_field', $tagList ) );

                $clean[] = [
                    'name'           => sanitize_text_field( $row['name']    ?? '' ),
                    'company'        => sanitize_text_field( $row['company'] ?? '' ),
                    'phone'          => sanitize_text_field( $row['phone']   ?? '' ),
                    'proc_time'      => sanitize_text_field( $row['proc_time'] ?? '' ),
                    'street1'        => sanitize_text_field( $row['street1'] ?? '' ),
                    'street2'        => sanitize_text_field( $row['street2'] ?? '' ),
                    'city'           => sanitize_text_field( $row['city']    ?? '' ),
                    'state'          => sanitize_text_field( $row['state']   ?? '' ),
                    'zip'            => sanitize_text_field( $row['zip']     ?? '' ),
                    'country'        => strtoupper( sanitize_text_field( $row['country'] ?? 'US' ) ),
                    'service_states' => $svcNorm,
                    'tags'           => $tagsNorm, // NEW
                ];
            }
            $output['service_addresses'] = $clean;
        }

        // Allowed carriers
        if ( isset($input['allowed_carriers']) && is_array($input['allowed_carriers']) ) {
            $rows = array_values(array_filter($input['allowed_carriers'], function($row){
                return is_array($row) && !empty($row['carrier']);
            }));
            $clean = [];
            foreach ($rows as $row) {
                $carrier  = sanitize_text_field( trim((string)($row['carrier'] ?? '')) );
                $services = (string)($row['services'] ?? '');
                $svcList  = array_filter(array_map('trim', explode(',', $services)));
                $svcNorm  = implode(', ', array_map('sanitize_text_field', $svcList));
                if ($carrier !== '') {
                    $clean[] = ['carrier'=>$carrier, 'services'=>$svcNorm];
                }
            }
            $output['allowed_carriers'] = $clean;
        }

        // Labels
        if ( isset($input['label_message1']) ) { $output['label_message1'] = sanitize_text_field($input['label_message1']); }
        if ( isset($input['label_message2']) ) { $output['label_message2'] = sanitize_text_field($input['label_message2']); }

        // USPS timezone int
        if ( isset($input['usps_timezone']) ) {
            $tz = (int) $input['usps_timezone'];
            if ($tz < -12) $tz = -12; if ($tz > 14) $tz = 14;
            $output['usps_timezone'] = $tz;
        }

        // Shipment management
        if ( isset($input['void_statuses']) ) {
            $validKeys = array_keys( $this->get_status_options() );
            $vals = is_array($input['void_statuses']) ? $input['void_statuses'] : [];
            $vals = array_values( array_unique( array_filter( array_map('sanitize_key', $vals) ) ) );
            $output['void_statuses'] = array_values( array_intersect($vals, $validKeys) );
        }
        if ( isset($input['void_after_days']) ) {
            $days = (int) $input['void_after_days'];
            if ($days < 0) $days = 0; if ($days > 365) $days = 365;
            $output['void_after_days'] = $days;
        }

        return $output;
    }

    protected function is_locked(string $key): bool {
        if ($key==='api_key' && defined('EASYPOST_API_KEY')) return true;
        if ($key==='smarty_auth_id' && defined('SMARTY_AUTH_ID')) return true;
        if ($key==='smarty_auth_token' && defined('SMARTY_AUTH_TOKEN')) return true;
        return false;
    }

    protected function maybe_locked_note(string $key): void {
        /* … (unchanged) … */
    }

    protected function get_status_options(): array {
        try {
            if ( class_exists('FrmEasypostShipmentStatusModel') ) {
                $m = new FrmEasypostShipmentStatusModel();
                $list = $m->getList();
                if (is_array($list)) {
                    return array_map(static fn($v)=>(string)$v, $list);
                }
            }
        } catch (\Throwable $e) {}
        return [
            'unknown'              => __('Unknown','easypost-wp'),
            'pre_transit'          => __('Pre-Transit','easypost-wp'),
            'in_transit'           => __('In Transit','easypost-wp'),
            'out_for_delivery'     => __('Out for Delivery','easypost-wp'),
            'delivered'            => __('Delivered','easypost-wp'),
            'return_to_sender'     => __('Return to Sender','easypost-wp'),
            'available_for_pickup' => __('Available for Pickup','easypost-wp'),
            'failure'              => __('Failure','easypost-wp'),
            'cancelled'            => __('Cancelled','easypost-wp'),
        ];
    }

    public function enqueue_assets($hook): void {
        $screen = get_current_screen(); if (!$screen) return;
        $page_ok = in_array($screen->base, [
            'toplevel_page_'.self::MENU_SLUG_TOP,
            'easypost_page_'.self::MENU_SLUG_SETTINGS,
            'easypost_page_'.self::MENU_SLUG_ADDRESSES,
        ], true);
        if (!$page_ok) return;

        $base = plugin_dir_url(__FILE__);
        $base = preg_replace('#/includes/?$#','/',$base);

        
        wp_enqueue_style('frm-easypost-admin', FRM_EAP_BASE_PATH.'assets/css/easypost-admin.css?time='.time(), [], '1.0.0');
        wp_enqueue_script('frm-easypost-admin', FRM_EAP_BASE_PATH.'assets/js/easypost-admin.js?time='.time(), [], '1.0.0', true);

        wp_localize_script('frm-easypost-admin','FrmEAP',[
            'option'=>self::OPTION_NAME,
            'slugs'=>['top'=>self::MENU_SLUG_TOP,'settings'=>self::MENU_SLUG_SETTINGS,'addresses'=>self::MENU_SLUG_ADDRESSES],
            'i18n'=>['deleteRow'=>__('Delete row','frm-easypost'),'addRow'=>__('Add row','frm-easypost'),'addRule'=>__('Add rule','frm-easypost'),'addAddr'=>__('Add address','frm-easypost')],
        ]);
    }
}

FrmEasypostAdminAbstract::boot();