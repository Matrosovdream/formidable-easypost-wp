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
    if ($rendered) {
        return '';
    }
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

    // ---------- HTML ----------
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

    <!-- Inline JS: jQuery UI datepicker + date tags + ISO helper -->
    <script>
    jQuery(function($) {
        var $dateInput = $('#ep-label-date');

        // Init jQuery UI datepicker, format mm/dd/yyyy, min tomorrow
        if ($dateInput.length) {
            $dateInput.datepicker({
                dateFormat: 'mm/dd/yy', // shows as mm/dd/yyyy
                minDate: 1 // tomorrow
            });
        }

        // Global helper: convert mm/dd/yyyy -> yyyy-mm-dd for AJAX
        window.epGetLabelDateIso = function() {
            var v = $dateInput.val() ? $dateInput.val().trim() : '';
            if (!v) {
                return '';
            }

            var parts = v.split('/');
            if (parts.length !== 3) {
                return v; // fallback if unexpected
            }

            var mm = parts[0].padStart(2, '0');
            var dd = parts[1].padStart(2, '0');
            var yyyy = parts[2];

            return yyyy + '-' + mm + '-' + dd; // YYYY-MM-DD
        };

        // Date tags: 0..10
        $('.ep-date-tag').on('click', function() {
            if (!$dateInput.length) return;

            var add = parseInt($(this).data('add'), 10);
            if (isNaN(add)) {
                add = 0;
            }

            // If 0 clicked → clear date
            if (add === 0) {
                $dateInput.val('');
                return;
            }

            // For 1..10 → today + N days
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
