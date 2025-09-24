<?php

add_action('frm_easypost_label_bought', 'frm_easypost_label_bought_handler', 10, 1);
function frm_easypost_label_bought_handler(array $label) {

    if( !isset( $label['general'] ) ) {
        return;
    }

    // Label values
    $entryId = $label['general']['reference'] ?? null;
    $trackingCode = $label['general']['tracking_code'] ?? '';
    $toAddressZip = $label['to_address']['zip'] ?? '';
    $carrier = $label['selected_rate']['carrier'] ?? '';

    if( !$entryId ) {
        return;
    }

    // Get entry addresses 
    $model = new FrmEasypostEntryHelper();
    $addresses = $model->getEntryAddresses($entryId);

    $isUserAddressTo = false;
    foreach( $addresses as $address ) {
        if( $address['zip'] === $toAddressZip ) {
            $isUserAddressTo = true;
            break;
        }
    } 

    if(  !$isUserAddressTo ) {
        return;
    }

    // Update entry with tracking code and carrier
    $fields = [
        344 => $trackingCode,
        354 => $carrier
    ];

    foreach( $fields as $fieldId => $value ) {
        // First delete any existing value
        FrmEntryMeta::delete_entry_meta( $entryId, $fieldId );

        // Now create it fresh
        FrmEntryMeta::add_entry_meta( $entryId, $fieldId, null, $value );

    }

}
