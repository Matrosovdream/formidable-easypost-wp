<?php

add_action('init', function () {
    add_shortcode('entry-verify-address', 'ep_short_entry_verify_address');
});

/**
 * [entry-verify-address entry=ID client="firstId,lastId" address="addr1Id,addr2Id,cityId,stateId,zipId"]
 */
function ep_short_entry_verify_address($atts) {
    $atts = shortcode_atts([
        'entry'   => '',
        'client'  => '', // "firstFieldId,lastFieldId"
        'address' => '', // "addr1Id,addr2Id,cityId,stateId,zipId"
    ], $atts, 'entry-verify-address');

    $entry_id = (int) trim((string) $atts['entry']);
    if ($entry_id <= 0) {
        return '<div class="ep-entry-error">Missing or invalid <code>entry</code> param.</div>';
    }

    // Split helpers
    $toParts = function (string $csv): array {
        if ($csv === '') return [];
        $parts = array_map('trim', explode(',', $csv));
        foreach ($parts as &$p) { if ($p === null) { $p = ''; } }
        return $parts;
    };

    $clientParts  = $toParts((string) $atts['client']);   // [0]=firstId, [1]=lastId
    $addressParts = $toParts((string) $atts['address']);  // [0..4]=addr1Id,addr2Id,cityId,stateId,zipId
    $pick = fn(array $arr, int $i): string => isset($arr[$i]) ? (string)$arr[$i] : '';

    // logical name => Formidable field ID
    $fields = [
        'firstname' => trim($pick($clientParts, 0)),
        'lastname'  => trim($pick($clientParts, 1)),
        'address1'  => $pick($addressParts, 0),
        'address2'  => $pick($addressParts, 1),
        'city'      => $pick($addressParts, 2),
        'state'     => $pick($addressParts, 3),
        'zip'       => $pick($addressParts, 4),
    ];

    if (!class_exists('FrmEntry')) {
        return '<div class="ep-entry-error">Formidable Forms not found (<code>FrmEntry</code> missing).</div>';
    }

    $entry = \FrmEntry::getOne($entry_id, true);
    if (!$entry) {
        return '<div class="ep-entry-error">Entry not found for ID #' . esc_html((string)$entry_id) . '.</div>';
    }

    $entry_metas = is_array($entry->metas ?? null) ? $entry->metas : [];

    // Resolve values by field IDs
    $fieldValues = [];
    foreach ($fields as $key => $fieldId) {
        $fieldValues[$key] = ($fieldId !== '' && isset($entry_metas[$fieldId])) ? (string)$entry_metas[$fieldId] : '';
    }
    $fieldValues['name'] = trim(($fieldValues['firstname'] ?? '') . ' ' . ($fieldValues['lastname'] ?? ''));

    // ---------- CSS ----------
    wp_register_style('ep-entry-verify-style', false);
    wp_enqueue_style('ep-entry-verify-style');
    wp_add_inline_style('ep-entry-verify-style', <<<CSS
.epv-group{border:1px solid #eee;border-radius:10px;padding:14px;margin:10px 0}
.epv-legend-wrap{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;gap:10px}
.epv-legend{font-weight:700;margin:0}
.epv-verify-bar{display:flex;align-items:center;gap:8px}
.epv-verify-btn{border:0;border-radius:8px;padding:6px 10px;background:#f3f4f6;cursor:pointer}
.epv-toggle-btn{border:0;border-radius:8px;padding:6px 10px;background:#e5e7eb;cursor:pointer}
.epv-verify-status{font-size:12px}
.epv-verify-status.ok{color:#0a7a2b}
.epv-verify-status.err{color:#b00020}
.epv-normalized{display:none;margin-top:8px;font-size:12px;color:#374151;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;padding:8px}
.epv-form{display:none;margin-top:10px}
.epv-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.epv-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.epv-field{display:flex;flex-direction:column;margin-bottom:10px}
.epv-field label{font-size:12px;color:#555;margin-bottom:4px}
.epv-field input{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
CSS);

    // ---------- JS ----------
    wp_register_script('ep-entry-verify-script', false, ['jquery'], null, true);
    wp_enqueue_script('ep-entry-verify-script');
    wp_localize_script('ep-entry-verify-script', 'epEntryVerify', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ep_entry_verify_nonce'),
    ]);
    wp_add_inline_script('ep-entry-verify-script', <<<'JS'
(function($){
  const st = { ajaxUrl: epEntryVerify.ajaxUrl, nonce: epEntryVerify.nonce };

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
      $el.text('').hide();
      return;
    }
    const line1 = normalized.delivery_line_1 || '';
    const last  = normalized.last_line || '';
    const out   = [line1, last].filter(Boolean).join(', ');
    if (out) { $el.text('Normalized address: ' + out).show(); }
    else { $el.text('').hide(); }
  }

  function readFields(){
    return {
      name:    $('#epv-name').val(),
      street1: $('#epv-street1').val(),
      street2: $('#epv-street2').val(),
      city:    $('#epv-city').val(),
      state:   $('#epv-state').val(),
      zipcode: $('#epv-zip').val(),
      country: 'US'
    };
  }

  function verifyNow(){
    setVerifyStatus('#epv-verify-status', true, 'Verifying…');
    setNormalized('#epv-normalized', null);

    $.post(st.ajaxUrl, {
      action: 'entry_verify_address',
      _ajax_nonce: st.nonce,
      address: JSON.stringify(readFields()),
      strict: 1
    }).done(function(resp){
      if (resp && resp.success && resp.data && resp.data.status === 'verified') {
        setVerifyStatus('#epv-verify-status', true, '');
        setNormalized('#epv-normalized', resp.data.normalized || null);
      } else {
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Verification failed.';
        setVerifyStatus('#epv-verify-status', false, msg);
        setNormalized('#epv-normalized', null);
      }
    }).fail(function(){
      setVerifyStatus('#epv-verify-status', false, 'Server error.');
      setNormalized('#epv-normalized', null);
    });
  }

  // Keep small Verify button in header
  $(document).on('click', '#epv-verify', function(e){
    e.preventDefault();
    verifyNow();
  });

  // Toggle show/hide fields
  $(document).on('click', '#epv-toggle-fields', function(e){
    e.preventDefault();
    const $form = $('.epv-form');
    $form.slideToggle(150);
    const showing = $form.is(':visible');
    $(this).text(showing ? 'Hide fields' : 'Show fields');
  });

})(jQuery);
JS);

    // ---------- HTML ----------
    ob_start(); ?>
    <div class="epv-group">
      <div class="epv-legend-wrap">
        <div style="display:flex;flex-direction:column;gap:6px;">
          <div class="epv-legend">Address</div>
          <div id="epv-normalized" class="epv-normalized"></div>
        </div>
        <div class="epv-verify-bar">
          <button id="epv-verify" class="epv-verify-btn" type="button">Verify</button>
          <button id="epv-toggle-fields" class="epv-toggle-btn" type="button">Show fields</button>
          <span id="epv-verify-status" class="epv-verify-status"></span>
        </div>
      </div>

      <!-- Hidden by default; toggled by "Show fields" -->
      <div class="epv-form">
        <div class="epv-row">
          <div class="epv-field"><label>Name</label><input id="epv-name" value="<?php echo esc_attr($fieldValues['name'] ?? ''); ?>"></div>
          <div class="epv-field"><label>Phone</label><input id="epv-phone" value=""></div>
        </div>

        <div class="epv-row">
          <div class="epv-field"><label>Street 1</label><input id="epv-street1" value="<?php echo esc_attr($fieldValues['address1'] ?? ''); ?>"></div>
          <div class="epv-field"><label>Street 2</label><input id="epv-street2" value="<?php echo esc_attr($fieldValues['address2'] ?? ''); ?>"></div>
        </div>

        <div class="epv-row-3">
          <div class="epv-field"><label>City</label><input id="epv-city" value="<?php echo esc_attr($fieldValues['city'] ?? ''); ?>"></div>
          <div class="epv-field"><label>State</label><input id="epv-state" value="<?php echo esc_attr($fieldValues['state'] ?? ''); ?>"></div>
          <div class="epv-field"><label>ZIP</label><input id="epv-zip" value="<?php echo esc_attr($fieldValues['zip'] ?? ''); ?>"></div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ========= AJAX: Verify (Smarty) ========= */
add_action('wp_ajax_entry_verify_address', 'ep_ajax_entry_verify_address');
add_action('wp_ajax_nopriv_entry_verify_address', 'ep_ajax_entry_verify_address');
function ep_ajax_entry_verify_address() {
    check_ajax_referer('ep_entry_verify_nonce');

    $raw = wp_unslash($_POST['address'] ?? '');
    $decoded = json_decode($raw, true);
    $strict  = true;

    if (!is_array($decoded)) {
        wp_send_json_error(['message' => 'Invalid address payload.']);
    }

    $addressData = [
        'name'    => sanitize_text_field($decoded['name']    ?? ''),
        'street1' => sanitize_text_field($decoded['street1'] ?? ''),
        'street2' => sanitize_text_field($decoded['street2'] ?? ''),
        'city'    => sanitize_text_field($decoded['city']    ?? ''),
        'state'   => sanitize_text_field($decoded['state']   ?? ''),
        'zipcode' => sanitize_text_field($decoded['zipcode'] ?? ''),
        'country' => 'US',
    ];

    try {
        if (!class_exists('FrmSmartyApi')) {
            wp_send_json_error(['message' => 'FrmSmartyApi not found.']);
        }
        $smartyApi = new FrmSmartyApi();
        $resp = $smartyApi->verifyAddress($addressData, $strict);

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
        wp_send_json_error(['message' => 'Verify error: ' . $e->getMessage()]);
    }
}
