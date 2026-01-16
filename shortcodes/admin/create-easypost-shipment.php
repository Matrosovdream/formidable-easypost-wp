<?php
add_action('init', function () {
    add_shortcode('easypost_label_popup', 'ep_short_easypost_label_popup');
    add_shortcode('easypost_label_button', 'ep_short_easypost_label_button');
});

/**
 * BUTTON SHORTCODE
 * Usage: [easypost_label_button entry=1550 button-text="Create label"]
 */
function ep_short_easypost_label_button($atts) {
    $atts = shortcode_atts([
        'entry'       => '',
        'button-text' => 'Create label',
    ], $atts, 'easypost_label_button');

    $entry = trim((string)$atts['entry']);
    if ($entry === '') {
        return '<span class="ep-button-error">Missing entry param.</span>';
    }

    $text = esc_html($atts['button-text']);

    // Many buttons per page is fine (we delegate JS events)
    return '<button class="ep-btn ep-btn-primary ep-open-easypost" type="button" data-entry-id="' . esc_attr($entry) . '">' . $text . '</button>';
}

/**
 * POPUP SHORTCODE — render the modal ONCE (no entry param)
 * Usage: [easypost_label_popup]
 */
function ep_short_easypost_label_popup($atts) {
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    // Prefill label message defaults from options
    $opts   = get_option('frm_easypost', []);
    $label1 = isset($opts['label_message1']) ? (string)$opts['label_message1'] : '';
    $label2 = isset($opts['label_message2']) ? (string)$opts['label_message2'] : '';

    // Register + enqueue (scoped to this render)
    wp_enqueue_style('ep-easypost-popup', FRM_EAP_BASE_PATH . 'assets/css/easypost-label-popup.css?time=' . time());
    wp_enqueue_script('ep-easypost-popup', FRM_EAP_BASE_PATH . 'assets/js/easypost-label-popup.js?time=' . time(), ['jquery'], null, true);

    // jQuery UI Datepicker (WP has this script)
    wp_enqueue_script('jquery-ui-datepicker');

    // Simple jQuery UI base theme (or replace with your own bundled CSS)
    wp_enqueue_style(
        'jquery-ui-base',
        'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
        [],
        '1.13.2'
    );

    // Pass data to JS
    wp_localize_script('ep-easypost-popup', 'epPopup', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ep_easypost_nonce'),
        'prefill' => [
            'label_message1' => $label1,
            'label_message2' => $label2,
        ],
    ]);

    ob_start(); ?>
    <div id="ep-ep-modal" aria-hidden="true">
      <div id="ep-ep-dialog" role="dialog" aria-modal="true" aria-labelledby="ep-ep-title">
        <div id="ep-ep-header">
          <h3 id="ep-ep-title">EasyPost — Create Label</h3>
          <button id="ep-ep-close" aria-label="Close">×</button>
        </div>
        <div id="ep-ep-body">
          <!-- Hidden fields -->
          <input type="hidden" id="ep-entry-id"   name="entry_id"    value="">
          <input type="hidden" id="shipment_id"   name="shipment_id" value="">

          <div id="ep-ep-groups">
            <!-- From Address -->
            <div class="ep-group">
              <div class="ep-legend-wrap" data-prefix="from">
                <div>
                  <div class="ep-legend">From Address</div>
                  <div id="ep-from-normalized" class="ep-normalized"></div>
                </div>
                <div class="ep-verify-bar" style="gap:8px">
                  <button id="ep-verify-from" class="ep-verify-btn" type="button">Verify</button>
                  <span id="ep-verify-from-status" class="ep-verify-status"></span>
                </div>
              </div>

              <div class="ep-row">
                <div class="ep-field"><label>Name</label><input id="ep-from-name" value=""></div>
                <div class="ep-field"><label>Phone</label><input id="ep-from-phone" value=""></div>
              </div>

              <div class="ep-row">
                <div class="ep-field"><label>Street 1</label><input id="ep-from-street1" value=""></div>
                <div class="ep-field"><label>Street 2</label><input id="ep-from-street2" value=""></div>
              </div>

              <div class="ep-row-3">
                <div class="ep-field"><label>City</label><input id="ep-from-city" value=""></div>
                <div class="ep-field"><label>State</label><input id="ep-from-state" value=""></div>
                <div class="ep-field"><label>ZIP</label><input id="ep-from-zip" value=""></div>
              </div>

              <div class="ep-row-1">
                <div class="ep-field">
                  <label>Saved Addresses</label>
                  <select id="ep-from-select" class="ep-address-select" data-target-prefix="from">
                    <option value="">Choose saved address…</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- To Address -->
            <div class="ep-group">
              <div class="ep-legend-wrap" data-prefix="to">
                <div>
                  <div class="ep-legend">To Address</div>
                  <div id="ep-to-normalized" class="ep-normalized"></div>
                </div>
                <div class="ep-verify-bar" style="gap:8px">
                  <button id="ep-verify-to" class="ep-verify-btn" type="button">Verify</button>
                  <span id="ep-verify-to-status" class="ep-verify-status"></span>
                </div>
              </div>

              <div class="ep-row">
                <div class="ep-field"><label>Name</label><input id="ep-to-name" value=""></div>
                <div class="ep-field"><label>Phone</label><input id="ep-to-phone" value=""></div>
              </div>

              <div class="ep-row">
                <div class="ep-field"><label>Street 1</label><input id="ep-to-street1" value=""></div>
                <div class="ep-field"><label>Street 2</label><input id="ep-to-street2" value=""></div>
              </div>

              <div class="ep-row-3">
                <div class="ep-field"><label>City</label><input id="ep-to-city" value=""></div>
                <div class="ep-field"><label>State</label><input id="ep-to-state" value=""></div>
                <div class="ep-field"><label>ZIP</label><input id="ep-to-zip" value=""></div>
              </div>

              <div class="ep-row-1">
                <div class="ep-field">
                  <label>Saved addresses</label>
                  <select id="ep-to-select" class="ep-address-select" data-target-prefix="to">
                    <option value="">Choose saved address…</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Ready routes (FULL WIDTH) -->
            <div class="ep-group ep-group-full" style="grid-column: 1 / -1; width:100%;">
              <div class="ep-legend-wrap">
                <div class="ep-legend">Ready routes</div>
              </div>

              <div class="ep-row-1" style="width:100%;">
                <div class="ep-field" style="width:100%;">
                  <label for="ep-ready-routes">Ready routes (saved address pairs)</label>
                  <select id="ep-ready-routes" style="width:100%;">
                    <option value="">No routes yet</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Parcel -->
            <div class="ep-group">
              <div class="ep-legend-wrap"><div class="ep-legend">Parcel</div></div>
              <div class="ep-row">
                <div class="ep-field"><label>Weight (Oz)</label><input id="ep-parcel-weight" type="number" step="0.1" value="1"></div>
                <input id="ep-parcel-length" type="hidden" value="">
                <input id="ep-parcel-width"  type="hidden" value="">
                <input id="ep-parcel-height" type="hidden" value="">
              </div>

              <div class="ep-row-1">
                <div class="ep-field">
                  <label>Label message 1</label>
                  <input id="ep-label-msg1" value="<?php echo esc_attr($label1); ?>">
                </div>
              </div>
              <div class="ep-row-1">
                <div class="ep-field">
                  <label>Label message 2</label>
                  <input id="ep-label-msg2" value="<?php echo esc_attr($label2); ?>">
                </div>
              </div>

              <!-- Label date (jQuery UI datepicker) -->
              <div class="ep-row-1">
                <div class="ep-field">
                  <label>Label date</label>
                  <input
                      id="ep-label-date"
                      name="label_date"
                      type="text"
                      class="ep-date-input"
                      placeholder="MM/DD/YYYY"
                      value="">
                </div>
              </div>

              <!-- Date tags 0..10 -->
              <div class="ep-row-1">
                <div class="ep-field">
                  <div id="ep-date-tags">
                    <?php for ($i = 0; $i <= 10; $i++): ?>
                      <span class="ep-date-tag" data-add="<?php echo $i; ?>">
                        +<?php echo $i; ?>
                      </span>
                    <?php endfor; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Rates + Actions -->
            <div class="ep-group">
              <div class="ep-legend-wrap"><div class="ep-legend">Rates</div></div>
              <div id="ep-ep-rates-wrap" class="ep-field">
                <label for="ep-ep-rates">Available Rates</label>
                <select id="ep-ep-rates">
                  <option value="">No rates yet</option>
                </select>
              </div>
              <div id="ep-ep-actions">
                <button id="ep-ep-calc" class="ep-btn ep-btn-secondary" type="button">Calculate</button>
                <button id="ep-ep-buy"  class="ep-btn ep-btn-primary"  type="button" disabled>Buy label</button>
                <div id="ep-ep-label-link" style="margin-left:auto;"></div>
              </div>

              <div id="ep-ep-status" aria-live="polite"></div>
              <div id="ep-ep-tracking-link" style="margin-top:8px;"></div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <style>
      /* Red connector arrow inside the select label (purely visual in option text) */
      .ep-route-arrow { color:#c00; font-weight:700; }
    </style>

    <!-- Inline JS: Ready routes filler + selection -> fill From/To + trigger Calculate + datepicker -->
    <script>
    jQuery(function($) {
        var $dateInput    = $('#ep-label-date');
        var $routesSelect = $('#ep-ready-routes');

        // cache addresses for current entry (so on change we can fill instantly)
        var epAddrCache = [];

        function epEsc(s) {
            return (s == null ? '' : String(s)).replace(/[&<>"']/g, function(m) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
            });
        }

        function epComposeAddress(a) {
            var parts = [];
            if (a.name)    parts.push(a.name);
            if (a.street1) parts.push(a.street1);
            if (a.street2) parts.push(a.street2);
            var cityLine = [];
            if (a.city)  cityLine.push(a.city);
            if (a.state) cityLine.push(a.state);
            if (a.zip)   cityLine.push(a.zip);
            if (cityLine.length) parts.push(cityLine.join(', '));
            if (a.country) parts.push(a.country);
            return parts.join(' — ');
        }

        function epBuildReadyRoutes(addresses) {
            var userIdx = [];
            var otherIdx = [];

            for (var i = 0; i < addresses.length; i++) {
                if (addresses[i] && addresses[i].is_user_address) userIdx.push(i);
                else otherIdx.push(i);
            }

            var routes = [];
            for (var ui = 0; ui < userIdx.length; ui++) {
                for (var oi = 0; oi < otherIdx.length; oi++) {
                    routes.push([ userIdx[ui], otherIdx[oi] ]);
                    routes.push([ otherIdx[oi], userIdx[ui] ]);
                }
            }
            return routes;
        }

        function epSetBlock(prefix, a) {
            if (!a) return;

            $('#ep-' + prefix + '-name').val(a.name || '');
            $('#ep-' + prefix + '-phone').val(a.phone || '');
            $('#ep-' + prefix + '-street1').val(a.street1 || '');
            $('#ep-' + prefix + '-street2').val(a.street2 || '');
            $('#ep-' + prefix + '-city').val(a.city || '');
            $('#ep-' + prefix + '-state').val(a.state || '');
            $('#ep-' + prefix + '-zip').val(a.zip || '');

            // Try to select the same item in "Saved Addresses" select (best-effort by zip)
            var $sel = $('#ep-' + prefix + '-select');
            if ($sel.length && a.zip) {
                var found = false;
                $sel.find('option').each(function() {
                    var v = $(this).val();
                    if (v && String(v).indexOf(String(a.zip)) !== -1) { // loose matching
                        $sel.val(v);
                        found = true;
                        return false;
                    }
                });
                if (found) {
                    // notify any listeners in your existing popup JS
                    $sel.trigger('change');
                }
            }

            // Notify any listeners bound to the inputs
            $('#ep-' + prefix + '-name, #ep-' + prefix + '-phone, #ep-' + prefix + '-street1, #ep-' + prefix + '-street2, #ep-' + prefix + '-city, #ep-' + prefix + '-state, #ep-' + prefix + '-zip')
              .trigger('change')
              .trigger('input');
        }

        function epTriggerCalculateSoon() {
            setTimeout(function() {
                var $btn = $('#ep-ep-calc');
                if ($btn.length) {
                    $btn.trigger('click');
                }
            }, 500);
        }

        // Load addresses + build ready routes when opening
        $(document).on('click', '.ep-open-easypost', function() {
            var entryId = parseInt($(this).data('entry-id'), 10);
            if (!entryId) return;

            // reset cache + select
            epAddrCache = [];
            if ($routesSelect.length) {
                $routesSelect.prop('disabled', true);
                $routesSelect.empty().append('<option value="">Loading routes…</option>');
            }

            $.ajax({
                url: (window.epPopup && epPopup.ajaxUrl) ? epPopup.ajaxUrl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'easypost_get_entry_addresses',
                    entry_id: entryId,
                    _ajax_nonce: (window.epPopup && epPopup.nonce) ? epPopup.nonce : ''
                }
            }).done(function(res) {
                var addrs = (res && res.success && res.data && Array.isArray(res.data.addresses)) ? res.data.addresses : [];
                epAddrCache = addrs;

                if (!$routesSelect.length) return;

                if (!addrs.length) {
                    $routesSelect.prop('disabled', false);
                    $routesSelect.empty().append('<option value="">No routes (no addresses)</option>');
                    return;
                }

                var pairs = epBuildReadyRoutes(addrs);

                if (!pairs.length) {
                    $routesSelect.prop('disabled', false);
                    $routesSelect.empty().append('<option value="">No ready routes</option>');
                    return;
                }

                // Option labels: "addr1    --->    addr2" (arrow red via HTML in label; note: some browsers ignore HTML in <option>)
                // We also keep a plain-text fallback that looks good everywhere.
                var opts = ['<option value="">Choose a ready route…</option>'];
                for (var p = 0; p < pairs.length; p++) {
                    var a = addrs[pairs[p][0]];
                    var b = addrs[pairs[p][1]];
                    if (!a || !b) continue;

                    var left  = epComposeAddress(a);
                    var right = epComposeAddress(b);

                    var labelPlain = left + '    --->    ' + right;
                    var val = pairs[p][0] + ',' + pairs[p][1];

                    opts.push('<option value="' + epEsc(val) + '">' + epEsc(labelPlain) + '</option>');
                }

                $routesSelect.html(opts.join(''));
                $routesSelect.prop('disabled', false);
            }).fail(function() {
                if ($routesSelect.length) {
                    $routesSelect.prop('disabled', false);
                    $routesSelect.empty().append('<option value="">Failed to load routes</option>');
                }
            });
        });

        // On Ready routes change:
        // 1) fill From/To blocks
        // 2) trigger Calculate after 0.5s
        $(document).on('change', '#ep-ready-routes', function() {
            var v = $(this).val() ? String($(this).val()).trim() : '';
            if (!v) return;

            var parts = v.split(',');
            if (parts.length !== 2) return;

            var fromIdx = parseInt(parts[0], 10);
            var toIdx   = parseInt(parts[1], 10);

            if (!Array.isArray(epAddrCache) || !epAddrCache.length) return;
            if (isNaN(fromIdx) || isNaN(toIdx)) return;

            var fromA = epAddrCache[fromIdx];
            var toA   = epAddrCache[toIdx];

            epSetBlock('from', fromA);
            epSetBlock('to', toA);

            epTriggerCalculateSoon();
        });

        // Datepicker
        if ($dateInput.length) {
            $dateInput.datepicker({
                dateFormat: 'mm/dd/yy',
                minDate: 1
            });
        }

        window.epGetLabelDateIso = function() {
            var v = $dateInput.val() ? $dateInput.val().trim() : '';
            if (!v) return '';

            var parts = v.split('/');
            if (parts.length !== 3) return v;

            var mm = parts[0].padStart(2, '0');
            var dd = parts[1].padStart(2, '0');
            var yyyy = parts[2];

            return yyyy + '-' + mm + '-' + dd;
        };

        $('.ep-date-tag').on('click', function() {
            if (!$dateInput.length) return;

            var add = parseInt($(this).data('add'), 10);
            if (isNaN(add)) add = 0;

            if (add === 0) {
                $dateInput.val('');
                return;
            }

            var d = new Date();
            d.setHours(0,0,0,0);
            d.setDate(d.getDate() + add);

            $dateInput.datepicker('setDate', d);
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
