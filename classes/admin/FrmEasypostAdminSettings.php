<?php
/**
 * EasyPost Admin Settings (single-column)
 * + Smarty API Credentials block (Auth ID / Auth Token)
 * + NEW: "Service addresses" subpage with repeatable table
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FrmEasypostAdminSettings {
    private const OPTION_NAME = 'frm_easypost';

    /** @var array Cached settings */
    private array $settings = [];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // If this file is not the main plugin file, move this filter to the bootstrap file.
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_quick_link' ] );
    }

    /**
     * Top-level menu: EasyPost, subpages: Settings, Service addresses
     */
    public function register_menus(): void {
        $cap = 'manage_options';

        // Top-level page
        add_menu_page(
            __( 'EasyPost', 'frm-easypost' ),
            __( 'EasyPost', 'frm-easypost' ),
            $cap,
            'frm-easypost',
            [ $this, 'render_settings_page' ],
            'dashicons-admin-site',
            56
        );

        // Explicit Settings subpage
        add_submenu_page(
            'frm-easypost',
            __( 'Settings', 'frm-easypost' ),
            __( 'Settings', 'frm-easypost' ),
            $cap,
            'frm-easypost-settings',
            [ $this, 'render_settings_page' ]
        );

        // NEW: Service addresses subpage
        add_submenu_page(
            'frm-easypost',
            __( 'Service addresses', 'frm-easypost' ),
            __( 'Service addresses', 'frm-easypost' ),
            $cap,
            'frm-easypost-service-addresses',
            [ $this, 'render_service_addresses_page' ]
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting( 'frm_easypost', self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'show_in_rest'      => false,
            'default'           => $this->defaults(),
        ] );

        /**
         * PAGE: Settings (page slug used for sections below is 'frm_easypost')
         */
        // Section: EasyPost API
        add_settings_section(
            'frm_easypost_api',
            __( 'API Credentials', 'frm-easypost' ),
            function () {
                echo '<p>' . esc_html__( 'Enter your EasyPost API key. You can lock this with a wp-config constant.', 'frm-easypost' ) . '</p>';
            },
            'frm_easypost'
        );

        add_settings_field(
            'api_key',
            __( 'API Key', 'frm-easypost' ),
            [ $this, 'field_password' ],
            'frm_easypost',
            'frm_easypost_api',
            [ 'key' => 'api_key', 'placeholder' => 'API_KEY' ]
        );

        // Section: Smarty API
        add_settings_section(
            'frm_smarty_api',
            __( 'Smarty API Credentials', 'frm-easypost' ),
            function () {
                echo '<p>' . esc_html__( 'Provide your Smarty (SmartyStreets) credentials. You can lock fields with SMARTY_AUTH_ID and SMARTY_AUTH_TOKEN in wp-config.php.', 'frm-easypost' ) . '</p>';
            },
            'frm_easypost'
        );

        add_settings_field(
            'smarty_auth_id',
            __( 'Smarty Auth ID', 'frm-easypost' ),
            [ $this, 'field_password' ],
            'frm_easypost',
            'frm_smarty_api',
            [ 'key' => 'smarty_auth_id', 'placeholder' => 'SMARTY_AUTH_ID' ]
        );

        add_settings_field(
            'smarty_auth_token',
            __( 'Smarty Auth Token', 'frm-easypost' ),
            [ $this, 'field_password' ],
            'frm_easypost',
            'frm_smarty_api',
            [ 'key' => 'smarty_auth_token', 'placeholder' => 'SMARTY_AUTH_TOKEN' ]
        );

        // Section: Carrier Accounts
        add_settings_section(
            'frm_easypost_carriers',
            __( 'Carrier Accounts', 'frm-easypost' ),
            function () {
                echo '<p>' . esc_html__( 'Add your connected carrier accounts. Use comma-separated package names (e.g., "FlatRateEnvelope, FedExEnvelope").', 'frm-easypost' ) . '</p>';
            },
            'frm_easypost'
        );

        add_settings_field(
            'carrier_accounts',
            __( 'Accounts', 'frm-easypost' ),
            [ $this, 'field_carrier_accounts' ],
            'frm_easypost',
            'frm_easypost_carriers'
        );

        /**
         * PAGE: Service addresses (note the page slug for sections below is 'frm_easypost_service')
         * We still use the SAME registered setting group 'frm_easypost' to save the single option array.
         */
        add_settings_section(
            'frm_easypost_service_addresses',
            __( 'Service Addresses', 'frm-easypost' ),
            function () {
                echo '<p>' . esc_html__( 'Add any number of service addresses (e.g., passport centers, visa agencies).', 'frm-easypost' ) . '</p>';
            },
            'frm_easypost_service'
        );

        add_settings_field(
            'service_addresses',
            __( 'Addresses', 'frm-easypost' ),
            [ $this, 'field_service_addresses' ],
            'frm_easypost_service',
            'frm_easypost_service_addresses'
        );
    }

    /**
     * Default settings
     */
    private function defaults(): array {
        return [
            'api_key'           => '',
            'carrier_accounts'  => [],
            'smarty_auth_id'    => '',
            'smarty_auth_token' => '',
            'service_addresses' => [],
        ];
    }

    /**
     * Settings page UI (single column; Save under main card)
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->settings = $this->get_settings();
        ?>
        <div class="wrap frm-easypost-settings">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-admin-site" style="font-size:28px;line-height:1;"></span>
                <?php esc_html_e( 'EasyPost — Settings', 'frm-easypost' ); ?>
            </h1>

            <style>
                .frm-easypost-settings .card {background:#fff;border:1px solid #dcdcdc;border-radius:10px;padding:18px;margin:18px 0;max-width:100%}
                .frm-easypost-settings .muted {color:#666;margin-top:6px}
                .frm-easypost-settings code {font-size:12px;background:#f6f7f7;border:1px solid #e0e0e0;padding:2px 6px;border-radius:4px}
                .frm-easypost-settings .lock {display:inline-flex;gap:6px;align-items:center;color:#2d6cdf}
                .frm-easypost-stack {display:flex;flex-direction:column;gap:18px;}
                .frm-easypost-table {width:100%;border-collapse:collapse}
                .frm-easypost-table th, .frm-easypost-table td {border:1px solid #e0e0e0;padding:8px;background:#fff;vertical-align:top}
                .frm-easypost-table th {background:#f6f7f7;text-align:left}
                .frm-easypost-actions {margin-top:8px}
                .frm-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
                @media (max-width:782px){ .frm-grid-2 { grid-template-columns:1fr; } }
                .regular-text.full { width:100%; }
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'frm_easypost' ); ?>

                <div class="frm-easypost-stack">
                    <!-- Main settings card -->
                    <div class="card">
                        <?php do_settings_sections( 'frm_easypost' ); ?>
                    </div>

                    <!-- Save button card (stacked under the main card) -->
                    <div class="card">
                        <h2><?php esc_html_e( 'Save Changes', 'frm-easypost' ); ?></h2>
                        <p class="muted">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: 1: EasyPost link, 2: Smarty link */
                                    __( 'Helpful links: <a href="%1$s" target="_blank" rel="noopener">EasyPost Settings</a> • <a href="%2$s" target="_blank" rel="noopener">Smarty Dashboard</a>', 'frm-easypost' ),
                                    esc_url( 'https://app.easypost.com/account/settings' ),
                                    esc_url( 'https://www.smarty.com/account' )
                                )
                            );
                            ?>
                        </p>
                        <?php submit_button( __( 'Save Settings', 'frm-easypost' ) ); ?>
                    </div>
                </div>
            </form>
        </div>

        <script>
        (function(){
            const table = document.getElementById('frm-easypost-carriers');
            if (!table) return;
            const addBtn = document.getElementById('frm-easypost-add-row');

            function bindDeletes() {
                table.querySelectorAll('.link-delete-row').forEach(btn => {
                    btn.onclick = function(){
                        const tr = this.closest('tr');
                        if (tr && table.tBodies[0].rows.length > 1) tr.remove();
                    };
                });
            }

            if (addBtn) {
                addBtn.onclick = function(){
                    const tbody = table.tBodies[0];
                    const idx   = tbody.rows.length;
                    const opt   = '<?php echo esc_js( self::OPTION_NAME ); ?>';
                    const tmpl  = `
                    <tr>
                        <td><input type="text" name="${opt}[carrier_accounts][${idx}][code]" value="" placeholder="usps" class="regular-text" /></td>
                        <td><input type="text" name="${opt}[carrier_accounts][${idx}][id]" value="" placeholder="ca_xxxxxxxxxxxxxxxxxxxxxxxx" class="regular-text" /></td>
                        <td><input type="text" name="${opt}[carrier_accounts][${idx}][packages]" value="" placeholder="FlatRateEnvelope, FedExEnvelope" class="regular-text" /></td>
                        <td><button type="button" class="button link-delete-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                    </tr>`;
                    tbody.insertAdjacentHTML('beforeend', tmpl);
                    bindDeletes();
                };
            }

            bindDeletes();
        })();
        </script>
        <?php
    }

    /**
     * NEW: Service addresses page (separate subpage/slugs/sections)
     */
    public function render_service_addresses_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->settings = $this->get_settings();
        ?>
        <div class="wrap frm-easypost-settings">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-admin-site" style="font-size:28px;line-height:1;"></span>
                <?php esc_html_e( 'EasyPost — Service addresses', 'frm-easypost' ); ?>
            </h1>

            <style>
                .frm-easypost-settings .card {background:#fff;border:1px solid #dcdcdc;border-radius:10px;padding:18px;margin:18px 0;max-width:100%}
                .frm-easypost-table {width:100%;border-collapse:collapse}
                .frm-easypost-table th, .frm-easypost-table td {border:1px solid #e0e0e0;padding:8px;background:#fff;vertical-align:top}
                .frm-easypost-table th {background:#f6f7f7;text-align:left}
                .frm-easypost-actions {margin-top:8px}
                .frm-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
                @media (max-width:782px){ .frm-grid-2 { grid-template-columns:1fr; } }
                .regular-text.full { width:100%; }
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'frm_easypost' ); ?>

                <div class="card">
                    <?php do_settings_sections( 'frm_easypost_service' ); ?>
                </div>

                <div class="card">
                    <?php submit_button( __( 'Save Addresses', 'frm-easypost' ) ); ?>
                </div>
            </form>
        </div>

        <script>
        (function(){
            const table = document.getElementById('frm-easypost-service-addresses');
            const addBtn = document.getElementById('frm-easypost-add-service-address');
            if (!table || !addBtn) return;

            function bindDeletes(){
                table.querySelectorAll('.link-delete-row').forEach(btn => {
                    btn.onclick = function(){
                        const tr = this.closest('tr');
                        if (tr && table.tBodies[0].rows.length > 1) tr.remove();
                    };
                });
            }

            addBtn.onclick = function(){
                const tbody = table.tBodies[0];
                const idx   = tbody.rows.length;
                const opt   = '<?php echo esc_js( self::OPTION_NAME ); ?>';

                const tmpl = `
                <tr>
                    <td>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][name]" value="" placeholder="Name" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][company]" value="" placeholder="Company" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][phone]" value="" placeholder="Phone" />
                    </td>
                    <td>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][street1]" value="" placeholder="Street 1" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][street2]" value="" placeholder="Street 2 (optional)" />
                    </td>
                    <td>
                        <input class="regular-text1 full" type="text" name="${opt}[service_addresses][${idx}][city]" value="" placeholder="City" />
                        <br/>
                        <input class="regular-text1 1full" type="text" name="${opt}[service_addresses][${idx}][state]" value="" placeholder="State" />
                    </td>
                    <td><input class="regular-text1" type="text" name="${opt}[service_addresses][${idx}][zip]" value="" placeholder="ZIP" /></td>
                    <td><input class="regular-text1" type="text" name="${opt}[service_addresses][${idx}][country]" value="US" placeholder="US" /></td>
                    <td><button type="button" class="button link-delete-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                </tr>`;
                tbody.insertAdjacentHTML('beforeend', tmpl);
                bindDeletes();
            };

            bindDeletes();
        })();
        </script>
        <?php
    }

    /**
     * Retrieve current settings merged with defaults and wp-config constants
     */
    private function get_settings(): array {
        $opts = wp_parse_args( get_option( self::OPTION_NAME, [] ), $this->defaults() );

        // Apply wp-config constant if defined (lock field)
        if ( defined( 'EASYPOST_API_KEY' ) ) {
            $opts['api_key'] = (string) constant( 'EASYPOST_API_KEY' );
        }

        // Smarty constants (lock if present)
        if ( defined( 'SMARTY_AUTH_ID' ) ) {
            $opts['smarty_auth_id'] = (string) constant( 'SMARTY_AUTH_ID' );
        }
        if ( defined( 'SMARTY_AUTH_TOKEN' ) ) {
            $opts['smarty_auth_token'] = (string) constant( 'SMARTY_AUTH_TOKEN' );
        }

        // Ensure arrays exist
        if ( ! isset( $opts['carrier_accounts'] ) || ! is_array( $opts['carrier_accounts'] ) ) {
            $opts['carrier_accounts'] = [];
        }
        if ( ! isset( $opts['service_addresses'] ) || ! is_array( $opts['service_addresses'] ) ) {
            $opts['service_addresses'] = [];
        }

        return $opts;
    }

    /**
     * Sanitize callback (API key + carrier accounts + Smarty creds + service addresses)
     */
    public function sanitize_settings( array $input ): array {
        $output = $this->get_settings(); // start from existing + constants

        // EasyPost api_key
        if ( isset( $input['api_key'] ) && ! $this->is_locked( 'api_key' ) ) {
            $output['api_key'] = sanitize_text_field( wp_unslash( $input['api_key'] ) );
        }

        // Smarty creds
        if ( isset( $input['smarty_auth_id'] ) && ! $this->is_locked( 'smarty_auth_id' ) ) {
            $output['smarty_auth_id'] = sanitize_text_field( wp_unslash( $input['smarty_auth_id'] ) );
        }
        if ( isset( $input['smarty_auth_token'] ) && ! $this->is_locked( 'smarty_auth_token' ) ) {
            $output['smarty_auth_token'] = sanitize_text_field( wp_unslash( $input['smarty_auth_token'] ) );
        }

        // carrier_accounts
        if ( isset( $input['carrier_accounts'] ) && is_array( $input['carrier_accounts'] ) ) {
            $rows = array_values( array_filter( $input['carrier_accounts'], function( $row ) {
                return is_array( $row ) && ( ! empty( $row['code'] ) || ! empty( $row['id'] ) || ! empty( $row['packages'] ) );
            } ) );

            $clean = [];
            foreach ( $rows as $row ) {
                $code = isset( $row['code'] ) ? sanitize_key( $row['code'] ) : '';
                $id   = isset( $row['id'] ) ? sanitize_text_field( $row['id'] ) : '';
                $pkgs = isset( $row['packages'] ) ? (string) $row['packages'] : '';

                // normalize CSV: trim items & single space after commas
                $pkgList  = array_filter( array_map( 'trim', explode( ',', $pkgs ) ) );
                $pkgsNorm = implode( ', ', array_map( 'sanitize_text_field', $pkgList ) );

                if ( $code !== '' || $id !== '' || $pkgsNorm !== '' ) {
                    $clean[] = [
                        'code'     => $code,
                        'id'       => $id,
                        'packages' => $pkgsNorm,
                    ];
                }
            }
            $output['carrier_accounts'] = $clean;
        }

        // service_addresses
        if ( isset( $input['service_addresses'] ) && is_array( $input['service_addresses'] ) ) {
            $rows = array_values( array_filter( $input['service_addresses'], function( $row ) {
                if ( ! is_array($row) ) return false;
                // keep rows that have at least a name or street1
                return ! empty( $row['name'] ) || ! empty( $row['street1'] );
            } ) );

            $clean = [];
            foreach ( $rows as $row ) {
                $clean[] = [
                    'name'    => sanitize_text_field( $row['name']    ?? '' ),
                    'company' => sanitize_text_field( $row['company'] ?? '' ),
                    'phone'   => sanitize_text_field( $row['phone']   ?? '' ),
                    'street1' => sanitize_text_field( $row['street1'] ?? '' ),
                    'street2' => sanitize_text_field( $row['street2'] ?? '' ),
                    'city'    => sanitize_text_field( $row['city']    ?? '' ),
                    'state'   => sanitize_text_field( $row['state']   ?? '' ),
                    'zip'     => sanitize_text_field( $row['zip']     ?? '' ),
                    'country' => strtoupper( sanitize_text_field( $row['country'] ?? 'US' ) ),
                ];
            }
            $output['service_addresses'] = $clean;
        }

        return $output;
    }

    /**
     * Field: password (masked)
     */
    public function field_password( array $args = [] ): void {
        $key     = $args['key'] ?? '';
        $val     = $this->get_settings()[ $key ] ?? '';
        $ph      = $args['placeholder'] ?? '';
        $locked  = $this->is_locked( $key );
        $display = $val ? str_repeat( '•', 12 ) : '';

        printf(
            '<input type="password" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" %5$s autocomplete="new-password" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $key ),
            esc_attr( $locked ? $display : $val ),
            esc_attr( $ph ),
            $locked ? 'readonly' : ''
        );
        $this->maybe_locked_note( $key );
    }

    /**
     * Field: Carrier accounts table
     */
    public function field_carrier_accounts(): void {
        $opts = $this->get_settings();
        $rows = isset( $opts['carrier_accounts'] ) && is_array( $opts['carrier_accounts'] ) ? $opts['carrier_accounts'] : [];

        // Always show at least one row
        if ( empty( $rows ) ) {
            $rows = [['code' => '', 'id' => '', 'packages' => '']];
        }
        ?>
        <table class="frm-easypost-table" id="frm-easypost-carriers">
            <thead>
                <tr>
                    <th style="width:20%"><?php esc_html_e('Code', 'frm-easypost'); ?></th>
                    <th style="width:40%"><?php esc_html_e('Account ID', 'frm-easypost'); ?></th>
                    <th style="width:35%"><?php esc_html_e('Packages (comma-separated)', 'frm-easypost'); ?></th>
                    <th style="width:5%"><?php esc_html_e('Actions', 'frm-easypost'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $i => $row ) :
                $code = isset( $row['code'] ) ? (string) $row['code'] : '';
                $id   = isset( $row['id'] ) ? (string) $row['id'] : '';
                $pkgs = isset( $row['packages'] ) ? (string) $row['packages'] : '';
            ?>
                <tr>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[carrier_accounts][<?php echo (int) $i; ?>][code]"
                               value="<?php echo esc_attr( $code ); ?>"
                               placeholder="usps"
                               class="regular-text" />
                    </td>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[carrier_accounts][<?php echo (int) $i; ?>][id]"
                               value="<?php echo esc_attr( $id ); ?>"
                               placeholder="ca_xxxxxxxxxxxxxxxxxxxxxxxx"
                               class="regular-text" />
                    </td>
                    <td>
                        <input type="text"
                               name="<?php echo esc_attr( self::OPTION_NAME ); ?>[carrier_accounts][<?php echo (int) $i; ?>][packages]"
                               value="<?php echo esc_attr( $pkgs ); ?>"
                               placeholder="FlatRateEnvelope, FedExEnvelope"
                               class="regular-text" />
                    </td>
                    <td>
                        <button type="button" class="button link-delete-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="frm-easypost-actions">
            <button type="button" class="button" id="frm-easypost-add-row"><?php esc_html_e('Add row', 'frm-easypost'); ?></button>
        </div>
        <?php
    }

    /**
     * Field: Service addresses table
     */
    public function field_service_addresses(): void {
        $opts = $this->get_settings();
        $rows = isset( $opts['service_addresses'] ) && is_array( $opts['service_addresses'] )
            ? $opts['service_addresses'] : [];

        if ( empty( $rows ) ) {
            $rows = [[
                'name' => '', 'company' => '', 'street1' => '', 'street2' => '',
                'city' => '', 'state' => '', 'zip' => '', 'country' => 'US',
            ]];
        }

        $opt = esc_attr( self::OPTION_NAME );
        ?>
        <table class="frm-easypost-table" id="frm-easypost-service-addresses">
            <thead>
                <tr>
                    <th style="width:22%"><?php esc_html_e('Name / Company / Phone', 'frm-easypost'); ?></th>
                    <th style="width:25%"><?php esc_html_e('Street', 'frm-easypost'); ?></th>
                    <th style="width:20%"><?php esc_html_e('City / State', 'frm-easypost'); ?></th>
                    <th style="width:5%"><?php esc_html_e('ZIP', 'frm-easypost'); ?></th>
                    <th style="width:3%"><?php esc_html_e('Country', 'frm-easypost'); ?></th>
                    <th style="width:5%"><?php esc_html_e('Actions', 'frm-easypost'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $i => $row ): 
                $name    = (string) ( $row['name']    ?? '' );
                $company = (string) ( $row['company'] ?? '' );
                $street1 = (string) ( $row['street1'] ?? '' );
                $street2 = (string) ( $row['street2'] ?? '' );
                $city    = (string) ( $row['city']    ?? '' );
                $state   = (string) ( $row['state']   ?? '' );
                $zip     = (string) ( $row['zip']     ?? '' );
                $country = (string) ( $row['country'] ?? 'US' );
            ?>
                <tr>
                    <td>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="Phone" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][company]" value="<?php echo esc_attr($company); ?>" placeholder="Company" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][phone]" value="<?php echo esc_attr($row['phone'] ?? ''); ?>" placeholder="Phone" />
                    </td>
                    <td>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][street1]" value="<?php echo esc_attr($street1); ?>" placeholder="Street 1" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][street2]" value="<?php echo esc_attr($street2); ?>" placeholder="Street 2 (optional)" />

                    </td>
                    <td>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][city]"  value="<?php echo esc_attr($city); ?>"  placeholder="City" />
                        <br/>
                        <input class="regular-text1 full" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][state]" value="<?php echo esc_attr($state); ?>" placeholder="State" />
                    </td>
                    <td>
                        <input class="regular-text1" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][zip]" value="<?php echo esc_attr($zip); ?>" placeholder="ZIP" />
                    </td>
                    <td>
                        <input class="regular-text1" type="text" name="<?php echo $opt; ?>[service_addresses][<?php echo (int)$i; ?>][country]" value="<?php echo esc_attr($country); ?>" placeholder="US" />
                    </td>
                    <td><button type="button" class="button link-delete-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="frm-easypost-actions">
            <button type="button" class="button" id="frm-easypost-add-service-address"><?php esc_html_e('Add address', 'frm-easypost'); ?></button>
        </div>
        <?php
    }

    /**
     * Show a lock note when a setting is provided via wp-config constant
     */
    private function maybe_locked_note( string $key ): void {
        if ( 'api_key' === $key && defined( 'EASYPOST_API_KEY' ) ) {
            printf(
                ' <span class="lock">🔒 %s <code>%s</code></span>',
                esc_html__( 'Locked by', 'frm-easypost' ),
                'EASYPOST_API_KEY'
            );
        }
        if ( 'smarty_auth_id' === $key && defined( 'SMARTY_AUTH_ID' ) ) {
            printf(
                ' <span class="lock">🔒 %s <code>%s</code></span>',
                esc_html__( 'Locked by', 'frm-easypost' ),
                'SMARTY_AUTH_ID'
            );
        }
        if ( 'smarty_auth_token' === $key && defined( 'SMARTY_AUTH_TOKEN' ) ) {
            printf(
                ' <span class="lock">🔒 %s <code>%s</code></span>',
                esc_html__( 'Locked by', 'frm-easypost' ),
                'SMARTY_AUTH_TOKEN'
            );
        }
    }

    /**
     * Whether a field is locked by a constant
     */
    private function is_locked( string $key ): bool {
        if ( 'api_key' === $key && defined( 'EASYPOST_API_KEY' ) ) return true;
        if ( 'smarty_auth_id' === $key && defined( 'SMARTY_AUTH_ID' ) ) return true;
        if ( 'smarty_auth_token' === $key && defined( 'SMARTY_AUTH_TOKEN' ) ) return true;
        return false;
    }

    /**
     * Add a quick “Settings” link under the plugin on the Plugins screen
     */
    public function plugin_quick_link( array $links ): array {
        $url = admin_url( 'admin.php?page=frm-easypost-settings' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'frm-easypost' ) . '</a>';
        return $links;
    }
}

// Bootstrap in plugins_loaded
add_action( 'plugins_loaded', static function () {
    if ( class_exists( 'FrmEasypostAdminSettings' ) ) {
        new FrmEasypostAdminSettings();
    }
} );

/**
 * Helper: Read & unpack carrier accounts from options into a normalized array.
 * Returns:
 * [
 *   [ 'title' => 'USPS', 'id' => 'ca_...', 'packages' => ['FlatRateEnvelope'], 'code' => 'usps' ],
 *   ...
 * ]
 */
function frm_easypost_get_carrier_accounts(): array {
    $opts = get_option( 'frm_easypost', [] );
    $rows = isset( $opts['carrier_accounts'] ) && is_array( $opts['carrier_accounts'] ) ? $opts['carrier_accounts'] : [];

    $accounts = [];
    foreach ( $rows as $row ) {
        $code = isset( $row['code'] ) ? (string)$row['code'] : '';
        $id   = isset( $row['id'] ) ? (string)$row['id'] : '';
        $pkgS = isset( $row['packages'] ) ? (string)$row['packages'] : '';

        if ( $code === '' || $id === '' ) {
            continue; // skip incomplete rows
        }

        $packages = array_values( array_filter( array_map( 'trim', explode( ',', $pkgS ) ) ) );

        $accounts[] = [
            'title'    => strtoupper( $code ),
            'id'       => $id,
            'packages' => $packages,
            'code'     => $code,
        ];
    }

    return $accounts;
}

/**
 * NEW: Helpers to inject Smarty options into FrmSmartyApi
 */
function frm_smarty_get_config(): array {
    $opts = get_option( 'frm_easypost', [] );

    $authId    = defined('SMARTY_AUTH_ID')    ? (string) constant('SMARTY_AUTH_ID')    : (string) ($opts['smarty_auth_id']    ?? '');
    $authToken = defined('SMARTY_AUTH_TOKEN') ? (string) constant('SMARTY_AUTH_TOKEN') : (string) ($opts['smarty_auth_token'] ?? '');

    return [
        'auth_id'    => $authId,
        'auth_token' => $authToken,
        // reasonable defaults for your earlier class
        'use_post'   => true,
        'logging'    => defined('WP_DEBUG') && WP_DEBUG,
    ];
}

/**
 * Returns an instance of FrmSmartyApi configured from settings/constants.
 * Throws if credentials are missing.
 */
function frm_smarty_api(): FrmSmartyApi {
    if ( ! class_exists( 'FrmSmartyApi' ) ) {
        // require_once plugin_dir_path(__FILE__) . 'path/to/FrmSmartyApi.php';
        throw new RuntimeException('FrmSmartyApi class not found.');
    }
    $cfg = frm_smarty_get_config();
    return new FrmSmartyApi($cfg);
}
