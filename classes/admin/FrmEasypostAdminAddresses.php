<?php
if ( ! defined('ABSPATH') ) { exit; }

final class FrmEasypostAdminAddresses extends FrmEasypostAdminAbstract {

    public function __construct() {
        parent::__construct();
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_frm_easypost_save_addresses', [$this,'handle_save_addresses']);
    }

    /** Just the "Service addresses" submenu under the same top menu */
    public function register_menu(): void {
        $cap = 'manage_options';
        add_submenu_page(
            self::MENU_SLUG_TOP,
            __('Service addresses','frm-easypost'),
            __('Service addresses','frm-easypost'),
            $cap,
            self::MENU_SLUG_ADDRESSES,
            [$this,'render_service_addresses_page']
        );
    }

    public function render_service_addresses_page(): void {
        if ( ! current_user_can('manage_options') ) { return; }
        $this->ensure_settings();
        ?>
        <div class="wrap frm-easypost-settings">
            <h1 class="frm-eap-h1">
                <span class="dashicons dashicons-admin-site frm-eap-h1-icon"></span>
                <?php esc_html_e('EasyPost — Service addresses','frm-easypost'); ?>
            </h1>

            <?php if ( isset($_GET['saved']) && $_GET['saved'] === '1' ): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Addresses saved.','frm-easypost'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="frm_easypost_save_addresses"/>
                <?php wp_nonce_field('frm_easypost_service_addresses'); ?>

                <div class="card frm-eap-card">
                    <?php $this->field_service_addresses(); ?>
                </div>
                <div class="card frm-eap-card">
                    <?php submit_button( __('Save Addresses','frm-easypost') ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /** admin-post handler (WAF-safe) */
    public function handle_save_addresses(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('Insufficient permissions.','frm-easypost') );
        }
        check_admin_referer('frm_easypost_service_addresses');

        $input = $_POST[self::OPTION_NAME] ?? [];
        if (!is_array($input)) $input = [];

        $sanitized = $this->get_settings();

        if ( isset( $input['service_addresses'] ) && is_array( $input['service_addresses'] ) ) {
            $rows = array_values( array_filter( $input['service_addresses'], function( $row ) {
                return is_array( $row ) && ( ! empty( $row['name'] ) || ! empty( $row['street1'] ) );
            } ) );
        
            $clean = [];
            foreach ( $rows as $row ) {
                $svcList  = array_filter( array_map( 'trim', explode( ',', (string)($row['service_states'] ?? '') ) ) );
                $svcNorm  = implode( ', ', array_map( 'sanitize_text_field', $svcList ) );
        
                // NEW: tags CSV
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
            $sanitized['service_addresses'] = $clean;
        } else {
            $sanitized['service_addresses'] = [];
        }
        

        update_option(self::OPTION_NAME, $sanitized, false);

        wp_safe_redirect(
            add_query_arg(
                ['page'=>self::MENU_SLUG_ADDRESSES,'saved'=>'1'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function field_service_addresses(): void {
        $opts = $this->get_settings();
        $rows = isset( $opts['service_addresses'] ) && is_array( $opts['service_addresses'] )
            ? $opts['service_addresses'] : [];
    
        if ( empty( $rows ) ) {
            $rows = [[
                'name' => '', 'company' => '', 'street1' => '', 'street2' => '',
                'city' => '', 'state' => '', 'zip' => '', 'country' => 'US',
                'service_states' => '', 'tags' => '',        // NEW default
                'phone' => '', 'proc_time' => '',
            ]];
        }
    
        $opt = esc_attr( self::OPTION_NAME );
        ?>
        <table class="frm-eap-table" id="frm-easypost-service-addresses">
            <thead>
                <tr>
                    <th style="width:22%"><?php esc_html_e('Name / Company / Phone / Processing time','frm-easypost'); ?></th>
                    <th style="width:25%"><?php esc_html_e('Street','frm-easypost'); ?></th>
                    <th style="width:20%"><?php esc_html_e('City / State','frm-easypost'); ?></th>
                    <th style="width:5%"><?php esc_html_e('ZIP','frm-easypost'); ?></th>
                    <th style="width:23%"><?php
                        // Expanded column title:
                        echo esc_html__('Country / Service States (comma-separated) / Tags (comma-separated)','frm-easypost');
                    ?></th>
                    <th style="width:5%"><?php esc_html_e('Actions','frm-easypost'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $i => $row ):
                $name    = (string)($row['name']           ?? '');
                $company = (string)($row['company']        ?? '');
                $phone   = (string)($row['phone']          ?? '');
                $proc    = (string)($row['proc_time']      ?? '');
                $street1 = (string)($row['street1']        ?? '');
                $street2 = (string)($row['street2']        ?? '');
                $city    = (string)($row['city']           ?? '');
                $state   = (string)($row['state']          ?? '');
                $zip     = (string)($row['zip']            ?? '');
                $country = (string)($row['country']        ?? 'US');
                $svc     = (string)($row['service_states'] ?? '');
                $tags    = (string)($row['tags']           ?? ''); // NEW
            ?>
                <tr>
                    <td>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="Name" />
                        <br/>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][company]" value="<?php echo esc_attr($company); ?>" placeholder="Company" />
                        <br/>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][phone]" value="<?php echo esc_attr($phone); ?>" placeholder="Phone" />
                        <br/>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][proc_time]" value="<?php echo esc_attr($proc); ?>" placeholder="Processing Time" />
                    </td>
                    <td>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][street1]" value="<?php echo esc_attr($street1); ?>" placeholder="Street 1" />
                        <br/>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][street2]" value="<?php echo esc_attr($street2); ?>" placeholder="Street 2 (optional)" />
                    </td>
                    <td>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][city]" value="<?php echo esc_attr($city); ?>" placeholder="City" />
                        <br/>
                        <input class="regular-text frm-eap-full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][state]" value="<?php echo esc_attr($state); ?>" placeholder="State" />
                    </td>
                    <td>
                        <input class="regular-text" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][zip]" value="<?php echo esc_attr($zip); ?>" placeholder="ZIP" />
                    </td>
                    <td>
                        <input class="regular-text" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][country]" value="<?php echo esc_attr($country); ?>" placeholder="US" />
                        <br/>
                        <textarea class="regular-text frm-eap-full" rows="2" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][service_states]" placeholder=""><?php echo esc_textarea($svc); ?></textarea>
                        <br/>
                        <!-- NEW: Tags textarea -->
                        <textarea class="regular-text frm-eap-full" rows="2" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][tags]" placeholder=""><?php echo esc_textarea($tags); ?></textarea>
                    </td>
                    <td><button type="button" class="button frm-eap-del-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    
        <div class="frm-eap-actions">
            <button type="button" class="button" id="frm-easypost-add-service-address"><?php esc_html_e('Add address','frm-easypost'); ?></button>
        </div>
        <?php
    }
    
    
}
