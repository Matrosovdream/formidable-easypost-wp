<?php

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