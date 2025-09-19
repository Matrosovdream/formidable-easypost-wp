<?php

// === [ AJAX: Void shipment ] ==================================================
add_action('wp_ajax_shp_void_shipment', 'shp_ajax_void_shipment');
add_action('wp_ajax_nopriv_shp_void_shipment', 'shp_ajax_void_shipment'); // allow if you show to guests; remove if not needed
function shp_ajax_void_shipment() {

    // Basic security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'shp_void_shipment')) {
        wp_send_json(['success' => false, 'data' => ['message' => 'Invalid nonce.']], 403);
    }

    $shipment_id = isset($_POST['shipment_id']) ? sanitize_text_field(wp_unslash($_POST['shipment_id'])) : '';
    if ($shipment_id === '') {
        wp_send_json(['success' => false, 'data' => ['message' => 'Missing shipment_id.']], 400);
    }

    // Try to void via your model if available; otherwise expose a hook for custom handling.
    $voided_at = current_time('mysql');
    $ok = false;

    try {
        
        $api = new FrmShipstationApi();

        $res = $api->voidLabel($shipment_id);

        echo "<pre>";
        print_r($res);
        echo "</pre>";
        die();

        if (is_wp_error($res)) {
            throw new Exception('API error: ' . $res->get_error_message());
        } else {
            $ok = true;
        }

        /**
         * Fallback/custom integration:
         * Allow sites to hook their own void logic:
         * Usage:
         *   add_filter('shp/void_shipment', function($ok, $shipment_id, &$voided_at){ ...; return true/false; }, 10, 3);
         */
        $ok = apply_filters('shp/void_shipment', $ok, $shipment_id, $voided_at);

    } catch (Throwable $e) {
        $ok = false;
    }

    if ($ok) {
        wp_send_json(['success' => true, 'data' => ['voided_at' => $voided_at]]);
    } else {
        wp_send_json(['success' => false, 'data' => ['message' => 'Void failed or not implemented.']], 500);
    }
}