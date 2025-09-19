<?php

class FrmEasypostEntryHelper {

    protected $addressModel;

    public function __construct() {
        $this->addressModel = new FrmEasypostShipmentAddressModel();
    }

    public function getEntryAddresses( int $entry_id ) {

        $addresses = array_merge(
            $this->getCorporateAddresses(),
            $this->getEntryAddressFields( $entry_id )
            //$this->getUserAddressesByEntry( $entry_id )
        );

        return $addresses;

    }

    public function getEntryMetas( int $entry_id ) {
        
        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) {
            return [];
        }

        return is_array($entry->metas ?? null) ? $entry->metas : [];

    }

    protected function getEntryAddressFields( int $entry_id ) {
        
        $fields = [
            'firstname' => 1,
            'lastname'  => 2,
            'street1'  => 37,
            'street2'  => 38,
            'city'      => 39,
            'state'     => 40,
            'zip'       => 41,
            'phone'     => 64,
        ];

        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) {
            return '<div class="ep-entry-error">Entry not found for ID #' . esc_html((string)$entry_id) . '.</div>';
        }

        $entry_metas = is_array($entry->metas ?? null) ? $entry->metas : [];

        // Resolve values by field IDs
        $fieldValues = [];
        foreach ($fields as $key => $fieldId) {
            $fieldValues[$key] = ($fieldId !== '' && isset($entry_metas[$fieldId])) ? (string)$entry_metas[$fieldId] : '';
        }
        $fieldValues['name'] = trim(($fieldValues['firstname'] ?? '') . ' ' . ($fieldValues['lastname'] ?? ''));

        $fieldValues['country'] = 'US';

        /*
        echo '<pre>';
        print_r($fieldValues);  
        echo '</pre>';
        die();
        */

        return [$fieldValues];

    }

    protected function getUserAddressesByEntry( int $entry_id ) {
        
        // Get entry data and user email (if available)
        $entry = FrmEntry::getOne( $entry_id );
        if( isset( $entry->user_id ) && $entry->user_id ) {
            $user = get_user_by( 'id', $entry->user_id );
        }
        $userEmail = $user->user_email ?? '';

        // Get previous user addresses
        $userItems = $this->addressModel->getList( ['email' => $userEmail], ['order_by' => 'id', 'order' => 'ASC', 'limit' => 100] );
        // Unique by zip
        $userItems = array_reduce( $userItems, function($carry, $item) {
            if( !isset($carry[$item['zip']]) ) {
                $carry[$item['zip']] = $item;
            }
            return $carry;
        }, []);

        return $userItems;

    }    

    /**
     * Return service/corporate addresses from settings, with sane fallback.
     */
    protected function getCorporateAddresses(): array {
        // The saved option
        $opts = get_option( 'frm_easypost', [] );
        $rows = isset( $opts['service_addresses'] ) && is_array( $opts['service_addresses'] )
            ? $opts['service_addresses']
            : [];

        // Normalize & keep only non-empty entries
        $fromOption = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $name    = isset( $row['name'] )    ? (string) $row['name']    : '';
            $company = isset( $row['company'] ) ? (string) $row['company'] : '';
            $proc_time = isset( $row['proc_time'] ) ? (string) $row['proc_time'] : '';
            $phone   = isset( $row['phone'] )   ? (string) $row['phone']   : '';
            $street1 = isset( $row['street1'] ) ? (string) $row['street1'] : '';
            $street2 = isset( $row['street2'] ) ? (string) $row['street2'] : '';
            $city    = isset( $row['city'] )    ? (string) $row['city']    : '';
            $state   = isset( $row['state'] )   ? (string) $row['state']   : '';
            $zip     = isset( $row['zip'] )     ? (string) $row['zip']     : '';
            $country = isset( $row['country'] ) ? strtoupper( (string) $row['country'] ) : 'US';
            $service_states  = isset( $row['service_states'] ) ? array_map('trim', explode( ',', (string) $row['service_states'] ) ) : [];

            // Require at least a name or a street1 to count as a valid row
            if ( $name === '' && $street1 === '' ) {
                continue;
            }

            $fromOption[] = compact( 'name', 'company', 'proc_time', 'phone', 'street1', 'street2', 'city', 'state', 'zip', 'country', 'service_states' );
        }

        return $fromOption;

    }

}