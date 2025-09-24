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

    wp_enqueue_style(
        'ep-entry-verify-address-style',
        esc_url(FRM_EAP_BASE_PATH . 'assets/css/easypost-entry-verify-address.css?time=' . time()),
        [],
        null
    );

    wp_enqueue_script(
        'ep-entry-verify-address-script',
        esc_url(FRM_EAP_BASE_PATH . 'assets/js/easypost-entry-verify-address.js?time=' . time()),
        ['jquery'],
        null,
        true
    );

    wp_localize_script('ep-entry-verify-address-script', 'epEntryVerify', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ep_entry_verify_nonce'),
    ]);

    // ---------- HTML ----------
    ob_start(); ?>
    <div class="epv-group">
      <div class="epv-legend-wrap">
        <div style="display:flex;flex-direction:column;gap:6px;">
          <div class="epv-legend">Address</div>
          <div class="epv-normalized"></div>
        </div>
        <div class="epv-verify-bar">
          <button class="epv-verify-btn epv-verify" type="button">Verify</button>
          <button class="epv-toggle-btn epv-toggle-fields" type="button">Show fields</button>
          <span class="epv-verify-status"></span>
        </div>
      </div>

      <!-- Hidden by default; toggled by "Show fields" -->
      <div class="epv-form">
        <div class="epv-row">
          <div class="epv-field"><label>Name</label><input class="epv-name" value="<?php echo esc_attr($fieldValues['name'] ?? ''); ?>"></div>
          <div class="epv-field"><label>Phone</label><input class="epv-phone" value=""></div>
        </div>

        <div class="epv-row">
          <div class="epv-field"><label>Street 1</label><input class="epv-street1" value="<?php echo esc_attr($fieldValues['address1'] ?? ''); ?>"></div>
          <div class="epv-field"><label>Street 2</label><input class="epv-street2" value="<?php echo esc_attr($fieldValues['address2'] ?? ''); ?>"></div>
        </div>

        <div class="epv-row-3">
          <div class="epv-field"><label>City</label><input class="epv-city" value="<?php echo esc_attr($fieldValues['city'] ?? ''); ?>"></div>
          <div class="epv-field"><label>State</label><input class="epv-state" value="<?php echo esc_attr($fieldValues['state'] ?? ''); ?>"></div>
          <div class="epv-field"><label>ZIP</label><input class="epv-zip" value="<?php echo esc_attr($fieldValues['zip'] ?? ''); ?>"></div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
