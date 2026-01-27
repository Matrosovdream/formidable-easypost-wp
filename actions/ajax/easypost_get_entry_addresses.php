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

    wp_send_json_success( $addresses );

  } catch (Throwable $e) {
      wp_send_json_error(['message' => 'Fetch error: ' . $e->getMessage()]);
  }
}