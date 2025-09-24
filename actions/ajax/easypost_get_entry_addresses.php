<?php

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

  // helper: parse CSV to lowercased trimmed array
  $parse_csv = static function($csv): array {
      if (!is_string($csv)) return [];
      $parts = array_map('trim', explode(',', $csv));
      $parts = array_filter($parts, static fn($v) => $v !== '');
      return array_map(static fn($v) => strtolower($v), $parts);
  };

  try {
      $model     = new FrmEasypostEntryHelper();
      $addresses = $model->getEntryAddresses($entry_id);

      $procTimes = [
          145 => 'Standard',
          195 => 'Expedited',
          150 => 'Rushed',
      ];

      $entryMetas  = $model->getEntryMetas($entry_id);
      $procTimeId  = isset($entryMetas[211]) ? $entryMetas[211] : '';
      $procTimeVal = $procTimes[ $procTimeId ] ?? '';
      $entryState  = isset($entryMetas[40])  ? (string)$entryMetas[40] : '';
      //$entryStateL = strtolower(trim($entryState));

      $selectedAddress = null;
      $procTime        = '';

      // Determine target proc time label (if mapped)
      if (isset($procTimes[$procTimeId])) {
          $procTime = $procTimes[$procTimeId];
      }

      // Build candidates by proc time (if we know the label)
      $candidates = [];
      if ($procTime !== '' && is_array($addresses)) {
          foreach ($addresses as $a) {
              if (($a['proc_time'] ?? '') === $procTime) {
                  $candidates[] = $a;
              }
          }
      }

      // Tiebreaker only by service_states (and must match to select)
      if (
        !empty($candidates) && 
        count($candidates) > 1 &&
        $entryState !== ''
        ) {
          foreach ($candidates as $a) {
              //$svcStates = $parse_csv($a['service_states'] ?? '');
              if (in_array($entryState, $a['service_states'])) {
                  $selectedAddress = $a;
                  break;
              }
          }
          // If no match in service_states, leave $selectedAddress = null
      } else {
        $selectedAddress = $candidates[0];
      }

      // Prepare output list
      $out = [];
      if (is_array($addresses)) {
          foreach ($addresses as $a) {
              $out[] = [
                  'name'       => sanitize_text_field($a['name']    ?? ''),
                  'street1'    => sanitize_text_field($a['street1'] ?? ''),
                  'street2'    => sanitize_text_field($a['street2'] ?? ''),
                  'city'       => sanitize_text_field($a['city']    ?? ''),
                  'state'      => sanitize_text_field($a['state']   ?? ''),
                  'zip'        => sanitize_text_field($a['zip']     ?? ''),
                  'country'    => sanitize_text_field($a['country'] ?? 'US'),
                  'phone'      => sanitize_text_field($a['phone']   ?? ''),
                  'proc_time'  => sanitize_text_field($a['proc_time'] ?? ''),
                  'Selected'   => false, // default false
              ];
          }
      }

      // If we picked a specific address, override selection by matching ZIP
      if ($selectedAddress && !empty($out)) {
          foreach ($out as $k => $row) {
              if (($row['zip'] ?? '') !== '' && ($row['zip'] === ($selectedAddress['zip'] ?? ''))) {
                  $out[$k]['Selected'] = true;
                  break;
              }
          }
      }

      wp_send_json_success(['addresses' => $out]);
  } catch (Throwable $e) {
      wp_send_json_error(['message' => 'Fetch error: ' . $e->getMessage()]);
  }
}