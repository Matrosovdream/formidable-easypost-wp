<?php

class FrmEasypostAddressApi extends FrmEasypostAbstractApi {

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