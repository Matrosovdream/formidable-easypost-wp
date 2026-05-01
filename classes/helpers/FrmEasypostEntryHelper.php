<?php

class FrmEasypostEntryHelper {

    protected $addressModel;

    public function __construct() {
        $this->addressModel = new FrmEasypostShipmentAddressModel();
    }

    public function getEntryAddresses( int $entry_id ) {

        $addresses = array_merge(
            $this->getCorporateAddresses(),
            [ $this->getEntryAddressFields( $entry_id ) ]
            //$this->getUserAddressesByEntry( $entry_id )
        );

        return $this->prepareEntryAddresses( $entry_id, $addresses );

    }

    public function prepareEntryAddresses( int $entry_id, array $addresses ): array {

        $procTimes = [
          145 => 'Standard',
          175 => 'Expedited',
          375 => 'Rushed',
        ];
  
        $entryMetas  = $this->getEntryMetas($entry_id);
        $procTimeId  = isset($entryMetas[211]) ? $entryMetas[211] : '';
        $procTimeVal = $procTimes[ $procTimeId ] ?? '';
        $entryState  = isset($entryMetas[40])  ? (string)$entryMetas[40] : '';
        //$entryStateL = strtolower(trim($entryState));
  
        $selectedAddress = null;
        $procTime        = '';
  
        // Determine target proc time label (if mapped)
        if (isset($procTimes[$procTimeId])) {
            $procTime = $procTimes[$procTimeId];
        }
  
        // Build candidates by proc time (if we know the label)
        $candidates = [];
        if ($procTime !== '' && is_array($addresses)) {
            foreach ($addresses as $a) {
                if (($a['proc_time'] ?? '') === $procTime) {
                    $candidates[] = $a;
                }
            }
        }
  
        // Tiebreaker only by service_states (and must match to select)
        if (
          !empty($candidates) && 
          count($candidates) > 1 &&
          $entryState !== ''
          ) {
            foreach ($candidates as $a) {
                //$svcStates = $parse_csv($a['service_states'] ?? '');
                if (in_array($entryState, $a['service_states'])) {
                    $selectedAddress = $a;
                    break;
                }
            }
            // If no match in service_states, leave $selectedAddress = null
        } else {
          $selectedAddress = $candidates[0];
        }
  
        // Prepare output list
        $out = [];
        if (is_array($addresses)) {
            foreach ($addresses as $a) {
                $out[] = [
                    'name'       => sanitize_text_field($a['name']    ?? ''),
                    'street1'    => sanitize_text_field($a['street1'] ?? ''),
                    'street2'    => sanitize_text_field($a['street2'] ?? ''),
                    'city'       => sanitize_text_field($a['city']    ?? ''),
                    'state'      => sanitize_text_field($a['state']   ?? ''),
                    'zip'        => sanitize_text_field($a['zip']     ?? ''),
                    'country'    => sanitize_text_field($a['country'] ?? 'US'),
                    'phone'      => sanitize_text_field($a['phone']   ?? ''),
                    'proc_time'  => sanitize_text_field($a['proc_time'] ?? ''),
                    'is_user_address' => !empty($a['is_user_address']) ? true : false,
                    'Selected'   => false, // default false
                ];
            }
        }
  
          // Prepare ready_routes from $out, based on is_user_address and all pairs, and reversed
          $user_indexes = [];
          $other_indexes = [];
  
          foreach ($out as $idx => $row) {
              if (!empty($row['is_user_address'])) {
                  $user_indexes[] = (int) $idx;
              } else {
                  $other_indexes[] = (int) $idx;
              }
          }
  
          // Build pairs: user -> other, and reversed other -> user
          $ready_routes = [];
          foreach ($user_indexes as $u) {
              foreach ($other_indexes as $o) {
                  $ready_routes[] = [ $u, $o ];
                  $ready_routes[] = [ $o, $u ];
              }
          }
  
        // If we picked a specific address, override selection by matching ZIP
        if ($selectedAddress && !empty($out)) {
            foreach ($out as $k => $row) {
  
              if( $row['is_user_address'] ) { continue; }
  
              if (($row['zip'] ?? '') !== '' && ($row['zip'] === ($selectedAddress['zip'] ?? ''))) {
                  $out[$k]['Selected'] = true;
                  break;
              }
            }
        }
  
        // Let's find closest match by ZIP and proc_time
        $closestAddress = null;
        foreach ($out as $k => $row) {
            if (
                $row['Selected'] === true
            ) {
                $closestAddress = $row;
            }
        }
  
      // Where is_user_address true address?
      $entryAddress = null;
      foreach ($out as $row) {
          if (!empty($row['is_user_address'])) {
              $entryAddress = $row;
              break;;
          }    
      }  
  
      // Passport service, find in name the word "Passport Service"
      $passportServiceAddress = null;
      foreach ($out as $row) {
          if (stripos($row['name'], 'Passport Service') !== false) {
              $passportServiceAddress = $row;
              break;;
          }    
      }
  
      $data = [
          'addresses' => $out, 
          'closest_address' => $closestAddress, 
          'entry_address' => $entryAddress,
          'passport_service' => $passportServiceAddress,
          'ready_routes' => $ready_routes
      ];

      return $data;

    }
 
    public function getEntryMetas( int $entry_id ) {

        FrmEntry::clear_cache();
        
        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) {
            return [];
        }

        return is_array($entry->metas ?? null) ? $entry->metas : [];

    }

    public function getEntryMetaValue( int $entry_id, int $field_id ) {
        
        return FrmEntryMeta::get_entry_meta_by_field($entry_id, $field_id, true);

    }

    public function updateMetaField(int $entry_id, int $field_id, $value): bool
    {
        $ok = \FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $value);
        if (!$ok) {
            if (method_exists('\FrmEntryMeta', 'delete_entry_meta')) {
                \FrmEntryMeta::delete_entry_meta($entry_id, $field_id);
            }
            \FrmEntryMeta::add_entry_meta($entry_id, $field_id, '', $value);
        }
        return true;
    }

    public function updateMultipleMetaField(int $entry_id, int $field_id, array $values, string $action='add'): bool
    {
        
        $oldValue = \FrmEntryMeta::get_entry_meta_by_field($entry_id, $field_id, true);
        $oldValues = is_array($oldValue) ? $oldValue : [];

        if( $action === 'add' ) {
            // Add values
            $newValues = array_unique( array_merge( $oldValues, $values ) );
        } elseif( $action === 'remove' ) {
            // Remove values
            $newValues = array_diff( $oldValues, $values );
        } else {
            // Replace
            $newValues = $values;
        }

        // Update meta
        return $this->updateMetaField( $entry_id, $field_id, $newValues );
    }

    public function updateEntryFresh( int $entry_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'frm_items'; // Formidable entries table
        $now   = current_time( 'mysql', true );
    
        $res = (bool) $wpdb->update(
            $table,
            [ 'updated_at' => $now ],
            [ 'id' => $entry_id ],
            [ '%s' ],
            [ '%d' ]
        );

        FrmEntry::clear_cache();

        return $res;

    }    

    public function getEntryAddressFields( int $entry_id ) {
        
        // From /references.php
        $fields = FRM_EP_ENTRY_ADDRESS_FIELDS ?? [];

        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) {
            //return '<div class="ep-entry-error">Entry not found for ID #' . esc_html((string)$entry_id) . '.</div>';
            return [];
        }

        $entry_metas = is_array($entry->metas ?? null) ? $entry->metas : [];

        // Resolve values by field IDs
        $fieldValues = [];
        foreach ($fields as $key => $fieldId) {
            $fieldValues[$key] = ($fieldId !== '' && isset($entry_metas[$fieldId])) ? (string)$entry_metas[$fieldId] : '';
        }
        $fieldValues['name'] = trim(($fieldValues['firstname'] ?? '') . ' ' . ($fieldValues['lastname'] ?? ''));

        $fieldValues['country'] = 'US';
        $fieldValues['is_user_address'] = true;

        // Trim all $fieldValues
        foreach( $fieldValues as $k => $v ) {
            $fieldValues[$k] = trim( (string)$v );
        }

        // Combined address line
        $fieldValues['combined'] = $this->prepareCombinedAddress( $fieldValues );

        return $fieldValues;

    }

    public function prepareCombinedAddress( array $addressFields ): string {

        $combinedAddress = [];
        if (!empty($addressFields['street1']) || !empty($addressFields['street2'])) {
            $combinedAddress[] = trim($addressFields['street1'] . ' ' . $addressFields['street2']);
        }
        if (!empty($addressFields['city']) || !empty($addressFields['state']) || !empty($addressFields['zip'])) {
            $combinedAddress[] = trim($addressFields['city'] . ' ' . $addressFields['state'] . ' ' . $addressFields['zip']);
        }

        // Exclude empty parts
        $combinedAddress = array_filter($combinedAddress, fn($part) => trim($part) !== '');
        $resLine = implode(', ', $combinedAddress);

        return $resLine;

    }

    public function verifyEntryAddress( int $entry_id ): array {

        $address = $this->getEntryAddressFields( $entry_id );
        //$addressLine = $address['combined'] ?? '';

        try {
            $smartyApi = new FrmSmartyApi();
            $resp = $smartyApi->verifyAddress($address, true);
    
            if (is_array($resp) && !empty($resp['status']) && $resp['status'] === 'verified') {

                // Set payload address
                $resp['normalized']['input_address'] = $address['combined'] ?? '';

                // Check if $address['combined'] matches verified address?
                if( $resp['normalized']['full_address'] == $address['combined'] ) {
                    $resp['normalized']['matched'] = true;
                } else {
                    $resp['normalized']['matched'] = false;
                }

                return [
                    'ok'         => true,
                    'status'     => 'verified',
                    'normalized' => $resp['normalized'] ?? [],
                    'message'    => 'Address verified'
                ];
            }
    
            return [
                'ok' => false, 
                'message' => 'Address not verified'
            ];

        } catch (Throwable $e) {
            return [
                'ok' => false, 
                'message' => 'Verify error: '.$e->getMessage()
            ];
        }

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

    public function voidShipments() {

        $settings       = (new FrmEasypostSettingsHelper())->getVoidShipment();
        $shipmentHelper = new FrmEasypostShipmentHelper(); // kept if you need it elsewhere
    
        if ( empty($settings['void_after_days']) || $settings['void_after_days'] <= 0 ) {
            return;
        }
    
        $shipmentModel = new FrmEasypostShipmentModel();
        $shipments = $shipmentModel->getList(
            [
                'status'          => $settings['void_statuses'],
                'void_after_days' => $settings['void_after_days'],
                'refund_status'   => null, // skip already-refunded
            ],
            ['limit' => 10000]
        );
    
        // Space out events to avoid a thundering herd (default 5s; filterable)
        $spacing = (int) apply_filters('frm_void_single_shipment_spacing_seconds', 5);
        $when    = time() + 3; // start 10s from now to be safe
    
        foreach ($shipments as $i => $shipment) {
            if (empty($shipment['easypost_id'])) {
                continue;
            }
    
            $easypost_id = (string) $shipment['easypost_id'];
            $tracking_code = (string) ($shipment['tracking_code'] ?? '');
            $ts = $when + ($i * max(1, $spacing));
    
            // Avoid duplicates if already queued with same args
            if ( ! wp_next_scheduled('frm_void_single_shipment', [$easypost_id, $tracking_code]) ) {
                wp_schedule_single_event($ts, 'frm_void_single_shipment', [$easypost_id, $tracking_code]);
            }
        }

    }

    /**
     * Calculate shipping rates for a given entry with filters.
     *
     * @param int   $entry_id The ID of the entry to calculate rates for.
     * @param array $filters  An associative array of filters to apply.
     *
     * @return array An array of calculated shipping rates.
     */
    public function calculateRatesByEntry( int $entry_id, array $filters, array $sorting, string $labelDirection='' ) {

        // From /references.php
        $labelDirectionTypes = FRM_EP_LABEL_DIRECTION_TYPES;


        // Get all entry addresses
        $addresses = $this->getEntryAddresses( $entry_id );

        // Prepare payload for calculate rates, by default
        $ratesPayload = [
            'entry_id' => $entry_id,
            'from_address' => $addresses['closest_address'] ?? [],
            'to_address'   => $addresses['entry_address'] ?? [],
        ];

        // When the caller opts into ground_only (e.g. mass-buy "Ground" checkbox),
        // pull saved defaults from Settings → General → Default dimensions so
        // RateHelper sends explicit L/W/H/weight (required for USPS GroundAdvantage).
        $groundOnly = ! empty( $filters['ground_only'] );
        if ( $groundOnly ) {
            $defaultDims = (new FrmEasypostSettingsHelper())->getDefaultDimensions();
            $parcel = [];
            foreach ( ['length', 'width', 'height', 'weight'] as $k ) {
                if ( $defaultDims[$k] !== '' && $defaultDims[$k] > 0 ) {
                    $parcel[$k] = $defaultDims[$k];
                }
            }
            if ( ! empty( $parcel ) ) {
                $ratesPayload['parcel'] = $parcel;
            }
        }

        if( !empty($labelDirection) ) {
            
            $labelDirection = $labelDirectionTypes[$labelDirection] ?? null;
            if( $labelDirection ) {

                $labelDirectionAdresses = $labelDirection['addresses'];

                $ratesPayload['from_address'] = $addresses[ $labelDirectionAdresses['from'] ];
                $ratesPayload['to_address'] = $addresses[ $labelDirectionAdresses['to'] ];

                // Combined address lines
                $ratesPayload['from_address']['combined'] = $this->prepareCombinedAddress( $ratesPayload['from_address'] );
                $ratesPayload['to_address']['combined']   = $this->prepareCombinedAddress( $ratesPayload['to_address'] );

            }

        }

        // Calculate rates by API
        $rateHelper = new FrmEasypostRateHelper();
        $ratesData = $rateHelper->calculateRatesByEntry( $ratesPayload );

        $rates = $ratesData['rates'] ?? [];

        // Filter rates based on provided filters
        if ( ! empty( $filters ) && is_array( $rates ) ) {
            
            if( isset( $filters['carrier'] ) ) {

                $carrierFilter = strtolower( trim( $filters['carrier'] ) );
                $rates = array_filter( $rates, function( $rate ) use ( $carrierFilter ) {
                    return strtolower( trim( $rate['carrier'] ?? '' ) ) === $carrierFilter;
                } );

            }

            // ground_only → keep only USPS GroundAdvantage rates
            if ( ! empty( $filters['ground_only'] ) ) {
                $rates = array_filter( $rates, function( $rate ) {
                    return strtolower( trim( $rate['service'] ?? '' ) ) === 'groundadvantage';
                } );
            }

        }

        // Sort rates based on provided sorting options
        if ( ! empty( $sorting ) && is_array( $rates ) ) {

            if( isset( $sorting['rate'] ) ) {

                $sortOrder = strtolower( trim( $sorting['rate'] ) ) === 'asc' ? SORT_ASC : SORT_DESC;
                usort( $rates, function( $a, $b ) use ( $sortOrder ) {
                    $rateA = floatval( $a['rate'] ?? 0 );
                    $rateB = floatval( $b['rate'] ?? 0 );
                    if ( $rateA == $rateB ) {
                        return 0;
                    }
                    return ( $sortOrder === SORT_ASC ) ? ( $rateA <=> $rateB ) : ( $rateB <=> $rateA );
                } );

            }


        }

        return [
            'entry_id' => $entry_id,
            'rates' => $rates,
            'addresses' => [
                'from' => $ratesPayload['from_address'] ?? [],
                'to'   => $ratesPayload['to_address'] ?? [],
            ]
        ];

    }

    public function updateEntryShipmentData( int $entry_id, array $data, array $labelData = [] ): bool {

        /*
        $data = [
            "id": "1194356",
            "easypost_id": "shp_84ea2a54439d4373ba951a14aad8a3a3",
            "entry_id": "64828",
            "is_return": "0",
            "status": "unknown",
            "tracking_code": "9405500208303116390324",
            "refund_status": null,
            "mode": "test",
            "created_at": "2026-01-30 01:52:50",
            "updated_at": "2026-01-30 01:52:57",
            "tracking_url": "https:\/\/track.easypost.com\/djE6dHJrXzdiMmM1MDY1YmI2MTRiMTM5MGYzMTYwNGJmODExZjg4"
        ]
        */

        $fieldsMap = [
            'tracking_code' => 344,
            'carrier'      => 354,
        ];

        $selectedRate = $labelData['selected_rate'] ?? [];

        // Update tracking_code
        if( 
            isset( $data['tracking_code'] ) 
            ) {
            $fieldId = $fieldsMap['tracking_code'];
            $this->updateMetaField( $entry_id, $fieldId, sanitize_text_field( $data['tracking_code'] ) );

        }

        // Update Label carrier
        if( 
            isset( $selectedRate['carrier'] ) 
            ) {
            $fieldId = $fieldsMap['carrier']; 

            // Fix for Formidable values, no other way around it
            if( $selectedRate['carrier'] == 'FedEx' ) { $selectedRate['carrier'] = 'Fedex';  }

            $this->updateMetaField( $entry_id, $fieldId, sanitize_text_field( $selectedRate['carrier'] ) );

        }

        return true;

    }

    public function updateEntryAddress( int $entry_id, $fields, $addressType='mailing' ) {


        $fieldsMap = FRM_EP_ENTRY_ADDRESS_FIELDS ?? [];
        if( empty( $fieldsMap ) ) { return; }

        // Update each field
        foreach( $fieldsMap as $key => $fieldId ) {

            $value = sanitize_text_field( ($fields[$key] ) ) ?? null;
            if( isset( $value ) ) {
                $this->updateMetaField( $entry_id, $fieldId, $value );
            }   
        }

        // Refresh entry updated_at
        $this->updateEntryFresh( $entry_id );

    }

}