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

    public function getDefaultDimensions(): array {
        $settings = $this->getAllSettings();
        return [
            'length' => isset($settings['default_length']) && $settings['default_length'] !== '' ? (float)$settings['default_length'] : '',
            'width'  => isset($settings['default_width'])  && $settings['default_width']  !== '' ? (float)$settings['default_width']  : '',
            'height' => isset($settings['default_height']) && $settings['default_height'] !== '' ? (float)$settings['default_height'] : '',
            'weight' => isset($settings['default_weight']) && $settings['default_weight'] !== '' ? (float)$settings['default_weight'] : '',
        ];
    }

    public function getVoidShipment(): array {
        $opts = $this->getAllSettings();
        return [
            'void_statuses'   => isset($opts['void_statuses']) && is_array($opts['void_statuses']) ? $opts['void_statuses'] : [],
            'void_after_days' => max(0, (int)($opts['void_after_days'] ?? 0)),
        ];
    }

    public function getProcessingTimeRules(): array {
        $opts = $this->getAllSettings();
        $rules = isset($opts['processing_time_rules']) && is_array($opts['processing_time_rules']) ? $opts['processing_time_rules'] : [];

        // Group by field_id for easier lookup
        $groupedRules = [];
        foreach( $rules as $rule ) {
            $fieldId = isset($rule['field_id']) ? (int)$rule['field_id'] : 0;
            if( $fieldId > 0 ) {

                // Prepare internal rules
                $rulesNew = [];
                foreach( $rule['rules'] as $r ) {

                    $carrier = trim( strtolower($r['carrier']) );
                    $servicesList = array_filter(array_map('trim', explode(',', (string)($r['services'] ?? ''))));

                    $rulesNew[ $carrier ] = [
                        'carrier'  => $r['carrier'],
                        'services' => array_map('strtolower', $servicesList),
                    ];

                }
                $rule['rules'] = $rulesNew;

                $groupedRules[$fieldId][] = $rule;
            }
        }

        return $groupedRules;

    }

    public function getAllSettings() {
        return get_option('frm_easypost', []);
    }

}