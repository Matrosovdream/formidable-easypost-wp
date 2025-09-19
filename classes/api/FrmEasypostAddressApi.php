<?php

class FrmEasypostAddressApi extends FrmEasypostAbstractApi {

    public function getAllAddresses(
        int $pageSize = 100,
        string $beforeId = '',
        string $afterId = ''
    ): array {
        $params = [
            'page_size' => $pageSize,
        ];
    
        if (!empty($beforeId)) {
            $params['before_id'] = $beforeId;
        }
    
        if (!empty($afterId)) {
            $params['after_id'] = $afterId;
        }
    
        $addresses = [];
    
        do {
            $res = $this->client->address->all($params);
    
            $errors = $this->handleErrors($res);
            if (!empty($errors)) {
                return $errors;
            }
    
            foreach ($res->addresses as $address) {
                $addresses[] = $this->prepareAddressResponse($address);
            }
    
            // Prepare params for the next page if available
            if ($res->has_more && !empty($res->addresses)) {
                // Use the last object id for pagination
                $last = end($res->addresses);
                $params['after_id'] = $last->id;
            } else {
                break;
            }

            sleep(1);
    
        } while ($res->has_more);
    
        return $addresses;
    }    

    protected function prepareAddressResponse( $address ): array {

        return [
            'easypost_id'  => $address->id,
            'name'         => $address->name,
            'company'      => $address->company,
            'street1'      => $address->street1,
            'street2'      => $address->street2,
            'city'         => $address->city,
            'state'        => $address->state,
            'zip'          => $address->zip,
            'country'      => $address->country,
            'phone'        => $address->phone,
            'email'        => $address->email,
            'residential'  => $address->residential,
            'carrier_facility' => $address->carrier_facility,
            'federal_tax_id'   => $address->federal_tax_id,
            'state_tax_id'     => $address->state_tax_id,
            'created_at'      => isset($address->created_at) ? $address->created_at : null,
            'updated_at'      => isset($address->updated_at) ? $address->updated_at : null,
        ];

    }

    public function createAddress( array $data ): array {

        $res = $this->client->address->create($data);

        $errors = $this->handleErrors($res);

        if( empty($errors) ) {
            $res = $this->prepareAddressResponse( $res );
        }

        return $errors ? $errors : $res;

    }

    public function verifyAddressById( string $addressId, array $params = [] ): array {

        $res = $this->client->address->verify($addressId, $params);

        $errors = $this->handleErrors($res);

        if( empty($errors) ) {
            $res = $this->prepareAddressResponse( $res );
        }

        return $errors ? $errors : $res;

    }

    public function verifyAddress(array $address, bool $strict = false): array
    {
        $created = null;

        try {
            if ($strict) {
                $created = $this->client->address->create(array_merge($address, [
                    'verify_strict' => ['delivery'],
                ]));

                $result = [
                    'ok'      => true,
                    'address' => $created,
                    'raw'     => $created,
                ];
            } else {
                $created = $this->client->address->create(array_merge($address, [
                    'verify' => ['delivery'],
                ]));

                $delivery = $created->verifications->delivery ?? null;
                $errors   = $delivery && (!$delivery->success ?? false)
                    ? ($delivery->errors ?? [])
                    : [];

                $result = [
                    'ok'      => empty($errors),
                    'address' => $created,
                    'errors'  => $this->normalizeEasyPostErrors($errors),
                    'raw'     => $created,
                ];
            }
        } catch (InvalidRequestException $e) {
            $result = $this->formatEasyPostException($e);
        } catch (ApiException $e) {
            $result = $this->formatEasyPostException($e);
        } finally {
            // Always clean up the address object if we created it
            if ($created && isset($created->id)) {
                try {
                    $this->client->address->delete($created->id);
                } catch (\Throwable $delErr) {
                    // Optional: log but don't break verification result
                    error_log('EasyPost delete address failed: ' . $delErr->getMessage());
                }
            }
        }

        return $result;
    }

}