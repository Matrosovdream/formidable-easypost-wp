<?php

class FrmEasypostShipmentApi extends FrmEasypostAbstractApi {

    public function createShipment( array $data ): array {

        $res = $this->client->shipment->create($data);

        $errors = $this->handleErrors($res);

        if( empty($errors) ) {
            $res = $this->prepareShipmentResponse( $res );
        }

        return $errors ? $errors : $res;

    }

    public function buyLabel( string $shipmentId, string $rateId ) {

        // Retrieve the rate
        $rate = $this->client->rate->retrieve($rateId);

        if( $rate === null ) {
            return ['error' => 'Rate not found'];
        }

        // Buy the label
        $res = $this->client->shipment->buy($shipmentId, $rate);

        // Handle errors
        $errors = $this->handleErrors($res);

        if( empty($errors) ) {
            $res = $this->prepareShipmentResponse( $res );
        }

        return $errors ? $errors : $res;

    }

    public function voidShipment(string $shipmentId): array
    {
        try {
            $shipment = $this->client->shipment->retrieve($shipmentId);

            // Optional: basic guardrails
            if (!isset($shipment->postage_label)) {
                return [
                    'ok'     => false,
                    'errors' => [['message' => 'Shipment has no purchased label to void.']],
                ];
            }

            // Request refund (void). Status becomes "refund_pending" and later "refunded".
            $updated = $shipment->refund();

            return [
                'ok'       => true,
                'status'   => $updated->status ?? null,  // e.g. "refund_pending"
                'shipment' => $updated,
            ];
        } catch (InvalidRequestException $e) {
            return $this->formatEasyPostException($e);
        } catch (ApiException $e) {
            return $this->formatEasyPostException($e);
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'errors' => [['message' => $e->getMessage()]],
            ];
        }
    }

    public function getShipmentById(string $shipmentId, array $attachObjects): array
    {

        $res = $this->client->shipment->retrieve($shipmentId);

        $errors = $this->handleErrors($res);

        if( empty($errors) ) {
            $res = $this->prepareShipmentResponse( $res, $attachObjects );
        }

        return $errors ? $errors : $res;

    }

    public function getAllShipments(int $pageSize = 20, ?string $beforeId = null, ?string $afterId = null): array
    {

        $params = ['page_size' => $pageSize];

        if ($beforeId) {
            $params['before_id'] = $beforeId;
        }
        if ($afterId) {
            $params['after_id'] = $afterId;
        }

        $shipments = $this->client->shipment->all($params);
        $errors = $this->handleErrors($shipments);

        if( !empty($shipments->shipments) ) {
            $preparedShipments = [];
            foreach($shipments->shipments as $shipment) {
                $preparedShipments[] = $this->prepareShipmentResponse( $shipment );
            }
            
            return $preparedShipments;
        } else {
            return $errors;
        }

    }

    public function refundShipment(string $shipmentId): ?object
    {

        try {

            // Request a refund (EasyPost API marks it as "refund_pending")
            $refunded = $this->client->shipment->refund( $shipmentId );

            return $refunded;
        } catch (ApiException $e) {
            \Log::error('EasyPost refund error', [
                'status' => $e->getHttpStatus(),
                'code'   => $e->getCode(),
                'msg'    => $e->getMessage(),
                'errors' => method_exists($e, 'getErrors') ? $e->getErrors() : null,
            ]);
            return null;
        }

    }

    private function prepareShipmentResponse( EasyPost\Shipment $res, array $attachObjects=[] ): array {

        // General
        $general = [
            'id' => $res->id,
            'created_at' => $res->created_at,
            'updated_at' => $res->updated_at,
            'is_return' => $res->is_return,
            'reference' => $res->reference,
            'status' => $res->status,
            'tracking_code' => $res->tracking_code,
            'insurance' => $res->insurance,
            'postage_label' => $res->postage_label,
            'refund_status' => $res->refund_status,
            'scan_form' => $res->scan_form,
            'selected_rate' => $res->selected_rate,
            'trackers' => $res->trackers,
            'mode' => $res->mode,
        ];

        // From address
        $addressFrom = $res->from_address ? [
            'id' => $res->from_address->id,
            'name' => $res->from_address->name,
            'company' => $res->from_address->company,
            'street1' => $res->from_address->street1,
            'street2' => $res->from_address->street2,
            'city' => $res->from_address->city,
            'state' => $res->from_address->state,
            'zip' => $res->from_address->zip,
            'country' => $res->from_address->country,
            'phone' => $res->from_address->phone,
            'email' => $res->from_address->email,
        ] : null;

        // To address
        $addressTo = $res->to_address ? [
            'id' => $res->to_address->id,
            'name' => $res->to_address->name,
            'company' => $res->to_address->company,
            'street1' => $res->to_address->street1,
            'street2' => $res->to_address->street2,
            'city' => $res->to_address->city,
            'state' => $res->to_address->state,
            'zip' => $res->to_address->zip,
            'country' => $res->to_address->country,
            'phone' => $res->to_address->phone,
            'email' => $res->to_address->email,
        ] : null;

        // Parcel
        $parcel = $res->parcel ? [
            'id' => $res->parcel->id,
            'length' => $res->parcel->length,
            'width' => $res->parcel->width,
            'height' => $res->parcel->height,   
            'weight' => $res->parcel->weight
        ] : null;

        // Postage label
        if( !empty( $general['postage_label'] ) ) {

            $label = $general['postage_label'];

            $postageLabel = [
                'id' => $label->id,
                'created_at' => $label->created_at,
                'updated_at' => $label->updated_at,
                'date_advance' => $label->date_advance,
                'integrated_form' => $label->integrated_form,
                'label_date' => $label->label_date,
                'label_resolution' => $label->label_resolution,
                'label_size' => $label->label_size,
                'label_type' => $label->label_type,
                'label_file_type' => $label->label_file_type,
                'label_url' => $label->label_url,
                'label_pdf_url' => $label->label_pdf_url,   
                'label_zpl_url' => $label->label_zpl_url,
                'label_epl2_url' => $label->label_epl2_url,
            ];

            unset( $general['postage_label'] );

        }

        // Selected rate
        if( !empty( $general['selected_rate'] ) ) {

            $selRate = $general['selected_rate'];

            $selectedRate = [
                'id' => $selRate->id,
                'created_at' => $selRate->created_at,
                'updated_at' => $selRate->updated_at,
                'mode' => $selRate->mode,
                'service' => $selRate->service,
                'carrier' => $selRate->carrier,
                'rate' => $selRate->rate,
                'currency' => $selRate->currency,       
                'retail_rate' => $selRate->retail_rate,
                'retail_currency' => $selRate->retail_currency,
                'list_rate' => $selRate->list_rate,
                'list_currency' => $selRate->list_currency,
                'billing_type' => $selRate->billing_type,
                'delivery_days' => $selRate->delivery_days,
                'delivery_date' => $selRate->delivery_date,
                'delivery_date_guaranteed' => $selRate->delivery_date_guaranteed,
                'est_delivery_days' => $selRate->est_delivery_days,
            ];

            unset( $general['selected_rate'] );

        }
        

        // Rates
        $rates = [];
        if( !empty($res->rates) ) {
            foreach($res->rates as $key=>$rate) {
                $rates[$key] = [
                    'id' => $rate->id,
                    'carrier' => $rate->carrier,
                    'service' => $rate->service,
                    'rate' => $rate->rate,
                    'currency' => $rate->currency,
                    'retail_rate' => $rate->retail_rate,
                    'retail_currency' => $rate->retail_currency,
                    'list_rate' => $rate->list_rate,
                    'list_currency' => $rate->list_currency,
                    'delivery_days' => $rate->delivery_days,
                    'delivery_date' => $rate->delivery_date,
                    'delivery_date_guaranteed' => $rate->delivery_date_guaranteed,
                    'est_delivery_days' => $rate->est_delivery_days,
                ];

                if( in_array('rates', $attachObjects) ) {
                    $rates[$key]['Object'] = $rate;
                }

            }   
        }

        return [
            'general' => $general,
            'from_address' => $addressFrom,
            'to_address' => $addressTo,
            'parcel' => $parcel,
            'postage_label' => $postageLabel ?? null,
            'selected_rate' => $selectedRate ?? null,
            'rates' => $rates,
        ];

    }

    public function getPredefinedPackages(array $carriers = []): array
    {

        $api_key = $this->apiKey;

        $query = ['types' => 'predefined_packages'];
        if (!empty($carriers)) {
            $query['carriers'] = implode(',', $carriers);
        }

        $url = add_query_arg($query, 'https://api.easypost.com/v2/metadata/carriers');

        $args = [
            'headers' => [
                // Basic auth: username = API key, password = blank
                'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new RuntimeException('HTTP request failed: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            // Keep original API payload for debugging
            throw new RuntimeException('EasyPost metadata error: ' . ($body ?: json_encode($data)));
        }

        return $data['carriers'] ?? [];

    }

}