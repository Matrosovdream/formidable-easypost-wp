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

    // Use a class (not ID) so multiple buttons can exist safely
    return '<button class="ep-btn ep-btn-primary ep-open-easypost" type="button" data-entry-id="' . esc_attr($entry) . '">' . $text . '</button>';
}

/**
 * POPUP SHORTCODE — render the modal ONCE (no entry param)
 * Usage: [easypost_label_popup]
 */
function ep_short_easypost_label_popup($atts) {
    static $rendered = false;
    if ($rendered) {
        return ''; // ensure only one modal is output
    }
    $rendered = true;

    // ---------- CSS ----------
    wp_register_style('ep-easypost-popup', false);
    wp_enqueue_style('ep-easypost-popup');
    wp_add_inline_style('ep-easypost-popup', <<<CSS
#ep-ep-modal{position:fixed;inset:0;display:none;background:rgba(0,0,0,.5);z-index:9999}
#ep-ep-modal.show{display:block}
#ep-ep-dialog{max-width:1250px;margin:6vh auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.25)}
#ep-ep-header{padding:14px 18px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
#ep-ep-title{font-weight:700;margin:0}
#ep-ep-close{cursor:pointer;border:none;background:transparent;font-size:22px;line-height:1}
#ep-ep-body{padding:18px}
#ep-ep-groups{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:900px){#ep-ep-groups{grid-template-columns:1fr}}
.ep-group{border:1px solid #eee;border-radius:10px;padding:14px}
.ep-legend-wrap{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;gap:10px}
.ep-legend{font-weight:700;margin:0}
.ep-verify-bar{display:flex;align-items:center;gap:8px}
.ep-verify-btn{border:0;border-radius:8px;padding:6px 10px;background:#f3f4f6;cursor:pointer}
.ep-verify-status{font-size:12px}
.ep-verify-status.ok{color:#0a7a2b}
.ep-verify-status.err{color:#b00020}
.ep-normalized{
  display:none; /* hidden until JS sets content */
  margin-top:8px;font-size:12px;color:#374151;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;padding:8px
}
.ep-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ep-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.ep-row-1{display:grid;grid-template-columns:1fr;gap:10px}
.ep-field{display:flex;flex-direction:column;margin-bottom:10px}
.ep-field label{font-size:12px;color:#555;margin-bottom:4px}
.ep-field input, .ep-field select{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
#ep-ep-actions{display:flex;gap:10px;align-items:center;margin-top:12px}
.ep-btn{border:0;border-radius:8px;padding:10px 14px;cursor:pointer}
.ep-btn-primary{background:#2563eb;color:#fff}
.ep-btn-secondary{background:#f3f4f6}
#ep-ep-rates-wrap{margin-top:10px}
#ep-ep-status{margin-top:10px;font-size:13px}
#ep-ep-status.ok{color:#0a7a2b}
#ep-ep-status.err{color:#b00020}
#ep-ep-buy[disabled]{opacity:.6;cursor:not-allowed;background:#9ca3af!important;color:#fff!important}
.ep-address-select { font-size: 12px; }
.post-content {
  margin-bottom: 300px;
}
CSS);

    // ---------- JS ----------
    wp_register_script('ep-easypost-popup', false, ['jquery'], null, true);
    wp_enqueue_script('ep-easypost-popup');

    wp_localize_script('ep-easypost-popup', 'epPopup', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce('ep_easypost_nonce'),
    ]);

    wp_add_inline_script('ep-easypost-popup', <<<'JS'
(function($){
  const st = { ajaxUrl: epPopup.ajaxUrl, nonce: epPopup.nonce };

  // ---- Address helpers ----
  // Include phone so "Saved address" also populates Phone field
  const FIELDS = ['name','phone','street1','street2','city','state','zip'];

  function populateAddressSelect($select, addresses) {
    $select.empty().append('<option value="">Choose saved address…</option>');
    if (!Array.isArray(addresses)) return;
    addresses.forEach(function(a, i){
      const labelParts = [];
      if (a.name)    labelParts.push(a.name);
      if (a.street1) labelParts.push(a.street1);
      let cityState = '';
      if (a.city)  cityState += a.city;
      if (a.state) cityState += (cityState ? ', ' : '') + a.state;
      if (cityState) labelParts.push(cityState);
      if (a.zip)   labelParts.push(a.zip);
      const label = labelParts.join(' — ');
      const $opt = $('<option>', { value: String(i), text: label });
      // keep full object including phone
      $opt.data('address', a);
      $select.append($opt);
    });
  }

  function applyAddressTo(prefix, addr) {
    if (!addr) return;
    FIELDS.forEach(function(key){
      const el = document.querySelector('#ep-' + prefix + '-' + key);
      if (el) el.value = addr[key] ?? '';
    });
  }

  // ---- Status helpers ----
  function setStatus(msg, ok){
    $('#ep-ep-status').text(msg).removeClass('ok err').addClass(ok ? 'ok' : 'err');
  }
  function setVerifyStatus(selector, ok, msg){
    const $el = $(selector);
    if(ok){
      $el.text('✓ Verified' + (msg ? ' — ' + msg : '')).removeClass('err').addClass('ok');
    } else {
      $el.text(msg ? ('✗ ' + msg) : '').removeClass('ok').addClass('err');
    }
  }
  function setNormalized(selector, normalized){
    const $el = $(selector);
    if (!normalized || typeof normalized !== 'object') {
      $el.text('').hide();   // keep hidden if nothing
      return;
    }
    const line1 = normalized.delivery_line_1 || '';
    const last  = normalized.last_line || '';
    const out   = [line1, last].filter(Boolean).join(', ');
    if (out) {
      $el.text('Verified address: ' + out).show();
    } else {
      $el.text('').hide();
    }
  }

  // ---- Buy button toggle (declare BEFORE any usage) ----
  function toggleBuyButton(){
    const hasShipment = ($('#shipment_id').val() || '').trim().length > 0;
    const hasRate = ($('#ep-ep-rates').val() || '').trim().length > 0;
    $('#ep-ep-buy').prop('disabled', !(hasShipment && hasRate));
  }

  // ---- Verify a prefix (from/to) via server (Smarty-backed) ----
  function verifyPrefix(prefix){
    const statusSel = (prefix === 'from') ? '#ep-verify-from-status' : '#ep-verify-to-status';
    const normSel   = (prefix === 'from') ? '#ep-from-normalized'   : '#ep-to-normalized';

    const data = {
      name:    $('#ep-' + prefix + '-name').val(),
      street1: $('#ep-' + prefix + '-street1').val(),
      street2: $('#ep-' + prefix + '-street2').val(),
      city:    $('#ep-' + prefix + '-city').val(),
      state:   $('#ep-' + prefix + '-state').val(),
      zip:     $('#ep-' + prefix + '-zip').val(),
      phone:   $('#ep-' + prefix + '-phone').val()
    };

    setVerifyStatus(statusSel, true, 'Verifying…');
    setNormalized(normSel, null);

    $.post(st.ajaxUrl, {
      action: 'easypost_verify_address',
      _ajax_nonce: st.nonce,
      address: JSON.stringify(data),
      strict: 1
    }).done(function(resp){
      if (resp && resp.success && resp.data && resp.data.status === 'verified') {
        setVerifyStatus(statusSel, true, '');
        setNormalized(normSel, resp.data.normalized || null);
      } else {
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Verification failed.';
        setVerifyStatus(statusSel, false, msg);
        setNormalized(normSel, null);
      }
    }).fail(function(){
      setVerifyStatus(statusSel, false, 'Server error.');
      setNormalized(normSel, null);
    });
  }

  // ---- Form reader ----
  function readForm(){
    return {
      entry_id:  $('#ep-entry-id').val(),
      from_address: {
        name:    $('#ep-from-name').val(),
        street1: $('#ep-from-street1').val(),
        street2: $('#ep-from-street2').val(),
        city:    $('#ep-from-city').val(),
        state:   $('#ep-from-state').val(),
        zip:     $('#ep-from-zip').val(),
        phone:   $('#ep-from-phone').val()
      },
      to_address: {
        name:    $('#ep-to-name').val(),
        street1: $('#ep-to-street1').val(),
        street2: $('#ep-to-street2').val(),
        city:    $('#ep-to-city').val(),
        state:   $('#ep-to-state').val(),
        zip:     $('#ep-to-zip').val(),
        phone:   $('#ep-to-phone').val()
      },
      parcel: {
        length: parseFloat($('#ep-parcel-length').val() || '0'),
        width:  parseFloat($('#ep-parcel-width').val()  || '0'),
        height: parseFloat($('#ep-parcel-height').val() || '0'),
        weight: parseFloat($('#ep-parcel-weight').val() || '0'),
      }
    };
  }

  // ---- Modal open/close ----
  $(document).on('click', '.ep-open-easypost', function(e){
    e.preventDefault();
    const entryId = $(this).data('entryId') || '';
    $('#ep-entry-id').val(entryId);
    $('#shipment_id').val('');
    $('#ep-ep-rates').empty().append('<option value="">No rates yet</option>');
    $('#ep-ep-title').text('EasyPost — Create Label for #' + entryId);
    $('#ep-ep-status').text('').removeClass('ok err');

    // Clear verify + normalized outputs and hide them
    $('#ep-verify-from-status, #ep-verify-to-status').text('').removeClass('ok err');
    setNormalized('#ep-from-normalized', null);
    setNormalized('#ep-to-normalized', null);

    $('#ep-ep-modal').addClass('show');
    toggleBuyButton();

    // fetch saved addresses
    $.post(st.ajaxUrl, {
      action: 'easypost_get_entry_addresses',
      _ajax_nonce: st.nonce,
      entry_id: entryId
    })
    .done(function(resp){
      const addresses = (resp && resp.success && Array.isArray(resp.data.addresses)) ? resp.data.addresses : [];
      populateAddressSelect($('#ep-from-select'), addresses);
      populateAddressSelect($('#ep-to-select'),   addresses);
    })
    .fail(function(){
      populateAddressSelect($('#ep-from-select'), []);
      populateAddressSelect($('#ep-to-select'),   []);
    });
  });

  $(document).on('click', '#ep-ep-close, #ep-ep-modal', function(e){
    if(e.target.id==='ep-ep-modal' || e.target.id==='ep-ep-close'){
      $('#ep-ep-modal').removeClass('show');
    }
  });

  // ---- Select change -> autofill + auto-verify (now includes phone) ----
  $(document).on('change', '.ep-address-select', function(){
    const $sel = $(this);
    const idx = parseInt($sel.val() || '-1', 10);
    if (idx < 0) return;

    let prefix = $sel.data('targetPrefix');
    if (!prefix) {
      const $wrap = $sel.closest('.ep-legend-wrap');
      prefix = $wrap.length ? $wrap.attr('data-prefix') : '';
    }
    if (!prefix) return;

    const addr = $sel.find('option:selected').data('address');
    if (addr) {
      applyAddressTo(prefix, addr); // fills phone too
      // Auto-verify after applying the saved address
      verifyPrefix(prefix);
    }
  });

  // ---- Calculate rates ----
  $(document).on('click', '#ep-ep-calc', function(e){
    e.preventDefault();
    setStatus('Calculating rates…', true);

    const payload = readForm();
    $.post(st.ajaxUrl, {
      action: 'easypost_calculate_rates',
      _ajax_nonce: st.nonce,
      data: JSON.stringify(payload)
    }).done(function(resp){
      const $sel = $('#ep-ep-rates');
      $sel.empty();

      if(resp && resp.success){
        $('#shipment_id').val(resp.data.general.id || '');
        const rates = Array.isArray(resp.data.rates) ? resp.data.rates : [];
        if (rates.length === 0) {
          $sel.append('<option value="">No rates found</option>');
          setStatus('No rates returned for this shipment.', false);
        } else {
          $sel.append('<option value="">Choose a rate…</option>');
          rates.forEach(function(r){
            const text = r.carrier + ' — ' + r.service + ' — ' + r.rate + ' ' + r.currency +
                        (r.est_delivery_days ? (' ('+r.est_delivery_days+' days)') : '');
            $('#ep-ep-rates').append(
              $('<option>', { value: r.id, text: text }).data('rate', r)
            );
          });
          setStatus('Rates loaded. Choose a rate to continue.', true);
        }
      } else {
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to calculate rates.';
        $sel.append('<option value="">No rates</option>');
        setStatus(msg, false);
      }
    }).fail(function(){
      $('#ep-ep-rates').empty().append('<option value="">Error loading rates</option>');
      setStatus('Server error while getting rates.', false);
    }).always(function(){
      $('#ep-ep-calc').prop('disabled', false);
      toggleBuyButton();
    });
  });

  // ---- Buy label ----
  $(document).on('click', '#ep-ep-buy', function(e){
    e.preventDefault();
    const shipmentId = $('#shipment_id').val();
    const rateId = $('#ep-ep-rates').val();
    if(!shipmentId || !rateId){ setStatus('Select a rate first.', false); return; }

    setStatus('Buying label…', true);
    $('#ep-ep-buy, #ep-ep-calc').prop('disabled', true);

    $.post(st.ajaxUrl, {
      action: 'easypost_create_label',
      _ajax_nonce: st.nonce,
      shipment_id: shipmentId,
      rate_id: rateId
    }).done(function(resp){
      if(resp && resp.success){
        setStatus('Label purchased. Tracking: ' + (resp.data.general.tracking_code || 'N/A'), true);
        if(resp.data.general.postage_label && resp.data.general.postage_label.label_url){
          $('#ep-ep-label-link').html('<a href="'+resp.data.general.postage_label.label_url+'" target="_blank" rel="noopener">Download Label</a>');
        }
      } else {
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to buy label.';
        setStatus(msg, false);
      }
    }).fail(function(){
      setStatus('Server error while buying label.', false);
    }).always(function(){
      $('#ep-ep-buy, #ep-ep-calc').prop('disabled', false);
    });
  });

  // ---- Verify From (button) ----
  $(document).on('click', '#ep-verify-from', function(e){
    e.preventDefault();
    verifyPrefix('from');
  });

  // ---- Verify To (button) ----
  $(document).on('click', '#ep-verify-to', function(e){
    e.preventDefault();
    verifyPrefix('to');
  });

  // ---- Enable Buy when rate selected (set shipment first, then toggle) ----
  $(document).on('change', '#ep-ep-rates', function(){
    const $opt = $(this).find('option:selected');
    if ($opt.length) {
      const rate = $opt.data('rate');
      if (rate && rate.shipment_id) {
        $('#shipment_id').val(rate.shipment_id);
      }
    }
    toggleBuyButton();
  });

})(jQuery);
JS);

    // ---------- HTML ----------
    ob_start(); ?>
    <div id="ep-ep-modal" aria-hidden="true">
      <div id="ep-ep-dialog" role="dialog" aria-modal="true" aria-labelledby="ep-ep-title">
        <div id="ep-ep-header">
          <h3 id="ep-ep-title">EasyPost — Create Label</h3>
          <button id="ep-ep-close" aria-label="Close">×</button>
        </div>
        <div id="ep-ep-body">
          <!-- Hidden fields (with names) -->
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
            </div>
          </div>

        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ========= AJAX: Calculate rates ========= */
add_action('wp_ajax_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
add_action('wp_ajax_nopriv_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
function ep_ajax_easypost_calculate_rates() {
    check_ajax_referer('ep_easypost_nonce');

    $raw = wp_unslash($_POST['data'] ?? '');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) wp_send_json_error(['message' => 'Invalid payload.']);

    // Get entry data and user email (if available)
    $entry = FrmEntry::getOne( $decoded['entry_id'] );
    if( isset( $entry->user_id ) && $entry->user_id ) {
      $user = get_user_by( 'id', $entry->user_id );
    }
    $userEmail = $user->user_email ?? '';

    $labelData = [
        'from_address' => [
            'name'    => sanitize_text_field($decoded['from_address']['name'] ?? ''),
            'street1' => sanitize_text_field($decoded['from_address']['street1'] ?? ''),
            'street2' => sanitize_text_field($decoded['from_address']['street2'] ?? ''),
            'city'    => sanitize_text_field($decoded['from_address']['city'] ?? ''),
            'state'   => sanitize_text_field($decoded['from_address']['state'] ?? ''),
            'zip'     => sanitize_text_field($decoded['from_address']['zip'] ?? ''),
            'phone'   => sanitize_text_field($decoded['from_address']['phone'] ?? ''),
            'country' => 'US',
        ],
        'to_address' => [
            'name'    => sanitize_text_field($decoded['to_address']['name'] ?? ''),
            'street1' => sanitize_text_field($decoded['to_address']['street1'] ?? ''),
            'street2' => sanitize_text_field($decoded['to_address']['street2'] ?? ''),
            'city'    => sanitize_text_field($decoded['to_address']['city'] ?? ''),
            'state'   => sanitize_text_field($decoded['to_address']['state'] ?? ''),
            'zip'     => sanitize_text_field($decoded['to_address']['zip'] ?? ''),
            'phone'   => sanitize_text_field($decoded['to_address']['phone'] ?? ''),
            'country' => 'US',
            'email'   => sanitize_email($userEmail),
        ],
        'parcel' => [
            'weight' => floatval($decoded['parcel']['weight'] ?? 0),
        ],
        'reference'  => sanitize_text_field($decoded['entry_id'] ?? ''),
    ];

    try {
      $carrierHelper = new FrmEasypostCarrierHelper();
      $carrierAccounts = $carrierHelper->getCarrierAccounts();

      $addresses = [
        "from_address" => $labelData['from_address'],
        "to_address"   => $labelData['to_address'],
      ];

      $rates = [];
      $shipment = null;
      foreach( $carrierAccounts as $account ) {
          $req = [
              "from_address" => $addresses['from_address'],
              "to_address"   => $addresses['to_address'],
              "parcel" => [
                  "weight" => floatval($decoded['parcel']['weight'] ?? 1),
                  "predefined_package" => $account['packages'][0],
              ],
              "carrier_accounts" => [$account['id']],
              "reference"  => sanitize_text_field($decoded['entry_id'] ?? ''),
          ];

          $shipmentApi = new FrmEasypostShipmentApi();
          $shipment = $shipmentApi->createShipment($req);

          if( isset( $shipment['rates'] ) ) {
              foreach( $shipment['rates'] as $rate ) {
                  $rate['shipment_id'] = $shipment['general']['id'];
                  $rate['package'] = $account['packages'][0];
                  $rates[] = $rate;
              }
          }
      }

      $data = [
        'general'      => $shipment['general']      ?? [],
        'from_address' => $addresses['from_address'],
        'to_address'   => $addresses['to_address'],
        'rates'        => $rates ?? []
      ];

      wp_send_json_success($data);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}

/* ========= AJAX: Buy label ========= */
add_action('wp_ajax_easypost_create_label', 'ep_ajax_easypost_create_label');
add_action('wp_ajax_nopriv_easypost_create_label', 'ep_ajax_easypost_create_label');
function ep_ajax_easypost_create_label() {
    check_ajax_referer('ep_easypost_nonce');

    $shipmentId = sanitize_text_field($_POST['shipment_id'] ?? '');
    $rateId     = sanitize_text_field($_POST['rate_id'] ?? '');
    if (!$shipmentId || !$rateId) wp_send_json_error(['message' => 'Missing shipment or rate.']);

    try {
        $shipmentApi = new FrmEasypostShipmentApi();
        $label = $shipmentApi->buyLabel($shipmentId, $rateId);

        if (empty($label) || !is_array($label)) {
            wp_send_json_error(['message' => 'Empty response from label API.']);
        }

        // Update Shipment by API
        $shipmentHelper = new FrmEasypostShipmentHelper();
        $shipmentHelper->updateShipmentsApi();

        wp_send_json_success([
            'general' => $label['general'] ?? [],
        ]);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}

/* ========= AJAX: Verify address (Smarty) ========= */
add_action('wp_ajax_easypost_verify_address', 'ep_ajax_easypost_verify_address');
add_action('wp_ajax_nopriv_easypost_verify_address', 'ep_ajax_easypost_verify_address');
function ep_ajax_easypost_verify_address() {
    check_ajax_referer('ep_easypost_nonce');

    $raw = wp_unslash($_POST['address'] ?? '');
    $decoded = json_decode($raw, true);
    $strict = isset($_POST['strict']) ? (bool)intval($_POST['strict']) : false;
    if (!is_array($decoded)) wp_send_json_error(['message' => 'Invalid address payload.']);

    $addressData = [
        'name'    => sanitize_text_field($decoded['name']    ?? ''),
        'company' => sanitize_text_field($decoded['company'] ?? ''),
        'street1' => sanitize_text_field($decoded['street1'] ?? ''),
        'street2' => sanitize_text_field($decoded['street2'] ?? ''),
        'city'    => sanitize_text_field($decoded['city']    ?? ''),
        'state'   => sanitize_text_field($decoded['state']   ?? ''),
        'zip'     => sanitize_text_field($decoded['zip']     ?? ''),
        'phone'   => sanitize_text_field($decoded['phone']   ?? ''),
        'country' => 'US',
    ];

    try {
        $smartyApi = new FrmSmartyApi();
        $resp = $smartyApi->verifyAddress($addressData, true);

        if (is_array($resp) && !empty($resp['status']) && $resp['status'] === 'verified') {
            wp_send_json_success([
                'ok'         => (int)($resp['ok'] ?? 1),
                'scope'      => (string)($resp['scope'] ?? 'US'),
                'status'     => 'verified',
                'normalized' => $resp['normalized'] ?? [],
            ]);
        }

        wp_send_json_error(['message' => 'Address not verified.']);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'Verify error: '.$e->getMessage()]);
    }
}

/* ========= AJAX: Get entry addresses ========= */
add_action('wp_ajax_easypost_get_entry_addresses', 'ep_ajax_easypost_get_entry_addresses');
add_action('wp_ajax_nopriv_easypost_get_entry_addresses', 'ep_ajax_easypost_get_entry_addresses');
function ep_ajax_easypost_get_entry_addresses() {
    check_ajax_referer('ep_easypost_nonce');

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if ($entry_id <= 0) {
        wp_send_json_error(['message' => 'Missing or invalid entry_id.']);
    }

    if (!class_exists('FrmEasypostEntryHelper')) {
        wp_send_json_error(['message' => 'Entry helper not found.']);
    }

    try {
        $model = new FrmEasypostEntryHelper();
        $addresses = $model->getEntryAddresses($entry_id);

        $out = [];
        if (is_array($addresses)) {
            foreach ($addresses as $a) {
                $out[] = [
                    'name'    => sanitize_text_field($a['name']    ?? ''),
                    'street1' => sanitize_text_field($a['street1'] ?? ''),
                    'street2' => sanitize_text_field($a['street2'] ?? ''),
                    'city'    => sanitize_text_field($a['city']    ?? ''),
                    'state'   => sanitize_text_field($a['state']   ?? ''),
                    'zip'     => sanitize_text_field($a['zip']     ?? ''),
                    'country' => sanitize_text_field($a['country'] ?? 'US'),
                    'phone'   => sanitize_text_field($a['phone']   ?? ''),
                ];
            }
        }

        wp_send_json_success(['addresses' => $out]);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'Fetch error: ' . $e->getMessage()]);
    }
}
