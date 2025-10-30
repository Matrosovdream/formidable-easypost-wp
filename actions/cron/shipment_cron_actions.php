<?php
// 1) Worker: runs for ONE shipment (scheduled with wp_schedule_single_event)
add_action('frm_void_single_shipment', function (string $easypost_id, string $tracking_code) {

    try {
        $helper = new FrmEasypostShipmentHelper();
        $helper->voidShipment($easypost_id);
    } catch (\Throwable $e) {
        error_log('[frm_void_single_shipment] ' . $e->getMessage());
    }

}, 10, 2);