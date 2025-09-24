<?php

/**
 * ---------- AJAX: Void shipment ----------
 */
add_action('wp_ajax_easyspot_void_shipment', 'easyspot_ajax_void_shipment');
add_action('wp_ajax_nopriv_easyspot_void_shipment', 'easyspot_ajax_void_shipment');
function easyspot_ajax_void_shipment() {
    check_ajax_referer('easyspot_void_shipment', 'nonce');
    $easypost_id = sanitize_text_field($_POST['easypost_id'] ?? '');
    if ($easypost_id === '') wp_send_json_error(['message' => 'Missing ID']);
    if (!class_exists('FrmEasypostShipmentApi') || !class_exists('FrmEasypostShipmentHelper'))
        wp_send_json_error(['message' => 'Required classes not found']);
    try {

        // Make refund
        $shipmentApi = new FrmEasypostShipmentApi();
        $label = $shipmentApi->refundShipment($easypost_id);

        if( $label['ok'] ) {
            // Update all shipments
            $shipmentHelper = new FrmEasypostShipmentHelper();
            $shipmentHelper->updateShipmentApi( $easypost_id );

            wp_send_json_success($label);
        } else {

            // Send error
            wp_send_json_error($label);
        }

    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}
