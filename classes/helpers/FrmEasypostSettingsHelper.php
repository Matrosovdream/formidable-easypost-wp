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

    public function getAddressByTag(string $tag): ?array {
        $tag = trim($tag);
        if ($tag === '') {
            return null;
        }
    
        $needle = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
    
        $settings = $this->getAllSettings();
        $rows = isset($settings['service_addresses']) && is_array($settings['service_addresses'])
            ? $settings['service_addresses'] : [];
    
        foreach ($rows as $row) {
            $raw = (string)($row['tags'] ?? '');
            if ($raw === '') {
                continue;
            }
    
            // Normalize the CSV tags on the row
            $tags = array_filter(array_map('trim', explode(',', $raw)));
            $tags = array_map(function($v){
                return function_exists('mb_strtolower') ? mb_strtolower($v) : strtolower($v);
            }, $tags);
    
            if (in_array($needle, $tags, true)) {
                return $row;
            }
        }
    
        return null;
    }

    public function getAllSettings() {
        return get_option('frm_easypost', []);
    }

}