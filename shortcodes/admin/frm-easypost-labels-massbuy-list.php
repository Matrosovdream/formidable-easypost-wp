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
    private const FIELD_NATIONAL_TRACK  = 344;
    private const FIELD_PROCESSING_TIME = 211;
    private const FIELD_MAILING_ADDRESS = 37;

    /** Processing time values (field 211) */
    // Dev
    private const PT_STANDARD  = 145;
    private const PT_EXPEDITED = 195;
    private const PT_RUSH      = 395;

    /** Default pagination */
    private const DEFAULT_PER_PAGE = 20;

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
    private const AJAX_ACTION          = 'ffda_massbuy_labels_action';
    private const AJAX_VERIFY_ADDRESS  = 'ffda_massbuy_entry_verify_address';
    private const NONCE_KEY            = 'ffda_massbuy_labels_nonce';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_bootstrap_assets']);

        // Mass actions (logged-in)
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_handle_mass_action']);

        // Verify address per entry (logged-in)
        add_action('wp_ajax_' . self::AJAX_VERIFY_ADDRESS, [__CLASS__, 'ajax_verify_address_for_entry']);
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
        $entryHelper = new FrmEasypostEntryHelper();

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
                'value'    => $processing, // 0 means no filter
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

        echo '<div class="ffda-massbuy-list" data-ajax-url="' . esc_attr($ajax_url) . '" data-ajax-nonce="' . esc_attr($nonce) . '">';

        self::render_filters([
            'photo' => $photo_mode,
            'group' => $group,
            'processing' => $processing,
        ]);

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
        echo '<table class="table table-sm table-striped align-middle">';
        echo '<thead class="table-light">';
        echo '<tr>';
        echo '<th style="width:48px;" class="text-center">';
        echo '<input type="checkbox" class="form-check-input" id="ffda_select_all" aria-label="Select all">';
        echo '</th>';
        echo '<th style="width:40px;">ID</th>';
        echo '<th style="width:180px;">Created</th>';
        echo '<th style="width:220px;">Service (12)</th>';
        echo '<th style="width:140px;">Photos</th>';
        echo '<th style="width:200px;">Mailing address</th>';
        echo '<th style="width:180px;">Processing time</th>';
        echo '<th style="width:260px;">Tracking number (344)</th>';
        echo '<th style="width:110px;" class="text-end"></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="8" class="text-center text-muted py-4">No results for this filter.</td></tr>';
        } else {
            foreach ($items as $entry) {
                if ( ! is_object($entry) || empty($entry->id) ) { continue; }

                $id = (int) $entry->id;
                $created = isset($entry->created_at) ? (string) $entry->created_at : '';
                $created = $created !== '' ? date('Y-m-d H:i', strtotime($created)) : '';

                $metas = isset($entry->metas) && is_array($entry->metas) ? $entry->metas : [];

                $service_val  = self::meta_val($metas, self::FIELD_SERVICE);
                $track_344    = self::meta_val($metas, self::FIELD_NATIONAL_TRACK);
                $proc_211     = self::meta_val($metas, self::FIELD_PROCESSING_TIME);
                $mailing_addr = $entryHelper->getEntryAddressFields($id);

                $photo_badge = ($photo_mode === 'with')
                    ? '<span class="badge text-bg-success">photo-done</span>'
                    : '<span class="badge text-bg-secondary">photo-no</span>';

                $proc_label = self::processing_label($proc_211);
                $proc_badge = $proc_label !== ''
                    ? '<span class="badge text-bg-info">' . esc_html($proc_label) . '</span>'
                    : '<span class="text-muted">-</span>';

                $order_url = home_url('/orders/entry/' . $id . '/?order=' . $id);

                echo '<tr data-entry-id="' . esc_attr((string)$id) . '">';
                echo '<td class="text-center">';
                echo '<input type="checkbox" class="form-check-input ffda-entry-check" name="ffda_entries[]" value="' . esc_attr((string)$id) . '" aria-label="Select entry ' . esc_attr((string)$id) . '">';
                echo '</td>';
                echo '<td><strong>' . esc_html((string) $id) . '</strong></td>';
                echo '<td class="small">' . esc_html($created ?: '-') . '</td>';
                echo '<td>' . esc_html($service_val ?: '-') . '</td>';
                echo '<td>' . $photo_badge . '</td>';

                // Mailing address cell (we add a class so JS can inject verify badge)
                echo '<td class="ffda-mailing-addr-cell">' . esc_html($mailing_addr['combined'] ?: '-') . '</td>';

                echo '<td>' . $proc_badge . '</td>';
                echo '<td>' . esc_html($track_344 ?: '-') . '</td>';
                echo '<td class="text-end"><a class="btn btn-outline-primary btn-sm" href="' . esc_url($order_url) . '" target="_blank" rel="noopener">Open</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div class="alert alert-info mt-3 mb-2">';
        echo '<div class="fw-semibold mb-1">Form type groups mapping</div>';
        echo '<div class="small">';

        echo '<div class="mb-2"><span class="badge text-bg-secondary me-2">DS11</span>';
        echo esc_html(implode(', ', self::SERVICES_ONE_LABEL));
        echo '</div>';

        echo '<div><span class="badge text-bg-secondary me-2">DS82</span>';
        echo esc_html(implode(', ', self::SERVICES_TWO_LABELS));
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '</div>';

        self::render_selection_ui();
        self::render_pagination($current_page, $total_pages, [
            'photo' => $photo_mode,
            'group' => $group,
            'processing' => $processing,
        ]);

        // Small CSS for verify badges
        echo '<style>
            .ep-verify-status {
    font-size: 12px;
    display: block;
    width: fit-content;
    border: 1px solid green;
    border-radius: 4px;
    padding: 3px;
}
            .ep-verify-status.ok{color:#0a7a2b}
            .ep-verify-status.err{    color: #b00020;
    border: 1px solid #b00020;}
        </style>';

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * FILTERS + MASS ACTION BUTTONS (LEFT of Apply)
     */
    private static function render_filters(array $state): void {
        $photo = $state['photo'] ?? 'without';
        $group = $state['group'] ?? 'one';
        $processing = (int) ($state['processing'] ?? 0);

        $action = self::current_url_without(['pg']); // reset page on apply

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

        // Buttons row: LEFT = carrier + actions, RIGHT = Apply
        echo '<div class="col-12 d-flex flex-wrap justify-content-between align-items-end gap-2 mt-2">';

        // LEFT controls
        echo '<div class="d-flex flex-wrap align-items-end gap-2">';
        echo '<div>';
        echo '<label class="form-label mb-1">Select Carrier</label>';
        echo '<select class="form-select form-select-sm" id="ffda_carrier_select" style="min-width:180px;">';
        echo '<option value="fedex">FedEx</option>';
        echo '<option value="usps">USPS</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="d-flex flex-wrap gap-2 align-items-end" style="padding-top:22px;">';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="verify">Verify</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="calculate" disabled>Calculate</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="buy" disabled>Buy</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="print" disabled>Print</button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm ffda-mass-btn" data-ffda-action="complete" disabled>Complete</button>';
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

        // JS: enable/disable mass buttons based on selection + AJAX handlers + sequential verify
        echo '<script>
        (function(){
            function qs(sel, root){ return (root||document).querySelector(sel); }
            function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

            var wrap = qs(".ffda-massbuy-list");
            if(!wrap){ return; }

            function getSelectedIds(){
                return qsa(".ffda-entry-check", wrap)
                    .filter(function(cb){ return cb && cb.checked; })
                    .map(function(cb){ return parseInt(cb.value, 10); })
                    .filter(function(v){ return v && v > 0; });
            }

            var buttons = qsa(".ffda-mass-btn", wrap);
            var carrierSel = qs("#ffda_carrier_select", wrap);
            var statusEl = qs("#ffda_mass_action_status", wrap);

            function setButtonsEnabled(enabled){
                buttons.forEach(function(btn){
                    var actionName = btn.getAttribute("data-ffda-action") || "";
                    // Verify is allowed regardless of selection (your spec says: go through every row)
                    if(actionName === "verify"){
                        btn.disabled = false;
                        return;
                    }
                    btn.disabled = !enabled;
                });
            }

            function setStatus(msg, type){
                if(!statusEl){ return; }
                statusEl.textContent = msg || "";
                statusEl.className = "";

                // text color only
                if(type === "ok"){ statusEl.classList.add("text-success"); }
                else if(type === "err"){ statusEl.classList.add("text-danger"); }
                else { statusEl.classList.add("text-success"); } // default green
            }

            function refresh(){
                var ids = getSelectedIds();
                setButtonsEnabled(ids.length > 0);
                if(ids.length === 0){
                    setStatus("Select entries to enable actions.", "ok");
                } else {
                    setStatus("", "ok");
                }
            }

            // Watch selection changes
            document.addEventListener("change", function(e){
                if(!e || !e.target){ return; }
                if(e.target.classList && e.target.classList.contains("ffda-entry-check")){
                    refresh();
                }
                if(e.target && e.target.id === "ffda_select_all"){
                    setTimeout(refresh, 0);
                }
            });

            function postMassAction(actionName){
                var ids = getSelectedIds();
                if(ids.length === 0){
                    setStatus("No entries selected.", "err");
                    return;
                }

                var ajaxUrl = wrap.getAttribute("data-ajax-url") || "";
                var nonce = wrap.getAttribute("data-ajax-nonce") || "";
                if(!ajaxUrl || !nonce){
                    setStatus("Missing AJAX config.", "err");
                    return;
                }

                var carrier = carrierSel ? carrierSel.value : "fedex";

                setButtonsEnabled(false);
                setStatus("Running " + actionName + " for " + ids.length + " entries…", "ok");

                var form = new FormData();
                form.append("action", "' . esc_js(self::AJAX_ACTION) . '");
                form.append("nonce", nonce);
                form.append("do", actionName);
                form.append("carrier", carrier);
                ids.forEach(function(id){ form.append("entry_ids[]", String(id)); });

                fetch(ajaxUrl, {
                    method: "POST",
                    credentials: "same-origin",
                    body: form
                })
                .then(function(r){ return r.json().catch(function(){ return null; }); })
                .then(function(data){
                    if(!data || !data.success){
                        var msg = (data && data.data && data.data.message) ? data.data.message : "Request failed.";
                        setStatus(msg, "err");
                    } else {
                        var msgOk = (data.data && data.data.message) ? data.data.message : "Done.";
                        setStatus(msgOk, "ok");
                    }
                })
                .catch(function(err){
                    setStatus("Network error: " + (err && err.message ? err.message : "unknown"), "err");
                })
                .finally(function(){
                    refresh();
                });
            }

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
                
            }
async function runVerifySequential(){
  // ✅ ONLY rows where checkbox is checked
  var checked = qsa(".ffda-entry-check:checked", wrap);

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

  // lock non-verify actions
  setButtonsEnabled(false);
  setStatus("Verifying addresses: 0 / " + checked.length, "ok");

  for (var i = 0; i < checked.length; i++) {
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
      form.append("action", "ffda_massbuy_entry_verify_address");
      form.append("nonce", nonce);
      form.append("entry_id", String(entryId));

      var resp = await fetch(ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: form
      });

      var json = null;
      try { json = await resp.json(); } catch(e){ json = null; }

      var payload = null;
      if(json && typeof json.ok !== "undefined"){
        payload = json;                 // { ok, verify_result, message }
      } else if(json && json.success && json.data){
        payload = json.data;            // WP wrapper
      } else if(json && json.data){
        payload = json.data;
      } else {
        payload = { ok:false, verify_result:false, message:"Bad response" };
      }

      renderVerifyResult(addrCell, payload);

    } catch(err){
      renderVerifyResult(addrCell, {
        ok:false,
        verify_result:false,
        message:(err && err.message) ? err.message : "Network error"
      });
    }

    setStatus("Verifying addresses: " + (i+1) + " / " + checked.length, "ok");
    await sleep(500); // 0.5 sec delay
  }

  setStatus("Verify complete: " + checked.length + " entries.", "ok");
  refresh(); // re-enable buttons based on current selection
}


            // Click handlers
            buttons.forEach(function(btn){
                btn.addEventListener("click", function(){
                    var actionName = btn.getAttribute("data-ffda-action") || "";
                    if(!actionName){ return; }

                    if(actionName === "verify"){
                        runVerifySequential();
                        return;
                    }

                    postMassAction(actionName);
                });
            });

            refresh();
        })();
        </script>';
    }

    private static function render_selection_ui(): void {
        echo '<div class="d-flex flex-wrap gap-2 align-items-center mt-2">';
        echo '<div class="text-muted small">';
        echo 'Selected: <strong id="ffda_selected_count">0</strong>';
        echo '</div>';
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

    /**
     * Verify address endpoint:
     * POST: action=ffda_massbuy_entry_verify_address, nonce, entry_id
     * Returns: { ok: bool, verify_result: bool, message: string }
     *
     * NOTE: This will call helper method if it exists. Rename $method below to match your helper.
     */
    public static function ajax_verify_address_for_entry(): void {

        //$nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';

        $entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;

        try {

            $helper = new FrmEasypostEntryHelper();
            $out = $helper->verifyEntryAddress($entry_id);

            // Normalize
            if (is_array($out)) {
                $ok = isset($out['ok']) ? (bool) $out['ok'] : true;
                $msg = isset($out['message']) ? (string) $out['message'] : '';
                wp_send_json(['ok' => $ok, 'message' => $msg]);
            }

            // Unknown
            wp_send_json([
                'ok' => false,
                'message' => 'Unexpected verify response type.',
            ]);

        } catch (\Throwable $e) {
            wp_send_json(['ok' => false, 'verify_result' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Mass actions endpoint for selected entries (calculate/buy/print/complete).
     */
    public static function ajax_handle_mass_action(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'Not logged in.']);
        }
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
        if ( ! wp_verify_nonce($nonce, self::NONCE_KEY) ) {
            wp_send_json_error(['message' => 'Bad nonce.']);
        }

        if ( ! class_exists('FrmEasypostLabelHelper') ) {
            wp_send_json_error(['message' => 'FrmEasypostLabelHelper not loaded.']);
        }

        $do = isset($_POST['do']) ? sanitize_text_field((string) $_POST['do']) : '';
        $allowed = ['calculate','buy','print','complete'];
        if ( ! in_array($do, $allowed, true) ) {
            wp_send_json_error(['message' => 'Unknown action.']);
        }

        $carrier = isset($_POST['carrier']) ? sanitize_text_field((string) $_POST['carrier']) : 'fedex';
        $carrier = in_array($carrier, ['fedex','usps'], true) ? $carrier : 'fedex';

        $entry_ids = isset($_POST['entry_ids']) ? (array) $_POST['entry_ids'] : [];
        $ids = [];
        foreach ($entry_ids as $id) {
            $id = (int) $id;
            if ($id > 0) { $ids[] = $id; }
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            wp_send_json_error(['message' => 'No entries selected.']);
        }

        $helper = new FrmEasypostLabelHelper();

        // Change names to match your helper methods
        $method_map = [
            'calculate'  => 'massCalculateEntries',
            'buy'        => 'massBuyLabels',
            'print'      => 'massPrintLabels',
            'complete'   => 'massCompleteEntries',
        ];

        $method = $method_map[$do] ?? '';
        if ($method === '' || ! method_exists($helper, $method)) {
            wp_send_json_error([
                'message' => 'Action "' . $do . '" is not implemented in FrmEasypostLabelHelper (missing method ' . $method . ').'
            ]);
        }

        try {
            $out = $helper->{$method}($ids, $carrier);

            $msg = 'Done: ' . $do . ' for ' . count($ids) . ' entries (' . strtoupper($carrier) . ').';
            if (is_array($out) && isset($out['message']) && is_string($out['message'])) {
                $msg = $out['message'];
            }

            wp_send_json_success([
                'message' => $msg,
                'result'  => $out,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    private static function processing_label(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') { return ''; }
        if ($raw === (string) self::PT_STANDARD) { return 'Standard'; }
        if ($raw === (string) self::PT_EXPEDITED) { return 'Expedited'; }
        if ($raw === (string) self::PT_RUSH) { return 'Rush'; }
        return $raw;
    }

    private static function meta_val(array $metas, int $field_id): string {
        if (!isset($metas[$field_id])) {
            return '';
        }
        $v = $metas[$field_id];

        if (is_array($v)) {
            $v = $v[0] ?? '';
        }
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

        foreach ($remove_keys as $k) {
            unset($query[$k]);
        }

        $base = home_url($path);
        if (!empty($query)) {
            $base = add_query_arg($query, $base);
        }
        return $base;
    }
}

FrmEasypostLabelsMassbuyListShortcode::init();
