<?php

/* ========= AJAX: Verify (Smarty) ========= */
add_action('wp_ajax_entry_verify_address', 'ep_ajax_entry_verify_address');
add_action('wp_ajax_nopriv_entry_verify_address', 'ep_ajax_entry_verify_address');
function ep_ajax_entry_verify_address() {
    //check_ajax_referer('ep_entry_verify_nonce');

    $raw = wp_unslash($_POST['address'] ?? '');
    $decoded = json_decode($raw, true);
    $strict  = true;

    if (!is_array($decoded)) {
        wp_send_json_error(['message' => 'Invalid address payload.']);
    }

    if( isset( $decoded['entry_id'] ) ) {

        $entry_id = intval( $decoded['entry_id'] );
        if ( $entry_id > 0 ) {
            // Load address fields from entry
            if ( ! class_exists( 'FrmEasypostEntryHelper' ) ) {
                wp_send_json_error( [ 'message' => 'FrmEasypostEntryHelper not found.' ] );
            }
            $entry_helper = new FrmEasypostEntryHelper();
            $entry_addresses = $entry_helper->getEntryAddressFields( $entry_id );
            if ( is_array( $entry_addresses ) && count( $entry_addresses ) > 0 ) {
                // Use the first address found
                $addressData = $entry_addresses[0];
                // Override with any provided fields
                foreach ( [ 'name', 'street1', 'street2', 'city', 'state', 'zipcode' ] as $field ) {
                    if ( isset( $decoded[ $field ] ) ) {
                        $addressData[ $field ] = sanitize_text_field( $decoded[ $field ] );
                    }
                }
            } else {
                wp_send_json_error( [ 'message' => 'No address fields found for the given entry ID.' ] );
            }
        } else {
            wp_send_json_error( [ 'message' => 'Invalid entry ID.' ] );
        }

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