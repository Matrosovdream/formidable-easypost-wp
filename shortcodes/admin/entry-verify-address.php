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
        'address' => '', // "addr1Id,addr2Id,cityId,stateId,zipcodeId"
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

    // Map logical name => Formidable field ID (strings)
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

    // Resolve actual values by field IDs
    $fieldValues = [];
    foreach ($fields as $key => $fieldId) {
        if ($fieldId !== '' && isset($entry_metas[$fieldId])) {
            $fieldValues[$key] = (string) $entry_metas[$fieldId];
        } else {
            $fieldValues[$key] = '';
        }
    }
    $fieldValues['name'] = trim(($fieldValues['firstname'] ?? '') . ' ' . ($fieldValues['lastname'] ?? ''));

    // ---------- CSS (scoped/minimal) ----------
    wp_register_style('ep-entry-verify-style', false);
    wp_enqueue_style('ep-entry-verify-style');
    wp_add_inline_style('ep-entry-verify-style', <<<CSS
.epv-group{border:1px solid #eee;border-radius:10px;padding:14px;margin:10px 0}
.epv-legend-wrap{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;gap:10px}
.epv-legend{font-weight:700;margin:0}
.epv-verify-bar{display:flex;align-items:center;gap:8px}
.epv-verify-btn{border:0;border-radius:8px;padding:6px 10px;background:#f3f4f6;cursor:pointer}
.epv-verify-status{font-size:18px}
.epv-verify-status.ok{color:#0a7a2b}
.epv-verify-status.err{color:#b00020}
.epv-normalized{display:none;margin-top:8px;font-size:12px;color:#374151;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:8px;padding:8px}
.epv-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.epv-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.epv-field{display:flex;flex-direction:column;margin-bottom:10px}
.epv-field label{font-size:12px;color:#555;margin-bottom:4px}
.epv-field input{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
.epv-btn-primary {
    display: block;
    width: 40%;
    font-size: 15px;
    padding: 12px 16px;
    border: 0;
    border-radius: 8px;
    background: #2563eb;
    color: #fff;
    cursor: pointer;
    margin: 0 auto;
}
.epv-btn-primary:disabled {
  opacity:0.6;
  cursor:not-allowed;
}

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
    }else{
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

  function readFields(prefix){
    return {
      name:    $('#'+prefix+'-name').val(),
      street1: $('#'+prefix+'-street1').val(),
      street2: $('#'+prefix+'-street2').val(),
      city:    $('#'+prefix+'-city').val(),
      state:   $('#'+prefix+'-state').val(),
      zipcode: $('#'+prefix+'-zip').val(),
      country: 'US'
    };
  }

  function verifyNow(){
    const statusSel = '#epv-verify-status';
    const normSel   = '#epv-normalized';
    setVerifyStatus(statusSel, true, 'Verifying…');
    setNormalized(normSel, null);

    const payload = readFields('epv');

    $.post(st.ajaxUrl, {
      action: 'entry_verify_address',
      _ajax_nonce: st.nonce,
      address: JSON.stringify(payload),
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

  $(document).on('click', '#epv-verify', function(e){
    e.preventDefault();
    verifyNow();
  });

  // Optional: Enter key on any field triggers verify
  $(document).on('keydown', '.epv-field input', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      verifyNow();
    }
  });

})(jQuery);
JS);

    // ---------- HTML (inline, simple) ----------
    ob_start(); ?>
<div class="epv-group">
  <div class="epv-legend-wrap">
    <div>
      <div class="epv-legend">Address</div>
      <div id="epv-normalized" class="epv-normalized"></div>
    </div>
    <span id="epv-verify-status" class="epv-verify-status"></span>
  </div>

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

  <!-- BIG VERIFY BUTTON BELOW ALL FIELDS -->
  <div class="epv-row-1">
    <button id="epv-verify" class="epv-btn-primary" type="button">Verify Address</button>
  </div>
</div>

    <?php
    return ob_get_clean();
}

/* ========= AJAX: Verify (Smarty) for this simple shortcode ========= */
add_action('wp_ajax_entry_verify_address', 'ep_ajax_entry_verify_address');
add_action('wp_ajax_nopriv_entry_verify_address', 'ep_ajax_entry_verify_address');
function ep_ajax_entry_verify_address() {
    check_ajax_referer('ep_entry_verify_nonce');

    $raw = wp_unslash($_POST['address'] ?? '');
    $decoded = json_decode($raw, true);
    $strict  = true; // enforce strict as requested

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
