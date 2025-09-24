<?php

/* ========= AJAX: Buy label ========= */
add_action('wp_ajax_easypost_create_label', 'ep_ajax_easypost_create_label');
add_action('wp_ajax_nopriv_easypost_create_label', 'ep_ajax_easypost_create_label');
function ep_ajax_easypost_create_label() {
    check_ajax_referer('ep_easypost_nonce');

    $shipmentId = sanitize_text_field($_POST['shipment_id'] ?? '');
    $rateId     = sanitize_text_field($_POST['rate_id'] ?? '');
    if (!$shipmentId || !$rateId) wp_send_json_error(['message' => 'Missing shipment or rate.']);

    // NEW: accept label messages in Buy request too (use in your API layer if supported)
    $labelMsg1 = sanitize_text_field($_POST['label_message1'] ?? '');
    $labelMsg2 = sanitize_text_field($_POST['label_message2'] ?? '');

    try {
        $shipmentApi = new FrmEasypostShipmentApi();

        // Buy label by API
        $label = $shipmentApi->buyLabel($shipmentId, $rateId);

        if (empty($label) || !is_array($label)) {
            wp_send_json_error(['message' => 'Empty response from label API.']);
        }

        // Update Shipment by API
        $shipmentHelper = new FrmEasypostShipmentHelper();
        $shipmentHelper->updateShipmentApi($shipmentId );

        /**
         * Fires right after a label is successfully bought.
         *
         * @param array $shipment Normalized shipment data (see fields above).
         */
        do_action('frm_easypost_label_bought', $label);

        wp_send_json_success([
            'general' => $label['general'] ?? [],
            'label'   => $label['postage_label']   ?? [],
        ]);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}