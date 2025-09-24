<?php

/* ========= AJAX: Calculate rates ========= */
add_action('wp_ajax_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
add_action('wp_ajax_nopriv_easypost_calculate_rates', 'ep_ajax_easypost_calculate_rates');
function ep_ajax_easypost_calculate_rates() {
    check_ajax_referer('ep_easypost_nonce');

    $raw = wp_unslash($_POST['data'] ?? '');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) wp_send_json_error(['message' => 'Invalid payload.']);

    // Get entry data and user email (if available)
    $entry = FrmEntry::getOne( $decoded['entry_id'] );
    if( isset( $entry->user_id ) && $entry->user_id ) {
      $user = get_user_by( 'id', $entry->user_id );
    }
    $userEmail = $user->user_email ?? '';

    // NEW: pick up label messages from payload
    $labelMsg1 = sanitize_text_field($decoded['label_message1'] ?? '');
    $labelMsg2 = sanitize_text_field($decoded['label_message2'] ?? '');

    $labelData = [
        'from_address' => [
            'name'    => sanitize_text_field($decoded['from_address']['name'] ?? ''),
            'street1' => sanitize_text_field($decoded['from_address']['street1'] ?? ''),
            'street2' => sanitize_text_field($decoded['from_address']['street2'] ?? ''),
            'city'    => sanitize_text_field($decoded['from_address']['city'] ?? ''),
            'state'   => sanitize_text_field($decoded['from_address']['state'] ?? ''),
            'zip'     => sanitize_text_field($decoded['from_address']['zip'] ?? ''),
            'phone'   => sanitize_text_field($decoded['from_address']['phone'] ?? ''),
            'country' => 'US',
        ],
        'to_address' => [
            'name'    => sanitize_text_field($decoded['to_address']['name'] ?? ''),
            'street1' => sanitize_text_field($decoded['to_address']['street1'] ?? ''),
            'street2' => sanitize_text_field($decoded['to_address']['street2'] ?? ''),
            'city'    => sanitize_text_field($decoded['to_address']['city'] ?? ''),
            'state'   => sanitize_text_field($decoded['to_address']['state'] ?? ''),
            'zip'     => sanitize_text_field($decoded['to_address']['zip'] ?? ''),
            'phone'   => sanitize_text_field($decoded['to_address']['phone'] ?? ''),
            'country' => 'US',
            'email'   => sanitize_email($userEmail),
        ],
        'parcel' => [
            'weight' => floatval($decoded['parcel']['weight'] ?? 0),
        ],
        'reference'  => sanitize_text_field($decoded['entry_id'] ?? ''),
        // Not all carriers honor custom print fields; include under options if supported
        'options'    => [
            'print_custom_1' => $labelMsg1,
            'print_custom_2' => $labelMsg2,
        ],
    ];

    try {
      $carrierHelper   = new FrmEasypostCarrierHelper();
      $carrierAccounts = $carrierHelper->getCarrierAccounts();

      $addresses = [
        "from_address" => $labelData['from_address'],
        "to_address"   => $labelData['to_address'],
      ];

      $rates    = [];
      $shipment = null;

      foreach( $carrierAccounts as $account ) {
          $req = [
              "from_address" => $addresses['from_address'],
              "to_address"   => $addresses['to_address'],
              "parcel"       => [
                  "weight" => floatval($decoded['parcel']['weight'] ?? 1),
                  "predefined_package" => $account['packages'][0],
              ],
              "carrier_accounts" => [$account['id']],
              "reference"        => sanitize_text_field($decoded['entry_id'] ?? ''),
              // forward the label messages as EasyPost "options" if your API layer supports this
              "options"          => [
                  "print_custom_1" => $labelMsg1,
                  "print_custom_2" => $labelMsg2,
              ],
          ];

          if( strtolower( $account['code'] ) == 'fedex' ) {
            unset( $req['options'] );
          } 

          $shipmentApi = new FrmEasypostShipmentApi();
          $shipment    = $shipmentApi->createShipment($req);

          if( isset( $shipment['rates'] ) ) {
              foreach( $shipment['rates'] as $rate ) {
                  $rate['shipment_id'] = $shipment['general']['id'];
                  $rate['package']     = $account['packages'][0];
                  $rates[] = $rate;
              }
          }
      }

      $data = [
        'general'      => $shipment['general']      ?? [],
        'from_address' => $addresses['from_address'],
        'to_address'   => $addresses['to_address'],
        'rates'        => $rates ?? []
      ];

      wp_send_json_success($data);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}