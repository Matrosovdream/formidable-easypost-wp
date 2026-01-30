<?php
if ( ! defined('ABSPATH') ) { exit; }

final class FrmEasypostLabelsMassbuyListShortcode {

    private const SHORTCODE = 'frm-easypost-labels-massbuy-list';

    /** Field IDs (your rules) */
    private const FIELD_STATUS          = 7;
    private const FIELD_SERVICE         = 12;
    private const FIELD_FLAGS           = 273;
    private const FIELD_PHOTO_NO        = 328;
    private const FIELD_PHOTO_DONE      = 670;
    private const FIELD_PROCESSING_TIME = 211;
    private const FIELD_MAILING_ADDRESS = 37;
    private const FIELD_NOTE            = 5;

    private const PHOTO_IFRAME_URL      = 'https://www.unitedpassport.com/photo-iframe/';
    private const PAGE_PRINT_LABELS_URL = 'labels-mass-buy-print/';


    /** Processing time values (field 211) */
    // Dev
    private const PT_STANDARD  = FRM_EP_PROC_TIME_FIELDS['standard']['id'];
    private const PT_EXPEDITED = FRM_EP_PROC_TIME_FIELDS['expedited']['id'];
    private const PT_RUSH      = FRM_EP_PROC_TIME_FIELDS['rushed']['id'];

    /** Default pagination */
    private const DEFAULT_PER_PAGE = 10;

    /** Service groups */
    private const SERVICES_ONE_LABEL = [
        'New Passport',
        'Child Passport',
        'Damaged Passport',
        'Lost Passport',
        'Stolen Passport',
    ];

    private const SERVICES_TWO_LABELS = [
        'Passport Renewal',
        'Name Change',
        'Second Passport',
    ];

    /** AJAX */
    private const AJAX_VERIFY_ADDRESS       = 'ffda_massbuy_entry_verify_address';
    private const AJAX_CALCULATE_RATES      = 'ffda_massbuy_entry_calculate_rates_for_entry';
    private const AJAX_BUY_LABEL_FOR_ENTRY  = 'ajax_buy_label_for_entry';
    private const AJAX_SET_COMPLETE_ENTRY   = 'ajax_set_complete_entry';
    private const NONCE_KEY                 = 'ffda_massbuy_labels_nonce';
    private const AJAX_UPDATE_ADDRESS = 'ffda_massbuy_entry_update_address';


    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_bootstrap_assets']);

        add_action('wp_ajax_' . self::AJAX_VERIFY_ADDRESS, [__CLASS__, 'ajax_verify_address_for_entry']);
        add_action('wp_ajax_' . self::AJAX_CALCULATE_RATES, [__CLASS__, 'ajax_calculate_rates_for_entry']);
        add_action('wp_ajax_' . self::AJAX_BUY_LABEL_FOR_ENTRY, [__CLASS__, 'ajax_buy_label_for_entry']);
        add_action('wp_ajax_' . self::AJAX_SET_COMPLETE_ENTRY, [__CLASS__, 'ajax_set_complete_entry']);
        add_action('wp_ajax_' . self::AJAX_UPDATE_ADDRESS, [__CLASS__, 'ajax_update_address_for_entry']);

    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'render']);
    }

    public static function register_bootstrap_assets(): void {
        wp_register_style(
            'ffda_bootstrap_css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            [],
            '5.3.3'
        );

        wp_register_script(
            'ffda_bootstrap_js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            [],
            '5.3.3',
            true
        );
    }

    public static function render($atts = [], $content = ''): string {

        if ( ! class_exists('FrmEntry') ) {
            return self::alert('Formidable Forms (FrmEntry) not loaded.', 'danger');
        }
        if ( ! class_exists('FrmEasypostLabelHelper') ) {
            return self::alert('FrmEasypostLabelHelper class not found. Load it before using this shortcode.', 'danger');
        }

        wp_enqueue_style('ffda_bootstrap_css');
        wp_enqueue_script('ffda_bootstrap_js');

        // Filters from GET
        $photo_mode = isset($_GET['photo']) ? sanitize_text_field((string) $_GET['photo']) : 'without';
        $photo_mode = ($photo_mode === 'with') ? 'with' : 'without';

        $group = isset($_GET['group']) ? sanitize_text_field((string) $_GET['group']) : 'one';
        $group = ($group === 'two') ? 'two' : 'one';

        // Processing time filter (optional)
        $processing = isset($_GET['processing']) ? (int) $_GET['processing'] : 0;
        $allowed_processing = [0, self::PT_STANDARD, self::PT_EXPEDITED, self::PT_RUSH];
        if ( ! in_array($processing, $allowed_processing, true) ) {
            $processing = 0;
        }

        $page = isset($_GET['pg']) ? (int) $_GET['pg'] : 1;
        if ($page < 1) { $page = 1; }

        $per_page = self::DEFAULT_PER_PAGE;

        $services = ($group === 'two') ? self::SERVICES_TWO_LABELS : self::SERVICES_ONE_LABEL;

        $helper = new FrmEasypostLabelHelper();
        $entryHelper = class_exists('FrmEasypostEntryHelper') ? new FrmEasypostEntryHelper() : null;

        $args = [
            'page' => $page,
            'per_page' => $per_page,
            'include_drafts' => false,
            'status' => [
                'field_id' => self::FIELD_STATUS,
                'value' => 'Verified',
            ],
            'exclude_flag' => [
                'field_id' => self::FIELD_FLAGS,
                'contains' => 'label-printed',
            ],
            'photo' => [
                'mode' => $photo_mode,
                'field_id_without' => self::FIELD_PHOTO_NO,
                'value_without' => 'photo-no',
                'field_id_with' => self::FIELD_PHOTO_DONE,
                'value_with' => 'photo-done',
            ],
            'service' => [
                'field_id' => self::FIELD_SERVICE,
                'values' => $services,
            ],
            'processing_time' => [
                'field_id' => self::FIELD_PROCESSING_TIME,
                'value'    => $processing,
            ],
        ];

        $result = $helper->getMassUpdateEntries($args);

        $items = $result['items'] ?? [];
        $pagination = $result['pagination'] ?? [
            'total' => 0,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => 0,
            'offset' => 0,
        ];

        $total = (int) ($pagination['total'] ?? 0);
        $total_pages = (int) ($pagination['total_pages'] ?? 0);
        $current_page = (int) ($pagination['current_page'] ?? $page);

        // AJAX vars for JS
        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce(self::NONCE_KEY);

        ob_start();

        $print_url = home_url(self::PAGE_PRINT_LABELS_URL);

        echo '<div class="ffda-massbuy-list"
                data-ajax-url="' . esc_attr($ajax_url) . '"
                data-ajax-nonce="' . esc_attr($nonce) . '"
                data-print-url="' . esc_attr($print_url) . '">';


        self::render_filters([
            'photo' => $photo_mode,
            'group' => $group,
            'processing' => $processing,
        ]);

        // Alert area for errors (Calculate can show red alert)
        echo '<div id="ffda_alert_area"></div>';

        // Summary
        echo '<div class="d-flex align-items-center justify-content-between mb-2">';
        echo '<div class="text-muted small">';
        echo 'Total: <strong>' . esc_html((string) $total) . '</strong>';
        echo ' &nbsp;|&nbsp; Per page: <strong>' . esc_html((string) $per_page) . '</strong>';
        if ($total_pages > 0) {
            echo ' &nbsp;|&nbsp; Page <strong>' . esc_html((string) $current_page) . '</strong> / ' . esc_html((string) $total_pages);
        }
        echo '</div>';
        echo '</div>';

        // Table
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped align-middle table-listing">';
        echo '<thead class="table-light">';
        echo '<tr>';
        echo '<th style="width:40px;" class="text-center">';
        echo '<input type="checkbox" class="form-check-input" id="ffda_select_all" aria-label="Select all">';
        echo '</th>';
        echo '<th style="width:40px;">ID</th>';
        echo '<th style="width:140px;">Created</th>';
        echo '<th style="width:180px;">Service (12)</th>';
        echo '<th style="width:140px;">Features</th>';
        echo '<th style="width:400px;">Mailing address</th>';
        echo '<th style="width:130px;">Proc time</th>';
        echo '<th style="width:350px;">Rates</th>';
        echo '<th style="width:200px;">Labels</th>';
        echo '<th style="width:110px;" class="text-end"></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="9" class="text-center text-muted py-4">No results for this filter.</td></tr>';
        } else {
            foreach ($items as $entry) {
                if ( ! is_object($entry) || empty($entry->id) ) { continue; }

                // Get shipments for the entry
                $shipments = self::getEntryShipments( (int) $entry->id );

                $delivered = (int) ($shipments['stats']['delivered'] ?? 0);
                $refunded  = (int) ($shipments['stats']['refunded'] ?? 0);

                $id = (int) $entry->id;

                // Prepare created date/time
                $created = isset($entry->created_at) ? (string) $entry->created_at : '';
                $created = date('Y-m-d H:i', strtotime($created));
                [$createdDate, $createdTime] = explode(' ', $created . ' ');

                // Get all metas
                $metas = isset($entry->metas) && is_array($entry->metas) ? $entry->metas : [];

                // Get service and processing time
                $service_val = self::meta_val($metas, self::FIELD_SERVICE);
                $proc_211    = self::meta_val($metas, self::FIELD_PROCESSING_TIME);

                $photo_done = self::meta_val($metas, self::FIELD_PHOTO_DONE);

                $note = trim(self::meta_val($metas, self::FIELD_NOTE));

                $mailing_addr = $entryHelper
                    ? $entryHelper->getEntryAddressFields($id)
                    : ['combined' => self::meta_val($metas, self::FIELD_MAILING_ADDRESS)];

                $photo_badge = ($photo_mode === 'with')
                    ? '<span class="badge text-bg-success">photo-done</span>'
                    : '<span class="badge text-bg-secondary">photo-no</span>';

                $proc_label = self::processing_label($proc_211);
                $proc_class = 'text-bg-info';
                if ($proc_label === 'Expedited') {
                    $proc_class = 'text-bg-warning';
                } elseif ($proc_label === 'Rush') {
                    $proc_class = 'text-bg-danger';
                }

                $proc_badge = $proc_label !== ''
                    ? '<span class="badge ' . esc_attr($proc_class) . '">' . esc_html($proc_label) . '</span>'
                    : '<span class="text-muted">-</span>';

                $order_url = home_url('/orders/entry/' . $id . '/?order=' . $id);
                $photo_iframe_url = self::PHOTO_IFRAME_URL . '?order=' . $id;

                echo '<tr data-entry-id="' . esc_attr((string)$id) . '">';
                echo '<td class="text-center">';
                echo '<input type="checkbox" class="form-check-input ffda-entry-check" name="ffda_entries[]" value="' . esc_attr((string)$id) . '" aria-label="Select entry ' . esc_attr((string)$id) . '">';
                echo '</td>';
                echo '<td><strong>' . esc_html((string) $id) . '</strong></td>';
                echo '<td>' . esc_html($createdDate) . '<br/>' . esc_html($createdTime) . '</td>';
                echo '<td>' . esc_html($service_val ?: '-') . '</td>';

                // Features
                echo '<td class="ffda-features-cell">' . $photo_badge;
                if ( $photo_done === 'photo-done' ) {
                    echo '<iframe src="' . esc_url($photo_iframe_url) . '" width="150" height="150" scrolling="no" style="margin-top: 10px"></iframe>';
                }
                echo '</td>';

                $addr = is_array($mailing_addr) ? $mailing_addr : [];
                $addr_first = (string) ($addr['firstname'] ?? '');
                $addr_last  = (string) ($addr['lastname'] ?? '');
                $addr_phone = (string) ($addr['phone'] ?? '');
                $addr_st1   = (string) ($addr['street1'] ?? '');
                $addr_st2   = (string) ($addr['street2'] ?? '');
                $addr_city  = (string) ($addr['city'] ?? '');
                $addr_state = (string) ($addr['state'] ?? '');
                $addr_zip   = (string) ($addr['zip'] ?? '');

                echo '<td class="ffda-mailing-addr-cell">';

                  // visible combined text
                  echo '<div class="ffda-addr-text">' . esc_html($addr['combined'] ?? '-') . '</div>';

                  // small button
                  echo '<button type="button" class="btn btn-link p-0 ffda-edit-addr-btn" style="font-size:12px;">Edit address</button>';

                  // hidden form
                  echo '<div class="ffda-edit-addr-form mt-2" style="display:none;">';

                    echo '<div class="row g-2">';
                      echo '<div class="col-6"><input type="text" class="form-control form-control-sm" name="firstname" placeholder="First name" value="' . esc_attr($addr_first) . '"></div>';
                      echo '<div class="col-6"><input type="text" class="form-control form-control-sm" name="lastname"  placeholder="Last name"  value="' . esc_attr($addr_last) . '"></div>';

                      echo '<div class="col-12"><input type="text" class="form-control form-control-sm" name="phone" placeholder="Phone" value="' . esc_attr($addr_phone) . '"></div>';

                      echo '<div class="col-12"><input type="text" class="form-control form-control-sm" name="street1" placeholder="Street 1" value="' . esc_attr($addr_st1) . '"></div>';
                      echo '<div class="col-12"><input type="text" class="form-control form-control-sm" name="street2" placeholder="Street 2" value="' . esc_attr($addr_st2) . '"></div>';

                      echo '<div class="col-12"><input type="text" class="form-control form-control-sm" name="city"  placeholder="City" value="' . esc_attr($addr_city) . '"></div>';
                      echo '<div class="col-6"><input type="text" class="form-control form-control-sm" name="state" placeholder="State" value="' . esc_attr($addr_state) . '"></div>';
                      echo '<div class="col-6"><input type="text" class="form-control form-control-sm" name="zip"   placeholder="Zip" value="' . esc_attr($addr_zip) . '"></div>';
                    echo '</div>';

                    echo '<div class="d-flex align-items-center gap-2 mt-2">';
                      echo '<button type="button" class="btn btn-sm btn-primary ffda-save-addr-btn">Save</button>';
                      echo '<span class="ffda-addr-save-status text-muted small"></span>';
                    echo '</div>';

                  echo '</div>';

                  if ($note !== '') {
                      echo '<div class="ffda-note-wrap mt-2">';
                      echo '  <button type="button" class="btn btn-link p-0 ffda-note-btn" aria-label="Note" title="Note">';
                      echo '    <span class="ffda-note-icon">📝</span><span class="ffda-note-label">Note</span>';
                      echo '  </button>';
                      echo '  <div class="ffda-note-text" style="display:none;">' . esc_html($note) . '</div>';
                      echo '</div>';
                  }

                echo '</td>';

                echo '<td>' . $proc_badge . '</td>';

                // Rates
                echo '<td class="ffda-rates-cell"><span class="ffda-rates-placeholder"></span></td>';

                // Labels
                echo '<td class="ffda-labels-cell small">';
                echo '<div>Active: <strong>' . esc_html((string)$delivered) . '</strong></div>';

                $refund_class = $refunded > 0 ? 'ffda-refunded-alert' : '';
                echo '<div class="' . esc_attr($refund_class) . '">Refunded: <strong>' . esc_html((string)$refunded) . '</strong></div>';

                echo '<button type="button"
                    class="btn btn-outline-secondary btn-sm mt-1 ffda-show-shipments"
                    data-entry-id="' . esc_attr((string)$id) . '"
                    onclick="toggleShipments(this)">
                    Show all
                    </button>';

                echo '<div class="easypost-shipments-container" style="display:none;">';
                echo do_shortcode('[easypost-shipments entry=' . esc_attr((string)$id) . ']');
                echo '</div>';

                echo '<script>
                  function toggleShipments(button) {
                    var container = button.nextElementSibling;
                    if (container.style.display === "none") {
                      container.style.display = "block";
                      button.textContent = "Hide all";
                    } else {
                      container.style.display = "none";
                      button.textContent = "Show all";
                    }
                  }
                </script>';

                // Open
                echo '<td class="text-end"><a class="btn btn-outline-primary btn-sm" href="' . esc_url($order_url) . '" target="_blank" rel="noopener">Open</a></td>';

                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div class="alert alert-info mt-3 mb-2">';
        echo '<div class="fw-semibold mb-1">Form type groups mapping</div>';
        echo '<div class="small">';
        echo '<div class="mb-2"><span class="badge text-bg-secondary me-2">DS11</span>' . esc_html(implode(', ', self::SERVICES_ONE_LABEL)) . '</div>';
        echo '<div><span class="badge text-bg-secondary me-2">DS82</span>' . esc_html(implode(', ', self::SERVICES_TWO_LABELS)) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // table-responsive

        self::render_selection_ui();
        self::render_pagination($current_page, $total_pages, [
            'photo' => $photo_mode,
            'group' => $group,
            'processing' => $processing,
        ]);

        // Styles
        echo '<style>
            .ep-verify-status {
                font-size: 12px;
                display: inline-block;
                width: fit-content;
                border: 1px solid green;
                border-radius: 4px;
                padding: 3px;
                margin-top: 6px;
                margin-right: 10px;
            }
            .ep-verify-status.ok{color:#0a7a2b}
            .ep-verify-status.err{color:#b00020;border:1px solid #b00020;}
            .ep-normalized-block{
              width: 100%;
              max-width: 520px;
              white-space: normal;
            }

            .ffda-inline-msg { font-size: 12px; padding: 2px 6px; border-radius: 4px; display:inline-block; }
            .ffda-inline-msg.ok { color:#0a7a2b; border:1px solid #0a7a2b; }
            .ffda-inline-msg.err { color:#b00020; border:1px solid #b00020; }

            .label-printed-tag { margin-left: 6px; }

            .table-listing { font-size: 14px; }
            .table-listing td, .table-listing th { padding: 10px 15px!important; }

            .ffda-refunded-alert { color: #b00020; font-weight: 700; }

            /* --- Confirm modal tweaks --- */
            .ffda-confirm-backdrop{
              position:fixed; inset:0; background:rgba(0,0,0,.45);
              display:none; align-items:center; justify-content:center;
              z-index: 99999;
              padding: 18px;
            }
            .ffda-confirm-box{
              background:#fff; border-radius:12px; box-shadow: 0 10px 30px rgba(0,0,0,.25);
              width:100%; max-width:460px; padding:18px;
            }
            .ffda-confirm-title{ font-weight:700; font-size:16px; margin:0 0 8px; }
            .ffda-confirm-text{ font-size:14px; margin:0 0 14px; color:#444; }
            .ffda-confirm-actions{ display:flex; gap:10px; justify-content:flex-end; }

            .ffda-rate-group { margin-bottom: 10px; }
            .ffda-rate-group-title { font-weight: 700; margin-bottom: 6px; }

            .ffda-print-backdrop{
              position:fixed;
              inset:0;
              background:rgba(0,0,0,.55);
              display:none;
              align-items:center;
              justify-content:center;
              z-index: 99998;
              padding: 12px;
            }

            .ffda-print-box{
              width: 90vw;            /* ✅ requested 90% width */
              max-width: 1400px;
              height: 90vh;
              background:#fff;
              border-radius: 12px;
              box-shadow: 0 10px 30px rgba(0,0,0,.25);
              overflow:hidden;
            }

            .ffda-print-head{
              display:flex;
              align-items:center;
              justify-content:space-between;
              gap: 10px;
              padding: 10px 12px;
              border-bottom: 1px solid #eee;
            }

            .ffda-print-title{
              font-weight: 700;
            }

            .new-tracking-code-tag { margin-left: 6px; }

            .ffda-edit-addr-form .form-control-sm {     
              font-size: 14px;
              height: 22px;
              padding: 8px; 
            }
            .ffda-edit-addr-btn { text-decoration: underline; }

            .ffda-note-wrap { display:block; }
            .ffda-note-btn{
              display:inline-flex;
              align-items:center;
              gap:6px;
              font-size:12px;
              text-decoration:none;
            }
            .ffda-note-icon{
              width:22px;
              height:22px;
              display:inline-flex;
              align-items:center;
              justify-content:center;
              border-radius:999px;
              background:#f1f3f5;
              border:1px solid #e5e7eb;
            }
            .ffda-note-label{ color:#0d6efd; }
            .ffda-note-text{
              margin-top:6px;
              font-size:12px;
              line-height:1.35;
              padding:8px 10px;
              border:1px solid #eee;
              border-radius:8px;
              background:#fafafa;
              white-space:pre-wrap;
            }
        </style>';

        // ✅ Confirmation modal HTML (used for Buy & Complete)
        echo '
        <div class="ffda-confirm-backdrop" id="ffda_confirm_modal" aria-hidden="true">
          <div class="ffda-confirm-box" role="dialog" aria-modal="true" aria-labelledby="ffda_confirm_title">
            <div class="ffda-confirm-title" id="ffda_confirm_title">Confirm</div>
            <div class="ffda-confirm-text" id="ffda_confirm_text">Are you sure?</div>
            <div class="ffda-confirm-actions">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="ffda_confirm_no">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" id="ffda_confirm_yes">Yes</button>
            </div>
          </div>
        </div>';

        echo '</div>'; // .ffda-massbuy-list

        echo '
        <div class="ffda-print-backdrop" id="ffda_print_modal" aria-hidden="true">
          <div class="ffda-print-box" role="dialog" aria-modal="true" aria-label="Print labels">
            <div class="ffda-print-head">
              <div class="ffda-print-title">Print labels</div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="ffda_print_close">Close</button>
            </div>

            <iframe id="ffda_print_iframe" src="about:blank" style="width:100%; height:calc(90vh - 52px); border:0;"></iframe>
          </div>
        </div>';

        return (string) ob_get_clean();
    }

    private static function getEntryShipments( int $entry_id ): array {

      $model     = new FrmEasypostShipmentModel();
      $shipments = $model->getAllByEntryId( $entry_id );

      // Collect stats
      $stats = [
        'total' => count( $shipments ),
        'voided' => 0,
        'refunded' => 0,
        'delivered' => 0,
      ];

      foreach ( $shipments as $s ) {

        if ( isset( $s['status'] ) ) {
          $status = strtolower( (string) $s['status'] );
          if ( $status === 'voided' ) {
            $stats['voided']++;
          } elseif ( $status === 'delivered' ) {
            $stats['delivered']++;
          }
        }

        if ( ! empty( $s['refund_status'] ) ) {
          $stats['refunded']++;
        }
      }

      return [
        'stats'     => $stats,
        'shipments' => $shipments,
      ];
    }

    /**
     * FILTERS + MASS ACTION BUTTONS (LEFT of Apply)
     */
    private static function render_filters(array $state): void {
        $photo = $state['photo'] ?? 'without';
        $group = $state['group'] ?? 'one';
        $processing = (int) ($state['processing'] ?? 0);

        $action = self::current_url_without(['pg']);

        echo '<form method="get" action="' . esc_url($action) . '" class="card card-body mb-3">';
        echo '<div class="row g-2 align-items-end">';

        echo '<div class="col-12 col-md-4">';
        echo '<label class="form-label mb-1">Photos</label>';
        echo '<select class="form-select form-select-sm" name="photo">';
        echo '<option value="without"' . selected($photo, 'without', false) . '>WITHOUT photos (328 = photo-no)</option>';
        echo '<option value="with"' . selected($photo, 'with', false) . '>WITH photos (670 = photo-done)</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="col-12 col-md-4">';
        echo '<label class="form-label mb-1">Form type groups</label>';
        echo '<select class="form-select form-select-sm" name="group">';
        echo '<option value="one"' . selected($group, 'one', false) . '>DS11</option>';
        echo '<option value="two"' . selected($group, 'two', false) . '>DS82</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="col-12 col-md-4">';
        echo '<label class="form-label mb-1">Processing time (field 211)</label>';
        echo '<select class="form-select form-select-sm" name="processing">';
        echo '<option value="0"' . selected($processing, 0, false) . '>All</option>';
        echo '<option value="' . esc_attr((string) self::PT_STANDARD) . '"' . selected($processing, self::PT_STANDARD, false) . '>Standard (145)</option>';
        echo '<option value="' . esc_attr((string) self::PT_EXPEDITED) . '"' . selected($processing, self::PT_EXPEDITED, false) . '>Expedited (' . esc_html((string) self::PT_EXPEDITED) . ')</option>';
        echo '<option value="' . esc_attr((string) self::PT_RUSH) . '"' . selected($processing, self::PT_RUSH, false) . '>Rush (' . esc_html((string) self::PT_RUSH) . ')</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="col-12 d-flex flex-wrap justify-content-between align-items-end gap-2 mt-2">';

        // LEFT controls
        echo '<div class="d-flex flex-wrap align-items-end gap-2">';

        echo '<div>';
        echo '<label class="form-label mb-1">Select Carrier</label>';
        echo '<select class="form-select form-select-sm" id="ffda_carrier_select" style="min-width:180px; height:32px;">';
        echo '<option value="fedex">FedEx</option>';
        echo '<option value="usps">USPS</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="d-flex flex-wrap gap-2 align-items-end" style="padding-top:22px;">';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="verify" disabled>Verify</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="calculate" disabled>Calculate</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="buy" disabled>Buy</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="complete" disabled>Complete</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="print" disabled>Print</button>';
        echo '</div>';

        echo '<div class="small" style="padding-top:22px;">';
        echo '<span id="ffda_mass_action_status" class="text-success">Select entries to enable actions.</span>';
        echo '</div>';

        echo '</div>'; // left

        // RIGHT Apply
        echo '<div class="text-end">';
        echo '<button type="submit" class="btn btn-primary btn-lg">Apply</button>';
        echo '</div>';

        echo '</div>'; // row

        // Preserve unknown query params
        foreach ($_GET as $k => $v) {
            if (in_array($k, ['photo','group','processing','pg'], true)) { continue; }
            if (is_array($v)) { continue; }
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
        }

        echo '</form>';

        // --- Inline JS (HEREDOC to avoid PHP/JS quote parse errors) ---
        $ajaxVerify   = esc_js(self::AJAX_VERIFY_ADDRESS);
        $ajaxCalc     = esc_js(self::AJAX_CALCULATE_RATES);
        $ajaxBuy      = esc_js(self::AJAX_BUY_LABEL_FOR_ENTRY);
        $ajaxComplete = esc_js(self::AJAX_SET_COMPLETE_ENTRY);
        $ajaxUpdateAddr = esc_js(self::AJAX_UPDATE_ADDRESS);

        $js = <<<JS
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  var wrap = qs(".ffda-massbuy-list");
  if(!wrap){ return; }

  var alertArea = qs("#ffda_alert_area", wrap);

  function clearAlert(){
    if(alertArea){ alertArea.innerHTML = ""; }
  }
  function showAlertDanger(message){
    if(!alertArea){ return; }
    alertArea.innerHTML = '<div class="alert alert-danger mb-3">' + (message ? String(message) : "Error") + "</div>";
  }

  function getSelectedCheckboxes(){
    return qsa(".ffda-entry-check:checked", wrap);
  }

  var buttons = qsa(".ffda-mass-btn", wrap);
  var carrierSel = qs("#ffda_carrier_select", wrap);
  var statusEl = qs("#ffda_mass_action_status", wrap);

  function setButtonsEnabled(enabled){
    buttons.forEach(function(btn){ btn.disabled = !enabled; });
  }

  function setStatus(msg, type){
    if(!statusEl){ return; }
    statusEl.textContent = msg || "";
    statusEl.className = "";
    statusEl.classList.add(type === "err" ? "text-danger" : "text-success");
  }

  function refresh(){
    var checked = getSelectedCheckboxes();
    setButtonsEnabled(checked.length > 0);
    if(checked.length === 0){
      setStatus("Select entries to enable actions.", "ok");
    } else {
      setStatus("", "ok");
    }
  }

  document.addEventListener("change", function(e){
    if(!e || !e.target){ return; }
    if(e.target.classList && e.target.classList.contains("ffda-entry-check")){
      refresh();
    }
    if(e.target && e.target.id === "ffda_select_all"){
      setTimeout(refresh, 0);
    }
  });

  // Toggle form
document.addEventListener("click", function(e){
  var t = e && e.target;
  if(!t) return;

  if(t.classList && t.classList.contains("ffda-edit-addr-btn")){
    e.preventDefault();
    var cell = t.closest(".ffda-mailing-addr-cell");
    if(!cell) return;

    var form = cell.querySelector(".ffda-edit-addr-form");
    if(!form) return;

    form.style.display = (form.style.display === "none" || !form.style.display) ? "block" : "none";
  }
});

document.addEventListener("click", function(e){
  var t = e && e.target;
  if(!t) return;

  // allow click on icon/label inside the button
  var btn = t.closest ? t.closest(".ffda-note-btn") : null;
  if(!btn) return;

  e.preventDefault();

  var wrap = btn.closest(".ffda-note-wrap");
  if(!wrap) return;

  var text = wrap.querySelector(".ffda-note-text");
  if(!text) return;

  text.style.display = (text.style.display === "none" || !text.style.display) ? "block" : "none";
});


// Save address
document.addEventListener("click", async function(e){
  var t = e && e.target;
  if(!t || !(t.classList && t.classList.contains("ffda-save-addr-btn"))) return;

  e.preventDefault();

  var cell = t.closest(".ffda-mailing-addr-cell");
  var row  = t.closest("tr");
  if(!cell || !row) return;

  var entryId = parseInt(row.getAttribute("data-entry-id") || "0", 10);
  if(!entryId) return;

  var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
  var nonce   = wrap.getAttribute("data-ajax-nonce") || "";
  if(!ajaxUrl || !nonce) return;

  var status = cell.querySelector(".ffda-addr-save-status");
  if(status) status.textContent = "Saving…";

  var formWrap = cell.querySelector(".ffda-edit-addr-form");
  if(!formWrap) return;

  function val(name){
    var inp = formWrap.querySelector('[name="'+name+'"]');
    return inp && inp.value ? String(inp.value) : "";
  }

  try{
    var fd = new FormData();
    fd.append("action", "{$ajaxUpdateAddr}");
    fd.append("nonce", nonce);
    fd.append("entry_id", String(entryId));

    fd.append("firstname", val("firstname"));
    fd.append("lastname",  val("lastname"));
    fd.append("phone",     val("phone"));
    fd.append("street1",   val("street1"));
    fd.append("street2",   val("street2"));
    fd.append("city",      val("city"));
    fd.append("state",     val("state"));
    fd.append("zip",       val("zip"));

    var resp = await fetch(ajaxUrl, { method:"POST", credentials:"same-origin", body:fd });
    var json = null;
    try { json = await resp.json(); } catch(err){ json = null; }

    var payload = (json && typeof json.ok !== "undefined") ? json
                : (json && json.success && json.data) ? json.data
                : (json && json.data) ? json.data
                : { ok:false, message:"Bad response" };

    if(payload && payload.ok && payload.address){
      var addr = payload.address;
      var combined = addr.combined ? String(addr.combined) : "";

      var textEl = cell.querySelector(".ffda-addr-text");
      if(textEl) textEl.textContent = combined || "-";

      if(status) status.textContent = "Saved";

      // optionally auto-hide
      if(formWrap) formWrap.style.display = "none";
    } else {
      if(status) status.textContent = (payload && payload.message) ? String(payload.message) : "Save failed";
    }
  } catch(err){
    if(status) status.textContent = (err && err.message) ? err.message : "Network error";
  }
});


  function sleep(ms){ return new Promise(function(res){ setTimeout(res, ms); }); }

  function ensureVerifyBadge(container){
    var el = container.querySelector(".ep-verify-status");
    if(!el){
      el = document.createElement("span");
      el.className = "ep-verify-status";
      container.appendChild(el);
    }
    return el;
  }

  function ensureMatchBadge(container){
    var el = container.querySelector(".ep-match-status");
    if(!el){
      el = document.createElement("span");
      el.className = "ep-verify-status ep-match-status";
      container.appendChild(el);
    }
    return el;
  }

  function ensureNormalizedBlock(container){
    var el = container.querySelector(".ep-normalized-block");
    if(!el){
      el = document.createElement("div");
      el.className = "ep-verify-status ok ep-normalized-block";
      container.appendChild(el);
    }
    return el;
  }

  function renderVerifyResult(cell, payload){
    var badge = ensureVerifyBadge(cell);

    var ok = !!(payload && payload.ok);
    var msg = (payload && payload.message) ? String(payload.message) : "";

    if(ok){
      badge.className = "ep-verify-status ok";
      badge.textContent = "✓ Verified";
    } else {
      badge.className = "ep-verify-status err";
      badge.textContent = "✗ " + (msg || "Address not verified.");
    }

    var matched = false;
    if(payload && typeof payload.matched_addresses !== "undefined"){
      matched = !!payload.matched_addresses;
    }

    var matchBadge = ensureMatchBadge(cell);
    if(matched){
      matchBadge.className = "ep-verify-status ok ep-match-status";
      matchBadge.textContent = "✓ Matched";
    } else {
      matchBadge.className = "ep-verify-status err ep-match-status";
      matchBadge.textContent = "✗ Not matched";
    }

    var normalized = (payload && payload.normalized_address) ? String(payload.normalized_address) : "";
    if(normalized){
      var normBlock = ensureNormalizedBlock(cell);
      normBlock.className = "ep-verify-status ok ep-normalized-block";
      normBlock.innerHTML = "<strong>Normalized:</strong><br/>" + normalized;
    }
  }

  function runPrintSelected(){
  clearAlert();

  var checked = getSelectedCheckboxes();
  if(!checked.length){
    showAlertDanger("Select entries first.");
    setStatus("No entries selected.", "err");
    return;
  }

  var baseUrl = wrap.getAttribute("data-print-url") || "";
  if(!baseUrl){
    showAlertDanger("Missing print URL (data-print-url).");
    setStatus("Missing print URL.", "err");
    return;
  }

  var shipmentIds = [];
  var seen = {};

  checked.forEach(function(cb){
    var row = cb.closest("tr");
    if(!row) return;

    // all rate selects in this selected row (can be multiple groups)
    var selects = qsa(".ffda-rate-select", row);

    selects.forEach(function(sel){
      if(!sel || !sel.selectedOptions || !sel.selectedOptions[0]) return;

      // NOTE: shipment_id is stored on the selected OPTION
      var shipmentId = sel.selectedOptions[0].getAttribute("data-shipment-id") || "";
      shipmentId = String(shipmentId).trim();
      if(!shipmentId) return;

      // unique
      if(!seen[shipmentId]){
        seen[shipmentId] = true;
        shipmentIds.push(shipmentId);
      }
    });
  });

  if(!shipmentIds.length){
    showAlertDanger("No shipment_ids found in selected rows. Calculate first and ensure a rate is selected.");
    setStatus("No shipment_ids found.", "err");
    return;
  }

  // Param `shipment_ids` as CSV (URL-encoded)
  var url = baseUrl;
  url += (url.indexOf("?") >= 0 ? "&" : "?") + "shipment_ids=" + encodeURIComponent(shipmentIds.join(","));

  window.open(url, "_blank", "noopener");
  setStatus("Opening print page for " + shipmentIds.length + " shipment(s)...", "ok");
}


  function renderRatesSelect(cell, ratesByGroup, entryId){
    cell.innerHTML = "";

    // wrapper for all groups
    var wrap = document.createElement("div");
    wrap.className = "ffda-rates-wrap";

    // ratesByGroup is an object: { key: {group:{label,...}, rates:[...]}, ... }
    var keys = [];
    if(ratesByGroup && typeof ratesByGroup === "object"){
      keys = Object.keys(ratesByGroup);
    }

    if(!keys.length){
      cell.innerHTML = '<span class="text-muted">-</span>';
      return;
    }

    keys.forEach(function(k){
      var block = ratesByGroup[k] || {};
      var gmeta = block.group || {};
      var groupLabel = gmeta.label ? String(gmeta.label) : String(k);

      var ratesArr = Array.isArray(block.rates) ? block.rates : [];

      // Title line: Group label
      var title = document.createElement("div");
      title.className = "ffda-rate-group-title";
      title.textContent = groupLabel;

      // Select
      var sel = document.createElement("select");
      sel.className = "form-select form-select-sm ffda-rate-select";
      sel.setAttribute("data-entry-id", String(entryId));
      sel.setAttribute("data-rate-group", String(k));         // ✅ which group is this select for
      sel.setAttribute("data-group-label", groupLabel);       // optional, for UI/logging

      var opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "Select rate…";
      sel.appendChild(opt0);

      var firstSelectableIndex = -1;

      ratesArr.forEach(function(r, idx){
        var o = document.createElement("option");

        var rateId = (r && r.id) ? String(r.id) : String(idx);
        var label  = (r && r.label) ? String(r.label) : ("Rate " + (idx+1));
        var shipmentId = (r && r.shipment_id) ? String(r.shipment_id) : "";

        o.value = rateId;
        o.textContent = label;

        if(shipmentId){
          o.setAttribute("data-shipment-id", shipmentId);
        }

        sel.appendChild(o);

        if(firstSelectableIndex === -1){
          firstSelectableIndex = sel.options.length - 1;
        }
      });

      // Status area under THIS select
      var st = document.createElement("div");
      st.className = "ffda-buy-status mt-1";

      // Group container
      // Address toggle link
  var addrLink = document.createElement("a");
  addrLink.href = "#";
  addrLink.className = "ffda-addr-toggle small d-inline-block mt-1";
  addrLink.textContent = "Show addresses";
  addrLink.setAttribute("data-open", "0");

  // Addresses block (hidden by default)
  var addrWrap = document.createElement("div");
  addrWrap.className = "ffda-addr-block small";
  addrWrap.style.display = "none";

  // Read combined addresses from response
  var addr = block.addresses || {};
  var fromCombined = (addr.from && addr.from.combined) ? String(addr.from.combined) : "";
  var toCombined   = (addr.to && addr.to.combined) ? String(addr.to.combined) : "";

  addrWrap.innerHTML =
    '<div><strong>From:</strong> ' + (fromCombined ? fromCombined : '-') + '</div>' +
    '<div><strong>To:</strong> '   + (toCombined ? toCombined : '-') + '</div>';

  // Group container
  var groupBox = document.createElement("div");
  groupBox.className = "ffda-rate-group";
  groupBox.appendChild(title);
  groupBox.appendChild(sel);
  groupBox.appendChild(addrLink);
  groupBox.appendChild(addrWrap);
  groupBox.appendChild(st);

  // Toggle handler (scoped per select)
  addrLink.addEventListener("click", function(e){
    e.preventDefault();
    var isOpen = addrLink.getAttribute("data-open") === "1";
    if(isOpen){
      addrWrap.style.display = "none";
      addrLink.textContent = "Show addresses";
      addrLink.setAttribute("data-open", "0");
    } else {
      addrWrap.style.display = "block";
      addrLink.textContent = "Hide addresses";
      addrLink.setAttribute("data-open", "1");
    }
  });


      wrap.appendChild(groupBox);

      // Auto-select first real rate if exists
      if(firstSelectableIndex > 0){
        sel.selectedIndex = firstSelectableIndex;
      } else {
        // no rates in this group
        sel.disabled = true;
        st.innerHTML = '<span class="text-muted">No rates</span>';
      }
    });

    cell.appendChild(wrap);
  }


  var printModal  = qs("#ffda_print_modal");
var printIframe = qs("#ffda_print_iframe");
var printClose  = qs("#ffda_print_close");

function openPrintModal(url){
  if(!printModal || !printIframe){ return; }

  printIframe.src = url || "about:blank";
  printModal.style.display = "flex";
  printModal.setAttribute("aria-hidden", "false");
}

function closePrintModal(){
  if(!printModal || !printIframe){ return; }

  printModal.style.display = "none";
  printModal.setAttribute("aria-hidden", "true");
  printIframe.src = "about:blank";
}

if(printClose){
  printClose.addEventListener("click", closePrintModal);
}
if(printModal){
  // click backdrop closes
  printModal.addEventListener("click", function(e){
    if(e && e.target === printModal){ closePrintModal(); }
  });
  // ESC closes
  document.addEventListener("keydown", function(e){
    if(e && e.key === "Escape" && printModal.style.display === "flex"){
      closePrintModal();
    }
  });
}



  // ---------------- Confirmation modal helpers ----------------
  var confirmModal = qs("#ffda_confirm_modal");
  var confirmTitle = qs("#ffda_confirm_title");
  var confirmText  = qs("#ffda_confirm_text");
  var confirmYes   = qs("#ffda_confirm_yes");
  var confirmNo    = qs("#ffda_confirm_no");

  function hideConfirm(){
    if(!confirmModal) return;
    confirmModal.style.display = "none";
    confirmModal.setAttribute("aria-hidden", "true");
  }

  function showConfirm(opts){
    opts = opts || {};
    return new Promise(function(resolve){
      if(!confirmModal || !confirmYes || !confirmNo || !confirmTitle || !confirmText){
        // fallback
        resolve(window.confirm((opts && opts.text) ? String(opts.text) : "Are you sure?"));
        return;
      }

      confirmTitle.textContent = (opts.title ? String(opts.title) : "Confirm");
      confirmText.textContent  = (opts.text ? String(opts.text) : "Are you sure?");

      var decided = false;

      function cleanup(){
        confirmYes.removeEventListener("click", onYes);
        confirmNo.removeEventListener("click", onNo);
        document.removeEventListener("keydown", onKey);
        confirmModal.removeEventListener("click", onBackdropClick);
      }

      function done(val){
        if(decided) return;
        decided = true;
        cleanup();
        hideConfirm();
        resolve(val);
      }

      function onYes(){ done(true); }
      function onNo(){ done(false); }
      function onKey(e){
        if(!e) return;
        if(e.key === "Escape"){ done(false); }
        if(e.key === "Enter"){ done(true); }
      }
      function onBackdropClick(e){
        if(e && e.target === confirmModal){ done(false); }
      }

      confirmYes.addEventListener("click", onYes);
      confirmNo.addEventListener("click", onNo);
      document.addEventListener("keydown", onKey);
      confirmModal.addEventListener("click", onBackdropClick);

      confirmModal.style.display = "flex";
      confirmModal.setAttribute("aria-hidden", "false");
    });
  }

  // ---------------- Actions ----------------

  async function runVerifySequential(){
    clearAlert();

    var checked = getSelectedCheckboxes();
    if(checked.length === 0){
      setStatus("Select entries to enable actions.", "ok");
      return;
    }

    var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
    var nonce   = wrap.getAttribute("data-ajax-nonce") || "";
    if(!ajaxUrl || !nonce){
      setStatus("Missing AJAX config.", "err");
      return;
    }

    setButtonsEnabled(false);
    setStatus("Verifying addresses: 0 / " + checked.length, "ok");

    for(var i=0; i<checked.length; i++){
      var cb = checked[i];
      var row = cb.closest("tr");
      if(!row){ continue; }

      var entryId = parseInt(row.getAttribute("data-entry-id") || cb.value || "0", 10);
      var addrCell = row.querySelector(".ffda-mailing-addr-cell");

      if(!entryId || !addrCell){
        setStatus("Verifying addresses: " + (i+1) + " / " + checked.length, "ok");
        await sleep(500);
        continue;
      }

      var badge = ensureVerifyBadge(addrCell);
      badge.className = "ep-verify-status";
      badge.textContent = "… verifying";

      try{
        var form = new FormData();
        form.append("action", "{$ajaxVerify}");
        form.append("nonce", nonce);
        form.append("entry_id", String(entryId));

        var resp = await fetch(ajaxUrl, { method:"POST", credentials:"same-origin", body:form });
        var json = null;
        try { json = await resp.json(); } catch(e){ json = null; }

        var payload = (json && typeof json.ok !== "undefined") ? json
                    : (json && json.success && json.data) ? json.data
                    : (json && json.data) ? json.data
                    : { ok:false, message:"Bad response" };

        renderVerifyResult(addrCell, payload);

      } catch(err){
        renderVerifyResult(addrCell, { ok:false, message:(err && err.message) ? err.message : "Network error" });
      }

      setStatus("Verifying addresses: " + (i+1) + " / " + checked.length, "ok");
      await sleep(500);
    }

    setStatus("Verify complete: " + checked.length + " entries.", "ok");
    refresh();
  }

  async function runCalculateSequential(){
    clearAlert();

    var checked = getSelectedCheckboxes();
    if(checked.length === 0){
      setStatus("Select entries to enable actions.", "ok");
      return;
    }

    var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
    var nonce   = wrap.getAttribute("data-ajax-nonce") || "";
    if(!ajaxUrl || !nonce){
      setStatus("Missing AJAX config.", "err");
      return;
    }

    setButtonsEnabled(false);
    setStatus("Calculating rates: 0 / " + checked.length, "ok");

    for(var i=0; i<checked.length; i++){
      var cb = checked[i];
      var row = cb.closest("tr");
      if(!row){ continue; }

      var entryId = parseInt(row.getAttribute("data-entry-id") || cb.value || "0", 10);
      var ratesCell = row.querySelector(".ffda-rates-cell");

      if(!entryId || !ratesCell){
        setStatus("Calculating rates: " + (i+1) + " / " + checked.length, "ok");
        await sleep(500);
        continue;
      }

      ratesCell.innerHTML = '<span class="text-muted">… calculating</span>';

      try{
        var form = new FormData();
        form.append("action", "{$ajaxCalc}");
        form.append("nonce", nonce);
        form.append("entry_id", String(entryId));
        form.append("group", getGroupValue());

        var carrier = (carrierSel && carrierSel.value) ? String(carrierSel.value) : "";
        form.append("carrier", carrier);

        var resp = await fetch(ajaxUrl, { method:"POST", credentials:"same-origin", body:form });
        var json = null;
        try { json = await resp.json(); } catch(e){ json = null; }

        var payload = (json && typeof json.ok !== "undefined") ? json
                    : (json && json.success && json.data) ? json.data
                    : (json && json.data) ? json.data
                    : { ok:false, message:"Bad response" };

        var ok = !!payload.ok;
        var msg = payload.message ? String(payload.message) : "";

        var ratesObj = (payload && payload.rates && typeof payload.rates === "object") ? payload.rates : null;

        if(ok && ratesObj && Object.keys(ratesObj).length){
          renderRatesSelect(ratesCell, ratesObj, entryId);
        } else {
          if(!ok){
            showAlertDanger(msg || "Rates not found.");
            setStatus(msg || "Rates not found.", "err");
          }
          ratesCell.innerHTML = '<span class="text-muted">-</span>';
        }


      } catch(err){
        var m = (err && err.message) ? err.message : "Network error";
        showAlertDanger(m);
        setStatus(m, "err");
        ratesCell.innerHTML = '<span class="text-muted">-</span>';
      }

      setStatus("Calculating rates: " + (i+1) + " / " + checked.length, "ok");
      await sleep(500);
    }

    setStatus("Calculate complete: " + checked.length + " entries.", "ok");
    refresh();
  }

  async function runBuySequential(){
    clearAlert();

    var selects = qsa(".ffda-rate-select", wrap).filter(function(s){
      return s && !s.disabled;
    });
    if(selects.length === 0){
      showAlertDanger("No rate selects found. Click Calculate first.");
      setStatus("No rate selects found.", "err");
      return;
    }

    // ✅ CONFIRM
    var confirmed = await showConfirm({
      title: "Confirm Buy",
      text: "Buy labels for " + selects.length + " rate(s)? This will charge/purchase labels."
    });
    if(!confirmed){
      setStatus("Buy cancelled.", "ok");
      return;
    }

    var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
    var nonce   = wrap.getAttribute("data-ajax-nonce") || "";
    if(!ajaxUrl || !nonce){
      setStatus("Missing AJAX config.", "err");
      return;
    }

    setButtonsEnabled(false);
    setStatus("Buying labels: 0 / " + selects.length, "ok");

    for(var i=0; i<selects.length; i++){
      var sel = selects[i];

      if(sel.disabled){
        setStatus("Buying labels: " + (i+1) + " / " + selects.length, "ok");
        await sleep(500);
        continue;
      }

      var rateId = (sel.value || "").trim();
      var entryId = parseInt(sel.getAttribute("data-entry-id") || "0", 10);

      var shipmentId = "";
      if(sel && sel.selectedOptions && sel.selectedOptions[0]){
        shipmentId = sel.selectedOptions[0].getAttribute("data-shipment-id") || "";
      }

      var groupBox = sel.closest(".ffda-rate-group");
      var statusBox = groupBox ? groupBox.querySelector(".ffda-buy-status") : null;

      function setInlineMsg(type, text){
        if(!statusBox){ return; }
        statusBox.innerHTML =
          "<span class=\\"ffda-inline-msg " + String(type) + "\\">" +
            String(text) +
          "</span>";
      }

      if(!entryId){
        setInlineMsg("err", "Bad entry id");
        setStatus("Buying labels: " + (i+1) + " / " + selects.length, "ok");
        await sleep(500);
        continue;
      }

      if(!rateId){
        setInlineMsg("err", "Rate is not chosen");
        setStatus("Buying labels: " + (i+1) + " / " + selects.length, "ok");
        await sleep(500);
        continue;
      }

      if(!shipmentId){
        setInlineMsg("err", "Missing shipment_id");
        setStatus("Buying labels: " + (i+1) + " / " + selects.length, "ok");
        await sleep(500);
        continue;
      }

      setInlineMsg("ok", "… buying");

      try{

        var rateGroup = sel.getAttribute("data-rate-group") || "";

        var form = new FormData();
        form.append("action", "{$ajaxBuy}");
        form.append("nonce", nonce);
        form.append("entry_id", String(entryId));
        form.append("shipment_id", String(shipmentId));
        form.append("rate_id", rateId);
        form.append("group", getGroupValue());
        form.append("rate_group", rateGroup);

        var resp = await fetch(ajaxUrl, { method:"POST", credentials:"same-origin", body:form });
        var json = null;
        try { json = await resp.json(); } catch(e){ json = null; }

        var payload = (json && typeof json.ok !== "undefined") ? json
                    : (json && json.success && json.data) ? json.data
                    : (json && json.data) ? json.data
                    : { ok:false, message:"Bad response" };

        var ok = !!payload.ok;
        var msg = payload.message ? String(payload.message) : (ok ? "Bought" : "Buy failed");

        if(ok){
          sel.disabled = true;
          setInlineMsg("ok", msg);

          // ✅ If updated_tracking_code=true -> add badge in Features cell
          var updatedTracking = false;
          if(payload && typeof payload.updated_tracking_code !== "undefined"){
            updatedTracking = !!payload.updated_tracking_code;
          } else if(payload && payload.data && typeof payload.data.updated_tracking_code !== "undefined"){
            updatedTracking = !!payload.data.updated_tracking_code;
          }

          if(updatedTracking){
            var row = sel.closest("tr");
            var featuresCell = row ? row.querySelector(".ffda-features-cell") : null;
            addNewTrackingBadge(featuresCell);
          }

          var trackingUrl = "";
          if(payload && payload.data && payload.data.tracking_url){
            trackingUrl = String(payload.data.tracking_url);
          } else if(payload && payload.tracking_url){
            trackingUrl = String(payload.tracking_url);
          }

          if(trackingUrl && statusBox){
            var linkWrap = document.createElement("div");
            linkWrap.className = "mt-1";

            var a = document.createElement("a");
            a.href = trackingUrl;
            a.target = "_blank";
            a.rel = "noopener";
            a.textContent = "Tracking url"; // ✅ requested text

            linkWrap.appendChild(a);
            statusBox.appendChild(linkWrap);
          }


        } else {
          setInlineMsg("err", msg);
        }

      } catch(err){
        setInlineMsg("err", (err && err.message) ? err.message : "Network error");
      }

      setStatus("Buying labels: " + (i+1) + " / " + selects.length, "ok");
      await sleep(500);
    }

    setStatus("Buy complete.", "ok");
    refresh();
  }

  function hasLabelPrintedBadge(featuresCell){
    if(!featuresCell) return false;
    return !!featuresCell.querySelector(".label-printed-tag");
  }

  function addLabelPrintedBadge(featuresCell){
      if(!featuresCell) return;
      if(hasLabelPrintedBadge(featuresCell)) return;

      var b = document.createElement("span");
      b.className = "badge text-bg-secondary label-printed-tag";
      b.textContent = "label-printed";
      featuresCell.appendChild(b);
    }

    function hasNewTrackingBadge(featuresCell){
    if(!featuresCell) return false;
    return !!featuresCell.querySelector(".new-tracking-code-tag");
  }

  function addNewTrackingBadge(featuresCell){
    if(!featuresCell) return;
    if(hasNewTrackingBadge(featuresCell)) return;

    var b = document.createElement("span");
    b.className = "badge text-bg-warning new-tracking-code-tag"; // ✅ orange (bootstrap warning)
    b.textContent = "new-tracking-code";
    featuresCell.appendChild(b);
  }


  function getGroupValue(){
    // from the filter select in the form
    var el = qs('select[name="group"]', wrap) || qs('select[name="group"]');
    var v = el && el.value ? String(el.value) : "";
    return (v === "two") ? "two" : "one";
  }


  async function runCompleteSequential(){
    clearAlert();

    var checked = getSelectedCheckboxes();
    if(checked.length === 0){
      setStatus("Select entries to enable actions.", "ok");
      return;
    }

    // ✅ CONFIRM
    var confirmed = await showConfirm({
      title: "Confirm Complete",
      text: "Complete " + checked.length + " selected entr" + (checked.length === 1 ? "y" : "ies") + "? This will add label-printed."
    });
    if(!confirmed){
      setStatus("Complete cancelled.", "ok");
      return;
    }

    var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
    var nonce   = wrap.getAttribute("data-ajax-nonce") || "";
    if(!ajaxUrl || !nonce){
      setStatus("Missing AJAX config.", "err");
      return;
    }

    setButtonsEnabled(false);
    setStatus("Completing: 0 / " + checked.length, "ok");

    for(var i=0; i<checked.length; i++){
      var cb = checked[i];
      var row = cb.closest("tr");
      if(!row){ continue; }

      var entryId = parseInt(row.getAttribute("data-entry-id") || cb.value || "0", 10);
      var featuresCell = row.querySelector(".ffda-features-cell");

      if(!entryId || !featuresCell){
        setStatus("Completing: " + (i+1) + " / " + checked.length, "ok");
        await sleep(500);
        continue;
      }

      if(hasLabelPrintedBadge(featuresCell)){
        setStatus("Completing: " + (i+1) + " / " + checked.length, "ok");
        await sleep(500);
        continue;
      }

      try{
        var form = new FormData();
        form.append("action", "{$ajaxComplete}");
        form.append("nonce", nonce);
        form.append("entry_id", String(entryId));

        var resp = await fetch(ajaxUrl, { method:"POST", credentials:"same-origin", body:form });
        var json = null;
        try { json = await resp.json(); } catch(e){ json = null; }

        var payload = (json && typeof json.ok !== "undefined") ? json
                    : (json && json.success && json.data) ? json.data
                    : (json && json.data) ? json.data
                    : { ok:false, message:"Bad response" };

        if(payload && payload.ok){
          addLabelPrintedBadge(featuresCell);
        } else {
          var msg = (payload && payload.message) ? String(payload.message) : "Complete failed";
          showAlertDanger(msg);
        }

      } catch(err){
        showAlertDanger((err && err.message) ? err.message : "Network error");
      }

      setStatus("Completing: " + (i+1) + " / " + checked.length, "ok");
      await sleep(500);
    }

    setStatus("Complete finished.", "ok");
    refresh();
  }

  // Click handlers
  buttons.forEach(function(btn){
    btn.addEventListener("click", function(){
      var actionName = btn.getAttribute("data-ffda-action") || "";
      if(!actionName){ return; }

      if(actionName === "verify"){ runVerifySequential(); return; }
      if(actionName === "calculate"){ runCalculateSequential(); return; }
      if(actionName === "buy"){ runBuySequential(); return; }
      if(actionName === "complete"){ runCompleteSequential(); return; }
      if(actionName === "print"){ runPrintSelected(); return; }

      showAlertDanger("Action not implemented: " + actionName);
    });
  });

  refresh();
})();
JS;

        echo '<script>' . $js . '</script>';
    }

    private static function render_selection_ui(): void {
        echo '<div class="d-flex flex-wrap gap-2 align-items-center mt-2">';
        echo '<div class="text-muted small">Selected: <strong id="ffda_selected_count">0</strong></div>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" id="ffda_clear_selection">Clear selection</button>';
        echo '</div>';

        echo '<script>
        (function(){
            function qs(sel, root){ return (root||document).querySelector(sel); }
            function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

            var selectAll = qs("#ffda_select_all");
            var checks = function(){ return qsa(".ffda-entry-check"); };
            var countEl = qs("#ffda_selected_count");
            var clearBtn = qs("#ffda_clear_selection");

            if(!selectAll || !countEl){ return; }

            function updateCount(){
                var c = checks().filter(function(cb){ return cb.checked; }).length;
                countEl.textContent = String(c);

                var all = checks();
                if(all.length === 0){
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    return;
                }
                var checked = all.filter(function(cb){ return cb.checked; }).length;
                if(checked === 0){
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if(checked === all.length){
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }

            selectAll.addEventListener("change", function(){
                var desired = !!selectAll.checked;
                checks().forEach(function(cb){ cb.checked = desired; });
                updateCount();
            });

            document.addEventListener("change", function(e){
                if(e && e.target && e.target.classList && e.target.classList.contains("ffda-entry-check")){
                    updateCount();
                }
            });

            if(clearBtn){
                clearBtn.addEventListener("click", function(){
                    checks().forEach(function(cb){ cb.checked = false; });
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    updateCount();
                });
            }

            updateCount();
        })();
        </script>';
    }

    private static function render_pagination(int $page, int $total_pages, array $state): void {
        if ($total_pages <= 1) { return; }

        $base_args = [
            'photo' => $state['photo'] ?? 'without',
            'group' => $state['group'] ?? 'one',
            'processing' => (int) ($state['processing'] ?? 0),
        ];

        $make_url = function(int $p) use ($base_args): string {
            $args = $base_args;
            $args['pg'] = $p;
            return add_query_arg($args, self::current_url_without(['pg']));
        };

        echo '<nav aria-label="Pagination" class="mt-3">';
        echo '<ul class="pagination pagination-sm justify-content-center flex-wrap">';

        $prev_disabled = ($page <= 1) ? ' disabled' : '';
        echo '<li class="page-item' . $prev_disabled . '">';
        echo '<a class="page-link" href="' . esc_url($page <= 1 ? '#' : $make_url($page - 1)) . '">&laquo;</a>';
        echo '</li>';

        $window = 3;
        $start = max(1, $page - $window);
        $end   = min($total_pages, $page + $window);

        if ($start > 1) {
            echo '<li class="page-item"><a class="page-link" href="' . esc_url($make_url(1)) . '">1</a></li>';
            if ($start > 2) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }

        for ($p = $start; $p <= $end; $p++) {
            $active = ($p === $page) ? ' active' : '';
            echo '<li class="page-item' . $active . '">';
            echo '<a class="page-link" href="' . esc_url($make_url($p)) . '">' . esc_html((string)$p) . '</a>';
            echo '</li>';
        }

        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="' . esc_url($make_url($total_pages)) . '">' . esc_html((string)$total_pages) . '</a></li>';
        }

        $next_disabled = ($page >= $total_pages) ? ' disabled' : '';
        echo '<li class="page-item' . $next_disabled . '">';
        echo '<a class="page-link" href="' . esc_url($page >= $total_pages ? '#' : $make_url($page + 1)) . '">&raquo;</a>';
        echo '</li>';

        echo '</ul>';
        echo '</nav>';
    }

    // ---------------- AJAX HANDLERS (STUBS) ----------------

    public static function ajax_verify_address_for_entry(): void {
        if ( ! is_user_logged_in() ) { wp_send_json(['ok' => false, 'message' => 'Not logged in.']); }
        //if ( ! current_user_can('manage_options') ) { wp_send_json(['ok' => false, 'message' => 'Insufficient permissions.']); }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) { wp_send_json(['ok' => false, 'message' => 'Bad nonce.']); }

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        if ($entry_id <= 0) { wp_send_json(['ok' => false, 'message' => 'Missing entry_id.']); }

        $entryHelper = new FrmEasypostEntryHelper();
        $verifyRes = $entryHelper->verifyEntryAddress($entry_id);

        if( ($verifyRes['status'] ?? '') === 'verified' ) {
          $verified = true;
          $message = 'Address verified';
        } else {
          $verified = false;
          $message = $verifyRes['message'] ?? 'Address not verified';
        }

        $res = [
            'ok' => $verified,
            'verified' => $verified,
            'message' => $message,
            'normalized_address' => $verifyRes['normalized']['full_address'] ?? '',
            'input_address' => $verifyRes['normalized']['input_address'] ?? '',
            'matched_addresses' => $verifyRes['normalized']['matched'] ?? '',
            'data' => $verifyRes,
        ];

        wp_send_json( $res );
    }

    public static function ajax_calculate_rates_for_entry(): void {
        if ( ! is_user_logged_in() ) { wp_send_json(['ok' => false, 'message' => 'Not logged in.', 'rates' => []]); }
        //if ( ! current_user_can('manage_options') ) { wp_send_json(['ok' => false, 'message' => 'Insufficient permissions.', 'rates' => []]); }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) { wp_send_json(['ok' => false, 'message' => 'Bad nonce.', 'rates' => []]); }

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        if ($entry_id <= 0) { wp_send_json(['ok' => false, 'message' => 'Missing entry_id.', 'rates' => []]); }

        $carrier = isset($_POST['carrier']) ? sanitize_text_field((string) $_POST['carrier']) : '';
        if ( ! in_array($carrier, ['fedex', 'usps'], true) ) {
            $carrier = '';
        }

        $group = isset($_POST['group']) ? sanitize_text_field((string) $_POST['group']) : 'one';
        $group = ($group === 'two') ? 'two' : 'one';

        // List of label types to creates, FRM_EP_LABEL_DIRECTION_TYPES from /references.php
        $labelTypes = [];
        switch ($group) {
            case 'one':
                $labelTypes = ['service_client'];
                break;
            case 'two':
            default:
                $labelTypes = ['national_passport', 'service_client'];
                break;
        }

        $entryHelper = new FrmEasypostEntryHelper();

        $labelGroups = [];
        foreach( $labelTypes as $type ) {

          $group = FRM_EP_LABEL_DIRECTION_TYPES[$type] ?? null;
          if ( $group ) {

            $filters = [ 
              'carrier' => $carrier, 
              'group' => $group 
            ];

            if( $type == 'national_passport' ) {
              // For national_passport, only USPS is allowed
              $filters['carrier'] = 'usps';
            }
            
            $ratesData = $entryHelper->calculateRatesByEntry(
              $entry_id,
              $filters,
              [ 'rate' => 'asc' ],
              $type
            );

            $labelGroups[ $type ] = [
              'group' => $group,
              'entry_id' => $ratesData['entry_id'],
              'rates' => $ratesData['rates'],
              'addresses' => $ratesData['addresses'],
            ];

          }

        }

        // national_passport should show just USPS rates
        if ( isset( $labelGroups['national_passport'] ) ) {
          $npRates = $labelGroups['national_passport']['rates'] ?? [];
          $npRatesFiltered = [];
          foreach ( $npRates as $rate ) {
              if ( 
                  isset( $rate['carrier'] ) && 
                  strtolower( $rate['carrier'] ) === 'usps' 
                  ) {
                  $npRatesFiltered[] = $rate;
              }
          }
          //$labelGroups['national_passport']['rates'] = $npRatesFiltered;    
        }

        wp_send_json(['ok' => true, 'message' => 'Rates calculated.', 'rates' => $labelGroups]);
    }

    public static function ajax_buy_label_for_entry(): void {

        if ( ! is_user_logged_in() ) { wp_send_json(['ok' => false, 'message' => 'Not logged in.']); }
        //if ( ! current_user_can('manage_options') ) { wp_send_json(['ok' => false, 'message' => 'Insufficient permissions.']); }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) { wp_send_json(['ok' => false, 'message' => 'Bad nonce.']); }

        $entry_id    = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $shipment_id = isset($_POST['shipment_id']) ? sanitize_text_field((string) $_POST['shipment_id']) : '';
        $rate_id     = isset($_POST['rate_id']) ? sanitize_text_field((string) $_POST['rate_id']) : '';
        $rate_group = isset($_POST['rate_group']) ? sanitize_text_field((string) $_POST['rate_group']) : '';

        if ($entry_id <= 0) { wp_send_json(['ok' => false, 'message' => 'Missing entry_id.']); }
        if ($shipment_id === '') { wp_send_json(['ok' => false, 'message' => 'Missing shipment_id.']); }
        if ($rate_id === '') { wp_send_json(['ok' => false, 'message' => 'Rate is not chosen']); }

        if (!$shipment_id || !$rate_id) wp_send_json_error(['message' => 'Missing shipment or rate.']);

        try {
            $shipmentApi = new FrmEasypostShipmentApi();

            $label = $shipmentApi->buyLabel($shipment_id, $rate_id);

            if (empty($label) || !is_array($label)) {
                wp_send_json_error(['ok' => false, 'message' => 'Empty response from label API.']);
            }

            $shipmentHelper = new FrmEasypostShipmentHelper();
            $shipmentData = $shipmentHelper->updateShipmentApi($shipment_id );

            // Update tracking url of the entry
            $updatedTrackingCode = false;
            if( $rate_group === 'service_client' ) {
              $entryHelper = new FrmEasypostEntryHelper();
              $entryHelper->updateEntryShipmentData( $entry_id, $shipmentData ?? [], $label );

              $updatedTrackingCode = true;
            }

            wp_send_json_success([
                'ok' => true,
                'message' => 'Label bought successfully.',
                'data'   => $shipmentData,
                'updated_tracking_code' => $updatedTrackingCode,
            ]);

        } catch (Throwable $e) {
            wp_send_json_error(['ok'=> false, 'message' => 'API error: '.$e->getMessage()]);
        }
    }

    public static function ajax_set_complete_entry(): void {
        if ( ! is_user_logged_in() ) { wp_send_json(['ok' => false, 'message' => 'Not logged in.']); }
        //if ( ! current_user_can('manage_options') ) { wp_send_json(['ok' => false, 'message' => 'Insufficient permissions.']); }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) { wp_send_json(['ok' => false, 'message' => 'Bad nonce.']); }

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        if ($entry_id <= 0) { wp_send_json(['ok' => false, 'message' => 'Missing entry_id.']); }

        self::frm_add_label_printed_status($entry_id, self::FIELD_FLAGS);

        wp_send_json(['ok' => true, 'message' => 'Completed']);
    }

    public static function frm_add_label_printed_status(int $entry_id, int $field_id): bool {
      global $wpdb;

      $table = $wpdb->prefix . 'frm_item_metas';

      $row = $wpdb->get_row(
          $wpdb->prepare(
              "SELECT id, meta_value
              FROM {$table}
              WHERE item_id = %d AND field_id = %d
              LIMIT 1",
              $entry_id,
              $field_id
          )
      );

      $values = [];

      if ($row && ! empty($row->meta_value)) {
          $unserialized = maybe_unserialize($row->meta_value);
          if (is_array($unserialized)) {
              $values = $unserialized;
          }
      }

      if (in_array('label-printed', $values, true)) {
          return true;
      }

      $values[] = 'label-printed';
      $serialized = maybe_serialize(array_values($values));

      if ($row) {
          $wpdb->update(
              $table,
              [ 'meta_value' => $serialized ],
              [ 'id' => (int) $row->id ],
              [ '%s' ],
              [ '%d' ]
          );
      } else {
          $wpdb->insert(
              $table,
              [
                  'item_id'    => $entry_id,
                  'field_id'   => $field_id,
                  'meta_value' => $serialized,
                  'created_at' => current_time('mysql'),
              ],
              [ '%d', '%d', '%s', '%s' ]
          );
      }

      return true;
    }

    public static function ajax_update_address_for_entry(): void {
      if ( ! is_user_logged_in() ) { wp_send_json(['ok' => false, 'message' => 'Not logged in.'], 200); }
  
      $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
      if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) { wp_send_json(['ok' => false, 'message' => 'Bad nonce.'], 200); }
  
      $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
      if ($entry_id <= 0) { wp_send_json(['ok' => false, 'message' => 'Missing entry_id.'], 200); }
  
      $addr = [
          'firstname' => sanitize_text_field((string) ($_POST['firstname'] ?? '')),
          'lastname'  => sanitize_text_field((string) ($_POST['lastname'] ?? '')),
          'phone'     => sanitize_text_field((string) ($_POST['phone'] ?? '')),
          'street1'   => sanitize_text_field((string) ($_POST['street1'] ?? '')),
          'street2'   => sanitize_text_field((string) ($_POST['street2'] ?? '')),
          'city'      => sanitize_text_field((string) ($_POST['city'] ?? '')),
          'state'     => sanitize_text_field((string) ($_POST['state'] ?? '')),
          'zip'       => sanitize_text_field((string) ($_POST['zip'] ?? '')),
          'country'   => 'US',
      ];
  
      // Build combined + name (like your example)
      $addr['name'] = trim($addr['firstname'] . ' ' . $addr['lastname']);
      $addr['combined'] = trim($addr['street1'] . ', ' . $addr['city'] . ' ' . $addr['state'] . ' ' . $addr['zip']);
  
      // ✅ TODO: save it wherever you want (Formidable field, custom table, etc.)
      // Example placeholder:
      // $entryHelper = new FrmEasypostEntryHelper();
      // $entryHelper->updateEntryAddress($entry_id, $addr);
  
      wp_send_json(['ok' => true, 'message' => 'Address updated.', 'address' => $addr], 200);
  }
  

    // ---------------- HELPERS ----------------

    private static function processing_label(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') { return ''; }
        if ($raw === (string) self::PT_STANDARD) { return 'Standard'; }
        if ($raw === (string) self::PT_EXPEDITED) { return 'Expedited'; }
        if ($raw === (string) self::PT_RUSH) { return 'Rush'; }
        return $raw;
    }

    private static function meta_val(array $metas, int $field_id): string {
        if (!isset($metas[$field_id])) { return ''; }
        $v = $metas[$field_id];
        if (is_array($v)) { $v = $v[0] ?? ''; }
        return is_scalar($v) ? (string) $v : '';
    }

    private static function alert(string $msg, string $type = 'info'): string {
        return '<div class="alert alert-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
    }

    private static function current_url_without(array $remove_keys): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $parsed = wp_parse_url($url);

        $path = $parsed['path'] ?? '';
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        foreach ($remove_keys as $k) { unset($query[$k]); }

        $base = home_url($path);
        if (!empty($query)) { $base = add_query_arg($query, $base); }
        return $base;
    }
}

FrmEasypostLabelsMassbuyListShortcode::init();
