<?php

add_filter('frm_easypost_shipment_pre_create_data', function ($data) {
    
    // Add reference number to shipping label (if provided)
    $data['options'] = array_merge(
        $data['options'] ?? [],
        [
            'print_custom_3' => 'REF # ' . ($data['reference'] ?? 'N/A'),
        ]
    );

    // Set Timezone for USPS shipments
    $carrierAccounts = (new FrmEasypostSettingsHelper())->getCarrierAccounts();
    $chosenCarrier = $data['carrier_accounts'][0] ?? null;

    $uspsTimezone = (new FrmEasypostSettingsHelper())->getUspsTimezone();

    // Set a server timezone to match your WordPress settings
    date_default_timezone_set( get_option('timezone_string') );

    // Set 
    $data['created_at'] = date('Y-m-d\TH:i:s\Z');

    if (
        $chosenCarrier && 
        isset($carrierAccounts[$chosenCarrier]) &&
        $carrierAccounts[$chosenCarrier]['code'] == 'usps'
        ) {
        $data['usps_zone'] = $uspsTimezone ?? '';
    }

    return $data;

}, 10, 2);