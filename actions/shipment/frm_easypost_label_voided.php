<?php

add_action('frm_easypost_label_voided', 'frm_easypost_label_voided_func', 10, 1);
function frm_easypost_label_voided_func($label) {
    
    $shipment = $label['shipment'];

    $helper = new FrmEasypostShipmentHelper;

    $payload = [
        'shipment_id' => $general['shipment_id'] ?? null,
        'easypost_shipment_id' => $shipment->id ?? '',
        'user_id' => get_current_user_id(),
        'change_type' => 'void',
        'description' => ''
    ];
    $helper->AddHistoryRecord( $payload );

}