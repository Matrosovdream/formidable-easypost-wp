<?php

class FrmEasypostAddressHelper {

    public function getShipmentAddressType( array $shipment ) {

        $general = $shipment['general'] ?? [];
        if ( empty( $general ) ) {
            return '';
        }
        
        $entry_id = $general['reference'];
        if ( empty( $entry_id ) ) {
            return '';
        }

        $entryHelper = new FrmEasypostEntryHelper;
        $entryAddresses = $entryHelper->getEntryAddresses( $entry_id );

        // Not finished yet!

    }

}