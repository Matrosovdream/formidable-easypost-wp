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

    public function getVoidShipment(): array {
        $opts = $this->getAllSettings();
        return [
            'void_statuses'   => isset($opts['void_statuses']) && is_array($opts['void_statuses']) ? $opts['void_statuses'] : [],
            'void_after_days' => max(0, (int)($opts['void_after_days'] ?? 0)),
        ];
    }

    public function getAllSettings() {
        return get_option('frm_easypost', []);
    }

}