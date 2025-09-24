<?php

class FrmEasypostSettingsHelper {

    public function getCarrierAccounts() {
        $settings = $this->getAllSettings();
       
        // Map values
        $accounts = [];
        foreach( $settings['carrier_accounts'] as $acc ) {
            $accounts[ $acc['id'] ] = $acc;
        }

        return $accounts;

    }

    public function getUspsTimezone() {
        $settings = $this->getAllSettings();
        return $settings['usps_timezone'] ?? null;
    }

    public function getAllSettings() {
        return get_option('frm_easypost', []);
    }

}