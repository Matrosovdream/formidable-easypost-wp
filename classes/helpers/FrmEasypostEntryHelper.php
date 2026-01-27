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

        // Combined
        $combinedAddress = [];
        $combinedAddress[] = $fieldValues['street1'] ?? '';
        $combinedAddress[] = $fieldValues['street2'] ?? '';
        $combinedAddress[] = $fieldValues['city'] ?? '';
        $combinedAddress[] = $fieldValues['state'] ?? '';
        $combinedAddress[] = $fieldValues['zip'] ?? '';

        // Exclude empty parts
        $combinedAddress = array_filter($combinedAddress, fn($part) => trim($part) !== '');
        $fieldValues['combined'] = implode(', ', $combinedAddress);

        return $fieldValues;

    }

    public function verifyEntryAddress( int $entry_id ): array {

        $address = $this->getEntryAddressFields( $entry_id );
        //$addressLine = $address['combined'] ?? '';

        try {
            $smartyApi = new FrmSmartyApi();
            $resp = $smartyApi->verifyAddress($address, true);
    
            if (is_array($resp) && !empty($resp['status']) && $resp['status'] === 'verified') {
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
    public function calculateRatesByEntry( int $entry_id, array $filters, array $sorting ): array {


        // Get all entry addresses
        $addresses = $this->getEntryAddresses( $entry_id );

        // Prepare payload for calculate rates
        $ratesPayload = [
            'entry_id' => $entry_id,
            'from_address' => $addresses['closest_address'] ?? [],
            'to_address'   => $addresses['entry_address'] ?? [],
        ];

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

        return $rates;

    }

}