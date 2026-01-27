<?php

/* ========= AJAX: Calculate rates ========= */
add_action('wp_ajax_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
add_action('wp_ajax_nopriv_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
function ep_ajax_easypost_calculate_rates() {
    check_ajax_referer('ep_easypost_nonce');

    $raw = wp_unslash($_POST['data'] ?? '');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) wp_send_json_error(['message' => 'Invalid payload.']);

    // Calculate rates by API
    $rateHelper = new FrmEasypostRateHelper();
    $data = $rateHelper->calculateRatesByEntry( $decoded );

    if ( isset( $data['errors'] ) && $data['errors'] === true ) {
        wp_send_json_error( ['message' => $data['message']] );
    } else {
        wp_send_json_success( $data );
    }
 
}