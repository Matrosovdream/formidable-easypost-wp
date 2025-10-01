<?php
if ( ! defined('ABSPATH') ) { exit; }

final class FrmEasypostAdminSettings extends FrmEasypostAdminAbstract {

    public function __construct() {
        parent::__construct();

        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_init', [$this, 'register_settings']);

        // Plugin “Settings” quick link
        add_filter('plugin_action_links_' . plugin_basename( dirname(__DIR__) . '/'. basename(dirname(__DIR__)).'.php' ), [$this, 'plugin_quick_link']);
        // If above path is brittle, register a dedicated filter from main file (see bootstrap section).
    }

    /** Create top-level + settings submenu */
    public function register_menus(): void {
        $cap = 'manage_options';

        add_menu_page(
            __('EasyPost','frm-easypost'),
            __('EasyPost','frm-easypost'),
            $cap,
            self::MENU_SLUG_TOP,
            [$this,'render_settings_page'],
            'dashicons-admin-site',
            56
        );

        add_submenu_page(
            self::MENU_SLUG_TOP,
            __('Settings','frm-easypost'),
            __('Settings','frm-easypost'),
            $cap,
            self::MENU_SLUG_SETTINGS,
            [$this,'render_settings_page']
        );
    }

    /** Settings API sections/fields */
    public function register_settings(): void {
        register_setting('frm_easypost', self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'show_in_rest'      => false,
            'default'           => $this->defaults(),
        ]);

        // --- Sections
        add_settings_section('frm_easypost_api', __('API Credentials','frm-easypost'), function(){
            echo '<p>'.esc_html__('Enter your EasyPost API key. You can lock this with a wp-config constant.','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('api_key', __('API Key','frm-easypost'), [$this,'field_password'], 'frm_easypost','frm_easypost_api', ['key'=>'api_key','placeholder'=>'API_KEY']);

        add_settings_section('frm_smarty_api', __('Smarty API Credentials','frm-easypost'), function(){
            echo '<p>'.esc_html__('Provide your Smarty credentials. You can lock fields with SMARTY_AUTH_ID and SMARTY_AUTH_TOKEN in wp-config.php.','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('smarty_auth_id', __('Smarty Auth ID','frm-easypost'), [$this,'field_password'], 'frm_easypost','frm_smarty_api', ['key'=>'smarty_auth_id','placeholder'=>'SMARTY_AUTH_ID']);
        add_settings_field('smarty_auth_token', __('Smarty Auth Token','frm-easypost'), [$this,'field_password'], 'frm_easypost','frm_smarty_api', ['key'=>'smarty_auth_token','placeholder'=>'SMARTY_AUTH_TOKEN']);

        add_settings_section('frm_easypost_carriers', __('Carrier Accounts','frm-easypost'), function(){
            echo '<p>'.esc_html__('Add your connected carrier accounts. Use comma-separated package names (e.g., "FlatRateEnvelope, FedExEnvelope").','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('carrier_accounts', __('Accounts','frm-easypost'), [$this,'field_carrier_accounts'], 'frm_easypost','frm_easypost_carriers');

        add_settings_section('frm_easypost_allowed', __('Allowed carriers','frm-easypost'), function(){
            echo '<p>'.esc_html__('Limit which carriers (and optionally services) are allowed at checkout/label time. Leave “Services” empty to allow all services for that carrier.','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('allowed_carriers', __('Rules','frm-easypost'), [$this,'field_allowed_carriers'], 'frm_easypost','frm_easypost_allowed');

        add_settings_section('frm_easypost_labels', __('Label Messages','frm-easypost'), function(){
            echo '<p>'.esc_html__('Optional messages to be printed on your shipping labels (if supported by the carrier).','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('label_message1', __('Label Message 1','frm-easypost'), [$this,'field_text'], 'frm_easypost','frm_easypost_labels', ['key'=>'label_message1','placeholder'=>'e.g. Handle with care']);
        add_settings_field('label_message2', __('Label Message 2','frm-easypost'), [$this,'field_text'], 'frm_easypost','frm_easypost_labels', ['key'=>'label_message2','placeholder'=>'e.g. Fragile']);

        add_settings_section('frm_easypost_usps', __('USPS settings','frm-easypost'), function(){
            echo '<p>'.esc_html__('Configure USPS-specific options.','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('usps_timezone', __('Timezone (UTC offset, integer)','frm-easypost'), [$this,'field_number'], 'frm_easypost','frm_easypost_usps', [
            'key'=>'usps_timezone','placeholder'=>'e.g. -8 for America/Los_Angeles','min'=>-12,'max'=>14,'step'=>1,
            'help'=>__('Integer hours offset from UTC. Example: Pacific (Los Angeles) is -8 (standard time).','frm-easypost'),
        ]);

        add_settings_section('frm_easypost_ship_mgmt', __('Shipment management','frm-easypost'), function(){
            echo '<p>'.esc_html__('Configure auto-void behavior for shipments.','frm-easypost').'</p>';
        }, 'frm_easypost');

        add_settings_field('void_statuses', __('Void Statuses','frm-easypost'), [$this,'field_multiselect_statuses'], 'frm_easypost','frm_easypost_ship_mgmt', [
            'key'=>'void_statuses','options'=>$this->get_status_options(),
            'help'=>__('Shipments in any of these statuses will be eligible for auto-void after the selected number of days.','frm-easypost'),
        ]);

        add_settings_field('void_after_days', __('Void after (days)','frm-easypost'), [$this,'field_number'], 'frm_easypost','frm_easypost_ship_mgmt', [
            'key'=>'void_after_days','placeholder'=>'e.g. 7','min'=>0,'max'=>365,'step'=>1,
            'help'=>__('Number of days after which an eligible shipment should be voided. Set 0 to disable.','frm-easypost'),
        ]);
    }

    /** Render the Settings page */
    public function render_settings_page(): void {
        if ( ! current_user_can('manage_options') ) { return; }
        $this->ensure_settings();
        ?>
        <div class="wrap frm-easypost-settings">
            <h1 class="frm-eap-h1">
                <span class="dashicons dashicons-admin-site frm-eap-h1-icon"></span>
                <?php esc_html_e('EasyPost — Settings','frm-easypost'); ?>
            </h1>

            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.','frm-easypost'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('frm_easypost'); ?>
                <div class="frm-eap-stack">
                    <div class="card frm-eap-card">
                        <?php do_settings_sections('frm_easypost'); ?>
                    </div>
                    <div class="card frm-eap-card">
                        <h2><?php esc_html_e('Save Changes','frm-easypost'); ?></h2>
                        <p class="frm-eap-muted">
                            <?php
                            echo wp_kses_post( sprintf(
                                __('Helpful links: <a href="%1$s" target="_blank" rel="noopener">EasyPost Settings</a> • <a href="%2$s" target="_blank" rel="noopener">Smarty Dashboard</a>','frm-easypost'),
                                esc_url('https://app.easypost.com/account/settings'),
                                esc_url('https://www.smarty.com/account')
                            ) );
                            ?>
                        </p>
                        <?php submit_button(__('Save Settings','frm-easypost')); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    // ---------------- Field renderers (copied from your original, minor class refactors)

    public function field_password(array $args = []): void {
        $key = $args['key'] ?? '';
        $val = $this->get_settings()[$key] ?? '';
        $ph  = $args['placeholder'] ?? '';
        $locked = $this->is_locked($key);
        $display = $val ? str_repeat('•', 12) : '';
        printf(
            '<input type="password" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" %5$s autocomplete="new-password" />',
            esc_attr(self::OPTION_NAME), esc_attr($key), esc_attr($locked ? $display : $val), esc_attr($ph), $locked ? 'readonly' : ''
        );
        $this->maybe_locked_note($key);
    }

    public function field_text(array $args = []): void {
        $key = $args['key'] ?? '';
        $val = $this->get_settings()[$key] ?? '';
        $ph  = $args['placeholder'] ?? '';
        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text frm-eap-full" placeholder="%4$s" />',
            esc_attr(self::OPTION_NAME), esc_attr($key), esc_attr($val), esc_attr($ph)
        );
    }

    public function field_number(array $args = []): void {
        $key  = $args['key'] ?? '';
        $ph   = $args['placeholder'] ?? '';
        $min  = array_key_exists('min',$args) ? (string)$args['min'] : '';
        $max  = array_key_exists('max',$args) ? (string)$args['max'] : '';
        $step = array_key_exists('step',$args) ? (string)$args['step'] : '1';
        $help = $args['help'] ?? '';
        $val = $this->get_settings()[$key] ?? '';
        $val = is_numeric($val) ? (string)(int)$val : '';
        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" %5$s %6$s %7$s />',
            esc_attr(self::OPTION_NAME), esc_attr($key), esc_attr($val), esc_attr($ph),
            $min!==''?'min="'.esc_attr($min).'"':'',
            $max!==''?'max="'.esc_attr($max).'"':'',
            $step!==''?'step="'.esc_attr($step).'"':''
        );
        if ($help) echo '<p class="description" style="margin:4px 0 0;">'.esc_html($help).'</p>';
    }

    public function field_carrier_accounts(): void {
        $opts = $this->get_settings();
        $rows = isset($opts['carrier_accounts']) && is_array($opts['carrier_accounts']) ? $opts['carrier_accounts'] : [];
        if (empty($rows)) { $rows = [['code'=>'','id'=>'','packages'=>'']]; }
        ?>
        <table class="frm-eap-table" id="frm-easypost-carriers">
            <thead>
            <tr>
                <th style="width:20%"><?php esc_html_e('Code','frm-easypost'); ?></th>
                <th style="width:40%"><?php esc_html_e('Account ID','frm-easypost'); ?></th>
                <th style="width:35%"><?php esc_html_e('Packages (comma-separated)','frm-easypost'); ?></th>
                <th style="width:5%"><?php esc_html_e('Actions','frm-easypost'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i=>$row): ?>
                <tr>
                    <td><input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[carrier_accounts][<?php echo (int)$i; ?>][code]" value="<?php echo esc_attr($row['code'] ?? ''); ?>" placeholder="usps" class="regular-text" /></td>
                    <td><input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[carrier_accounts][<?php echo (int)$i; ?>][id]" value="<?php echo esc_attr($row['id'] ?? ''); ?>" placeholder="ca_xxxxxxxxxxxxxxxxxxxxxxxx" class="regular-text" /></td>
                    <td><input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[carrier_accounts][<?php echo (int)$i; ?>][packages]" value="<?php echo esc_attr($row['packages'] ?? ''); ?>" placeholder="FlatRateEnvelope, FedExEnvelope" class="regular-text" /></td>
                    <td><button type="button" class="button frm-eap-del-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="frm-eap-actions"><button type="button" class="button" id="frm-easypost-add-row"><?php esc_html_e('Add row','frm-easypost'); ?></button></div>
        <?php
    }

    public function field_allowed_carriers(): void {
        $opts = $this->get_settings();
        $rows = isset($opts['allowed_carriers']) && is_array($opts['allowed_carriers']) ? $opts['allowed_carriers'] : [];
        if (empty($rows)) { $rows = [['carrier'=>'','services'=>'']]; }
        $opt = esc_attr(self::OPTION_NAME);
        ?>
        <table class="frm-eap-table" id="frm-easypost-allowed-carriers">
            <thead>
            <tr>
                <th style="width:35%"><?php esc_html_e('Carrier','frm-easypost'); ?></th>
                <th style="width:60%"><?php esc_html_e('Services (comma-separated; empty = all)','frm-easypost'); ?></th>
                <th style="width:5%"><?php esc_html_e('Actions','frm-easypost'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $i=>$row): ?>
                <tr>
                    <td><input type="text" class="regular-text" name="<?php echo $opt; ?>[allowed_carriers][<?php echo (int)$i; ?>][carrier]" value="<?php echo esc_attr($row['carrier'] ?? ''); ?>" placeholder="USPS, FedEx, FedExDefault" /></td>
                    <td><input type="text" class="regular-text" name="<?php echo $opt; ?>[allowed_carriers][<?php echo (int)$i; ?>][services]" value="<?php echo esc_attr($row['services'] ?? ''); ?>" placeholder="Express, Priority or Standard_overnight, Priority_overnight" /></td>
                    <td><button type="button" class="button frm-eap-del-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="frm-eap-actions"><button type="button" class="button" id="frm-easypost-add-allowed-row"><?php esc_html_e('Add rule','frm-easypost'); ?></button></div>
        <?php
    }

    public function field_multiselect_statuses(array $args = []): void {
        $key = $args['key'] ?? 'void_statuses';
        $options = $args['options'] ?? [];
        $help = $args['help'] ?? '';
        $saved = $this->get_settings()[$key] ?? [];
        if (!is_array($saved)) $saved = [];
        printf('<select name="%1$s[%2$s][]" multiple size="8" class="regular-text" style="min-width:280px;">', esc_attr(self::OPTION_NAME), esc_attr($key));
        foreach ($options as $val=>$label) {
            printf('<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($val), selected(in_array($val,$saved,true), true, false), esc_html($label)
            );
        }
        echo '</select>';
        if ($help) echo '<p class="description" style="margin:4px 0 0;">'.esc_html($help).'</p>';
    }

    /** Quick link in Plugins list */
    public function plugin_quick_link(array $links): array {
        $url = admin_url('admin.php?page='.self::MENU_SLUG_SETTINGS);
        $links[] = '<a href="'.esc_url($url).'">'.esc_html__('Settings','frm-easypost').'</a>';
        return $links;
    }
}
