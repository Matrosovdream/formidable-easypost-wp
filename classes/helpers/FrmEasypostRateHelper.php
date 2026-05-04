<?php

class FrmEasypostRateHelper {

    public function calculateRatesByEntry( array $params ): array {
        
        // Get entry data and user email (if available)
        $entry = FrmEntry::getOne( $params['entry_id'], true );
        if( isset( $entry->user_id ) && $entry->user_id ) {
        $user = get_user_by( 'id', $entry->user_id );
        }
        $userEmail = $user->user_email ?? '';
        
        // NEW: pick up label messages from payload
        $labelMsg1 = sanitize_text_field($params['label_message1'] ?? '');
        $labelMsg2 = sanitize_text_field($params['label_message2'] ?? '');
        $labelDate = sanitize_text_field($params['label_date'] ?? '');

        $labelData = [
            'from_address' => [
                'name'    => sanitize_text_field($params['from_address']['name'] ?? ''),
                'street1' => sanitize_text_field($params['from_address']['street1'] ?? ''),
                'street2' => sanitize_text_field($params['from_address']['street2'] ?? ''),
                'city'    => sanitize_text_field($params['from_address']['city'] ?? ''),
                'state'   => sanitize_text_field($params['from_address']['state'] ?? ''),
                'zip'     => sanitize_text_field($params['from_address']['zip'] ?? ''),
                'phone'   => sanitize_text_field($params['from_address']['phone'] ?? ''),
                'country' => 'US',
            ],
            'to_address' => [
                'name'    => sanitize_text_field($params['to_address']['name'] ?? ''),
                'street1' => sanitize_text_field($params['to_address']['street1'] ?? ''),
                'street2' => sanitize_text_field($params['to_address']['street2'] ?? ''),
                'city'    => sanitize_text_field($params['to_address']['city'] ?? ''),
                'state'   => sanitize_text_field($params['to_address']['state'] ?? ''),
                'zip'     => sanitize_text_field($params['to_address']['zip'] ?? ''),
                'phone'   => sanitize_text_field($params['to_address']['phone'] ?? ''),
                'country' => 'US',
                'email'   => sanitize_email($userEmail),
            ],
            'parcel' => [
                'weight' => floatval($params['parcel']['weight'] ?? 0),
            ],
            'reference'  => sanitize_text_field($params['entry_id'] ?? ''),
            // Not all carriers honor custom print fields; include under options if supported
            'options'    => [
                'print_custom_1' => $labelMsg1,
                'print_custom_2' => $labelMsg2,
                'label_date'    => $labelDate,
            ],
        ];

        try {

            $carrierHelper   = new FrmEasypostCarrierHelper();
            $carrierAccounts = $carrierHelper->getCarrierAccounts();

            $settingsHelper = new FrmEasypostSettingsHelper();
            $processingTimeRules = $settingsHelper->getProcessingTimeRules();

            // Filters accounts based on processing time rules
            $rulesSetFinal = [];
            foreach( $processingTimeRules as $fieldId => $rules ) {

                $procTimeValue = FrmEntryMeta::get_entry_meta_by_field( $entry->id, $fieldId, true );

                foreach( $rules as $rule ) {
                    
                    if( $rule['field_value'] == $procTimeValue ) {
                        $rulesSetFinal = $rule['rules'];
                    }

                }

            }

            $addresses = [
                "from_address" => $labelData['from_address'],
                "to_address"   => $labelData['to_address'],
            ];

            $rates    = [];
            $shipment = null;

            $preset        = sanitize_text_field($params['parcel']['predefined_package'] ?? '');
            $presetCarrier = strtolower(sanitize_text_field($params['parcel']['predefined_carrier'] ?? ''));
            $L = floatval($params['parcel']['length'] ?? 0);
            $W = floatval($params['parcel']['width']  ?? 0);
            $H = floatval($params['parcel']['height'] ?? 0);

            foreach( $carrierAccounts as $account ) {

                // Build parcel from user input
                $parcel = ['weight' => floatval($params['parcel']['weight'] ?? 1)];

                if ($preset !== '') {
                    // Preset belongs to a specific carrier; skip the others
                    if ($presetCarrier && strtolower($account['code']) !== $presetCarrier) {
                        continue;
                    }
                    $parcel['predefined_package'] = $preset;
                } elseif ($L > 0 && $W > 0 && $H > 0) {
                    $parcel['length'] = $L;
                    $parcel['width']  = $W;
                    $parcel['height'] = $H;
                } elseif (!empty($account['packages'][0])) {
                    $parcel['predefined_package'] = $account['packages'][0];
                }

                $req = [
                    "from_address" => $addresses['from_address'],
                    "to_address"   => $addresses['to_address'],
                    "parcel"       => $parcel,
                    "carrier_accounts" => [$account['id']],
                    "reference"        => sanitize_text_field($params['entry_id'] ?? ''),
                    // forward the label messages as EasyPost "options" if your API layer supports this
                    "options"          => [
                        "print_custom_1" => $labelMsg1,
                        "print_custom_2" => $labelMsg2,
                        "label_date"    => $labelDate,
                    ],
                ];

                if( strtolower( $account['code'] ) == 'fedex' ) {
                    unset( $req['options'] );
                } 

                $shipmentApi = new FrmEasypostShipmentApi();
                $shipment    = $shipmentApi->createShipment($req);

                if( isset( $shipment['rates'] ) ) {
                    foreach( $shipment['rates'] as $rate ) {

                        $rate = $this->prepareRateData( $rate );

                        $rate['shipment_id'] = $shipment['general']['id'];
                        $rate['package']     = $account['packages'][0];
                        $rates[] = $rate;
                    }
                }
            }

            // ground_only → keep only USPS GroundAdvantage rates
            if ( ! empty( $params['ground_only'] ) ) {
                $rates = array_filter( $rates, function( $rate ) {
                    return strtolower( trim( $rate['service'] ?? '' ) ) === 'groundadvantage';
                } );
                $rates = array_values( $rates );
            }

            // Exclude rates based on processing time rules
            if( ! empty( $rulesSetFinal ) ) {
                $ratesFiltered = [];
                foreach( $rates as $rate ) {

                    $carrier = trim( strtolower($rate['carrier'] ) );
                    if( isset( $rulesSetFinal[ $carrier ] ) ) {
                        $allowedServices = $rulesSetFinal[ $carrier ]['services'];
                        if( empty( $allowedServices ) || in_array( strtolower($rate['service']), $allowedServices ) ) {
                            $ratesFiltered[] = $rate;
                        }
                    } else {
                        $ratesFiltered[] = $rate;
                    }
                }
                    
                $rates = $ratesFiltered;
            }

            $data = [
                'general'      => $shipment['general']      ?? [],
                'from_address' => $addresses['from_address'],
                'to_address'   => $addresses['to_address'],
                'rates'        => $rates ?? []
            ];

        } catch (Throwable $e) {

            $data = ['errors' => true, 'message' => 'API error: '.$e->getMessage()];

        }

        return $data;

    }

    protected function prepareRateData( array $rate ): array {

        // Label
        $labelArr = [
            $rate['carrier'] ?? '',
            $rate['service'] ?? '',
            (isset($rate['rate']) ? $rate['rate'] . '$' : ''),
            isset($rate['est_delivery_days']) ? $rate['est_delivery_days'].' days' : '',

        ];
        $rate['label'] = trim( implode( ' - ', array_filter( $labelArr ) ) );

        // Normalize rate data
        return $rate;

    }

}