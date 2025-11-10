<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Address verifier admin screen (submenu under "EasyPost")
 * URL: /wp-admin/admin.php?page=frm-easypost-address-verifier
 * Saves via admin-post.php to avoid WAF 403/blocked options.php posts.
 */
final class FrmAddressVerificationFormsAdmin {

    const OPTION_KEY  = 'frm_address_verification_forms';
    const CAPABILITY  = 'manage_options';
    const PARENT_SLUG = 'frm-easypost';
    const PAGE_SLUG   = 'frm-easypost-address-verifier';
    const PAGE_TITLE  = 'Address verifier';
    const MENU_TITLE  = 'Address verifier';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
        // keep register_setting so WP knows about the option (tools/import/export, etc.)
        add_action('admin_init', [__CLASS__, 'register_setting']);

        // custom save handler (admin-post)
        add_action('admin_post_frm_addrv_save', [__CLASS__, 'handle_save']);
    }

    /** Register submenu page under EasyPost */
    public static function register_menu(): void {
        add_submenu_page(
            self::PARENT_SLUG,
            __( self::PAGE_TITLE, 'frm-easypost' ),
            __( self::MENU_TITLE, 'frm-easypost' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /** Register the option so it exists and can be exported/imported */
    public static function register_setting(): void {
        register_setting(
            self::OPTION_KEY,             // settings group name
            self::OPTION_KEY,             // option name in wp_options
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
                'default'           => [
                    'provider' => 'smarty',
                    'rows'     => [],
                ],
                'show_in_rest'      => false,
            ]
        );
    }

    /** Central sanitize routine (used by both WP and our admin-post handler) */
    public static function sanitize_options( $input ): array {
        $out = [
            'provider' => 'smarty',
            'rows'     => [],
        ];

        // provider (only smarty for now)
        if ( isset($input['provider']) ) {
            $prov = strtolower( sanitize_text_field( (string) $input['provider'] ) );
            $out['provider'] = ($prov === 'smarty') ? 'smarty' : 'smarty';
        }

        // rows
        if ( isset($input['rows']) && is_array($input['rows']) ) {
            foreach ( $input['rows'] as $row ) {
                $form_id   = isset($row['form_id']) ? (int) $row['form_id'] : 0;
                $page      = isset($row['page']) ? (int) $row['page'] : 0;
                $street1   = isset($row['street1']) ? (int) $row['street1'] : 0;
                $city      = isset($row['city']) ? (int) $row['city'] : 0;
                $state     = isset($row['state']) ? (int) $row['state'] : 0;
                $zipcode   = isset($row['zipcode']) ? (int) $row['zipcode'] : 0;
                $test_mode = ! empty($row['test_mode']) ? 1 : 0; // NEW

                // keep only mappings that target a real form
                if ( $form_id > 0 ) {
                    $out['rows'][] = compact('form_id','page','street1','city','state','zipcode','test_mode');
                }
            }
        }

        return $out;
    }

    /** Admin-post save handler */
    public static function handle_save(): void {
        if ( ! current_user_can(self::CAPABILITY) ) {
            wp_die( esc_html__('You do not have permission to save these settings.', 'frm-easypost') );
        }

        check_admin_referer('frm_addrv_save_nonce');

        // pull posted array
        $posted = isset($_POST[self::OPTION_KEY]) && is_array($_POST[self::OPTION_KEY])
            ? wp_unslash( $_POST[self::OPTION_KEY] )
            : [];

        $sanitized = self::sanitize_options( $posted );
        update_option( self::OPTION_KEY, $sanitized, false );

        // redirect back with success flag
        $url = add_query_arg(
            [
                'page'               => self::PAGE_SLUG,
                'settings-updated'   => '1',
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect( $url );
        exit;
    }

    /** Render admin page (styles cloned from Service addresses) */
    public static function render_page(): void {
        if ( ! current_user_can(self::CAPABILITY) ) {
            wp_die( esc_html__('You do not have permission to access this page.', 'frm-easypost') );
        }

        $opt   = get_option(self::OPTION_KEY, [ 'provider' => 'smarty', 'rows' => [] ]);
        $forms = self::get_forms_list(); // [id => name]
        ?>
        <div class="wrap frm-easypost-settings">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-admin-site" style="font-size:28px;line-height:1;"></span>
                <?php esc_html_e( 'EasyPost — Address verifier', 'frm-easypost' ); ?>
            </h1>

            <?php if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.','frm-easypost'); ?></p></div>
            <?php endif; ?>

            <style>
                .frm-easypost-settings .card {background:#fff;border:1px solid #dcdcdc;border-radius:10px;padding:18px;margin:18px 0;max-width:100%}
                .frm-easypost-settings .muted {color:#666;margin-top:6px}
                .frm-easypost-table {width:100%;border-collapse:collapse}
                .frm-easypost-table th, .frm-easypost-table td {border:1px solid #e0e0e0;padding:8px;background:#fff;vertical-align:top}
                .frm-easypost-table th {background:#f6f7f7;text-align:left}
                .frm-easypost-actions {margin-top:8px}
                .regular-text.full { width:100%; }
                @media (max-width:782px){
                    .frm-easypost-table input[type="number"], .frm-easypost-table select { width:100%; }
                }
            </style>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="frm-addrv-settings-form">
                <input type="hidden" name="action" value="frm_addrv_save" />
                <?php wp_nonce_field('frm_addrv_save_nonce'); ?>

                <!-- Main settings card -->
                <div class="card">
                    <h2 style="margin-top:0;"><?php esc_html_e( 'Provider', 'frm-easypost' ); ?></h2>
                    <p class="muted">
                        <?php echo esc_html__( 'Formidable forms address verification • Inject into any form • Allow to choose verified address', 'frm-easypost' ); ?>
                    </p>

                    <p>
                        <label for="frm-addrv-provider" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e('Provider','frm-easypost'); ?></label>
                        <select id="frm-addrv-provider" name="<?php echo esc_attr(self::OPTION_KEY); ?>[provider]">
                            <option value="smarty" <?php selected( ($opt['provider'] ?? 'smarty'), 'smarty' ); ?>>Smarty</option>
                        </select>
                    </p>
                </div>

                <!-- Mapping table card -->
                <div class="card">
                    <h2 style="margin-top:0;"><?php esc_html_e( 'Forms verification', 'frm-easypost' ); ?></h2>
                    <table class="frm-easypost-table" id="frm-addrv-table">
                        <thead>
                            <tr>
                                <th style="width:24%"><?php esc_html_e('Form', 'frm-easypost'); ?></th>
                                <th style="width:10%"><?php esc_html_e('Page #', 'frm-easypost'); ?></th>
                                <th style="width:12%"><?php esc_html_e('Street 1 (field_id)', 'frm-easypost'); ?></th>
                                <th style="width:12%"><?php esc_html_e('City (field_id)', 'frm-easypost'); ?></th>
                                <th style="width:12%"><?php esc_html_e('State (field_id)', 'frm-easypost'); ?></th>
                                <th style="width:12%"><?php esc_html_e('Zip code (field_id)', 'frm-easypost'); ?></th>
                                <th style="width:10%"><?php esc_html_e('Test mode (Admin)', 'frm-easypost'); ?></th><!-- NEW -->
                                <th style="width:6%"><?php esc_html_e('Actions', 'frm-easypost'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rows = isset($opt['rows']) && is_array($opt['rows']) ? $opt['rows'] : [];
                        if ( empty($rows) ) {
                            $rows = [ [ 'form_id'=>0, 'page'=>0, 'street1'=>0, 'city'=>0, 'state'=>0, 'zipcode'=>0, 'test_mode'=>0 ] ];
                        }
                        foreach ( $rows as $i => $row ) :
                            self::render_row($i, $row, $forms);
                        endforeach; ?>
                        </tbody>
                    </table>

                    <div class="frm-easypost-actions">
                        <button type="button" class="button" id="frm-addrv-add-row"><?php esc_html_e('Add row', 'frm-easypost'); ?></button>
                    </div>

                    <template id="frm-addrv-row-template">
                        <?php self::render_row('__IDX__', [ 'form_id'=>0, 'page'=>0, 'street1'=>0, 'city'=>0, 'state'=>0, 'zipcode'=>0, 'test_mode'=>0 ], $forms, true ); ?>
                    </template>
                </div>

                <!-- Save button card -->
                <div class="card">
                    <h2 style="margin-top:0;"><?php esc_html_e( 'Save Changes', 'frm-easypost' ); ?></h2>
                    <?php submit_button( __( 'Save Settings', 'frm-easypost' ) ); ?>
                </div>
            </form>
        </div>

        <script>
        (function(){
            const table = document.getElementById('frm-addrv-table');
            const addBtn = document.getElementById('frm-addrv-add-row');
            const tpl = document.getElementById('frm-addrv-row-template');

            if (!table || !addBtn || !tpl) return;

            function reindex(){
                const rows = table.tBodies[0].querySelectorAll('tr');
                rows.forEach((tr, i) => {
                    tr.querySelectorAll('[name]').forEach(el => {
                        el.name = el.name.replace(/\[rows]\[\d+]/, '[rows]['+i+']');
                    });
                });
            }

            function bindDeletes(scope){
                (scope || document).querySelectorAll('.link-delete-row').forEach(btn => {
                    btn.onclick = function(){
                        const tbody = table.tBodies[0];
                        const tr = this.closest('tr');
                        if (tbody && tr && tbody.rows.length > 1) {
                            tr.remove();
                            reindex();
                        } else if (tbody && tr) {
                            // If it's the only row, clear fields instead of removing.
                            tr.querySelectorAll('input, select, textarea').forEach(el => {
                                if (el.type === 'checkbox') { el.checked = false; }
                                else { el.value = ''; }
                            });
                        }
                    };
                });
            }

            addBtn.onclick = function(){
                const idx = table.tBodies[0].rows.length;
                const html = tpl.innerHTML.replace(/__IDX__/g, idx);
                table.tBodies[0].insertAdjacentHTML('beforeend', html);
                bindDeletes(table);
            };

            bindDeletes(table);
        })();
        </script>
        <?php
    }

    /** Render a single mapping row (includes new Test mode checkbox) */
    private static function render_row( $idx, $row, $forms, $is_template = false ): void {
        $base = esc_attr(self::OPTION_KEY) . "[rows][$idx]";
        $form_id   = isset($row['form_id'])   ? (int)$row['form_id']   : 0;
        $page      = isset($row['page'])      ? (int)$row['page']      : 0;
        $street1   = isset($row['street1'])   ? (int)$row['street1']   : 0;
        $city      = isset($row['city'])      ? (int)$row['city']      : 0;
        $state     = isset($row['state'])     ? (int)$row['state']     : 0;
        $zipcode   = isset($row['zipcode'])   ? (int)$row['zipcode']   : 0;
        $test_mode = ! empty($row['test_mode']) ? 1 : 0;
        ?>
        <tr>
            <td>
                <select name="<?php echo $base; ?>[form_id]">
                    <option value="0">— <?php esc_html_e('Select form','frm-easypost'); ?> —</option>
                    <?php foreach ( $forms as $id => $name ) : ?>
                        <option value="<?php echo (int)$id; ?>" <?php selected( $form_id, (int)$id ); ?>>
                            <?php echo esc_html( $name . " (ID: $id)" ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="<?php echo $base; ?>[page]"    value="<?php echo esc_attr($page ?: ''); ?>"    placeholder="1" /></td>
            <td><input type="number" name="<?php echo $base; ?>[street1]" value="<?php echo esc_attr($street1 ?: ''); ?>" placeholder="field_id" /></td>
            <td><input type="number" name="<?php echo $base; ?>[city]"    value="<?php echo esc_attr($city ?: ''); ?>"    placeholder="field_id" /></td>
            <td><input type="number" name="<?php echo $base; ?>[state]"   value="<?php echo esc_attr($state ?: ''); ?>"   placeholder="field_id" /></td>
            <td><input type="number" name="<?php echo $base; ?>[zipcode]" value="<?php echo esc_attr($zipcode ?: ''); ?>" placeholder="field_id" /></td>
            <td style="text-align:center;">
                <label>
                    <input type="checkbox" name="<?php echo $base; ?>[test_mode]" value="1" <?php checked( $test_mode, 1 ); ?> />
                    <?php esc_html_e('Admin', 'frm-easypost'); ?>
                </label>
            </td>
            <td><button type="button" class="button link-delete-row" aria-label="<?php esc_attr_e('Delete row','frm-easypost'); ?>">✕</button></td>
        </tr>
        <?php
    }

    /** Get Formidable forms list [id => name] without using FrmForm::getAll() */
    private static function get_forms_list(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'frm_forms';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
            return [];
        }

        $sql = "SELECT id, name 
                FROM {$table}
                ORDER BY name ASC, id ASC";

        $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $out = [];
        foreach ( $rows as $row ) {
            $id   = isset($row->id)   ? (int) $row->id   : 0;
            $name = isset($row->name) ? (string) $row->name : '';
            if ( $id > 0 ) {
                $out[$id] = ($name !== '') ? $name : ('Form #' . $id);
            }
        }
        return $out;
    }
}

// Initialize
FrmAddressVerificationFormsAdmin::init();