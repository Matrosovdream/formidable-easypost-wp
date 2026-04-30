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

    // Predefined packages for USPS + FedEx, pulled from EasyPost metadata (cached 24h).
    $carrierHelper = new FrmEasypostCarrierHelper();
    $carriersForUi = $carrierHelper->getPredefinedPackages(['usps', 'fedex']);

    // Pass data to JS
    wp_localize_script('ep-easypost-popup', 'epPopup', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ep_easypost_nonce'),
        'prefill' => [
            'label_message1' => $label1,
            'label_message2' => $label2,
        ],
        'carriers' => $carriersForUi,
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

            <!-- Extra actions -->
            <div class="ep-group ep-group-full" style="grid-column: 1 / -1; width:100%;">
              <div class="ep-legend-wrap">
                <div class="ep-legend">Extra actions</div>
              </div>

              <div class="ep-row-1" style="width:100%;">
                <div class="ep-field" style="width:100%; display:flex; gap:12px; align-items:flex-start;">
                  <div>
                    <button id="ep-fill-closest" class="ep-btn ep-btn-primary" type="button" disabled>
                      National Passport
                    </button>
                    <button id="ep-fill-passport-service" class="ep-btn ep-btn-primary" type="button" disabled>
                      Service/Client
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Parcel -->
            <div class="ep-group">
              <div class="ep-legend-wrap"><div class="ep-legend">Parcel</div></div>
              <div class="ep-row">
                <div class="ep-field">
                  <label>Package</label>
                  <select id="ep-parcel-package">
                    <option value="">Custom dimensions</option>
                  </select>
                </div>
                <div class="ep-field"><label>Weight (Oz)</label><input id="ep-parcel-weight" type="number" step="0.1" value="1"></div>
              </div>
              <div class="ep-row ep-parcel-dims">
                <div class="ep-field"><label>Length (in)</label><input id="ep-parcel-length" type="number" step="0.01" value=""></div>
                <div class="ep-field"><label>Width (in)</label> <input id="ep-parcel-width"  type="number" step="0.01" value=""></div>
                <div class="ep-field"><label>Height (in)</label><input id="ep-parcel-height" type="number" step="0.01" value=""></div>
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

    <!-- Inline JS: closest/passport buttons enable + click -> fill + calc; datepicker unchanged -->
    <script>
    jQuery(function($) {
        var $dateInput          = $('#ep-label-date');
        var $closestBtn         = $('#ep-fill-closest');
        var $passportServiceBtn = $('#ep-fill-passport-service');

        var epClosestAddress    = null;
        var epEntryAddress      = null;
        var epPassportService   = null;

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
                    if (v && String(v).indexOf(String(a.zip)) !== -1) {
                        $sel.val(v);
                        found = true;
                        return false;
                    }
                });
                if (found) $sel.trigger('change');
            }

            // Notify any listeners bound to the inputs
            $('#ep-' + prefix + '-name, #ep-' + prefix + '-phone, #ep-' + prefix + '-street1, #ep-' + prefix + '-street2, #ep-' + prefix + '-city, #ep-' + prefix + '-state, #ep-' + prefix + '-zip')
              .trigger('change')
              .trigger('input');
        }

        function epTriggerCalculateSoon() {
            setTimeout(function() {
                var $btn = $('#ep-ep-calc');
                if ($btn.length) $btn.trigger('click');
            }, 500);
        }

        function epUpdateExtraButtonsState() {
            var hasClosest  = !!(epClosestAddress && (epClosestAddress.street1 || epClosestAddress.zip || epClosestAddress.name));
            var hasEntry    = !!(epEntryAddress && (epEntryAddress.street1 || epEntryAddress.zip || epEntryAddress.name));
            var hasPassport = !!(epPassportService && (epPassportService.street1 || epPassportService.zip || epPassportService.name));

            // Closest: needs closest + entry
            if ($closestBtn.length) {
                $closestBtn.prop('disabled', !(hasClosest && hasEntry));
            }

            // Passport service: needs passport_service + entry
            if ($passportServiceBtn.length) {
                $passportServiceBtn.prop('disabled', !(hasPassport && hasEntry));
            }
        }

        // When opening popup: fetch new shape + enable/disable the buttons
        $(document).on('click', '.ep-open-easypost', function() {
            var entryId = parseInt($(this).data('entry-id'), 10);
            if (!entryId) return;

            epClosestAddress  = null;
            epEntryAddress    = null;
            epPassportService = null;
            epUpdateExtraButtonsState();

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
                var data = (res && res.success && res.data) ? res.data : {};

                epClosestAddress  = (data && data.closest_address) ? data.closest_address : null;
                epEntryAddress    = (data && data.entry_address) ? data.entry_address : null;
                epPassportService = (data && data.passport_service) ? data.passport_service : null;

                epUpdateExtraButtonsState();
            }).fail(function() {
                epClosestAddress  = null;
                epEntryAddress    = null;
                epPassportService = null;
                epUpdateExtraButtonsState();
            });
        });

        // Closest button:
        // 1) From = entry_address
        // 2) To   = closest_address
        // 3) Calculate after 0.5s
        $(document).on('click', '#ep-fill-closest', function() {
            if (!$closestBtn.length || $closestBtn.prop('disabled')) return;
            if (!epEntryAddress || !epClosestAddress) return;

            epSetBlock('from', epEntryAddress);
            epSetBlock('to', epClosestAddress);

            epTriggerCalculateSoon();
        });

        // Passport service button:
        // 1) From = passport_service
        // 2) To   = entry_address
        // 3) Calculate after 0.5s
        $(document).on('click', '#ep-fill-passport-service', function() {
            if (!$passportServiceBtn.length || $passportServiceBtn.prop('disabled')) return;
            if (!epPassportService || !epEntryAddress) return;

            epSetBlock('from', epPassportService);
            epSetBlock('to', epEntryAddress);

            epTriggerCalculateSoon();
        });

        // Datepicker (unchanged)
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
