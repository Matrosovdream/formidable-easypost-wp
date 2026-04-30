<?php

add_action('frm_easypost_label_bought', 'frm_easypost_label_bought_func', 10, 1);
function frm_easypost_label_bought_func($label) {

    $general = $label['general'];

    $helper = new FrmEasypostShipmentHelper;

    $payload = [
        'shipment_id' => $general['shipment_id'] ?? null,
        'easypost_shipment_id' => $general['id'] ?? '',
        'user_id' => get_current_user_id(),
        'change_type' => 'buy',
        'description' => ''
    ];
    $helper->AddHistoryRecord( $payload );

}