<?php

class FrmEasypostShipmentApi extends FrmEasypostAbstractApi {

    public function createShipment( array $data ) {

        $res = $this->client->shipment->create($data);

        $errors = $this->handleErrors($res);

        return $errors ? $errors : $res;

    }

    public function buyLabel( string $shipmentId, EasyPost\Rate $rate ) {

        $res = $this->client->shipment->buy($shipmentId, $rate);

        $errors = $this->handleErrors($res);

        return $errors ? $errors : $res;

    }

    public function getShipments( array $params = [], bool $autoPaginate = true ) {
    }

    /*
     * Get shipment by ID
    */
    public function getShipment( $shipmentId ) {
        if ( empty( $shipmentId ) ) {
            return new WP_Error( 'shipstation_shipmentid_required', __( 'shipmentId is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'GET', '/shipments/' . (int) $shipmentId );
    }   

    /**
     * Get shipments by order
    */
    public function getShipmentsByOrder( array $args ) {
        $query = [];
        if ( ! empty( $args['id'] ) ) {
            $query['orderId'] = (int) $args['id'];
        } elseif ( ! empty( $args['number'] ) ) {
            $query['orderNumber'] = (string) $args['number'];
        } else {
            return new WP_Error( 'shipstation_ship_param', __( 'Provide order "id" or "number".', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        $out = $this->request( 'GET', '/shipments', $query );
        if ( is_array( $out ) && isset( $out['shipments'] ) ) {
            return $out['shipments'];
        }
        return $out;
    }

    /**
     * Get labels by order (derived from shipments)
    */
    public function getLabelsByOrder( array $args, bool $includeData = false ): array|WP_Error {
        $shipments = $this->getShipmentsByOrder( $args );
        if ( is_wp_error( $shipments ) ) {
            return $shipments;
        }
        return $this->labelsFromShipments( is_array( $shipments ) ? $shipments : [], $includeData );
    }

    /**
     * Void label
    */
    public function voidLabel( $shipmentId ) {
        if ( empty( $shipmentId ) ) {
            return new WP_Error( 'shipstation_labelid_required', __( 'label_id is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'POST', '/shipments/voidlabel', [], [ 'shipmentId' => (int) $shipmentId ] );
    }

    /*
     * Get rates
    */
    public function getRates( array $shipment ): array|WP_Error {
        if ( empty( $shipment ) || ! is_array( $shipment ) ) {
            return new WP_Error( 'shipstation_shipment_required', __( 'shipment data is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }

        return $this->request( 'POST', '/shipments/getrates', [],$shipment );
    }

    /*
     * Create shipment
    */
    public function createLabel( array $shipment ): array|WP_Error {
        if ( empty( $shipment ) || ! is_array( $shipment ) ) {
            return new WP_Error( 'shipstation_shipment_required', __( 'shipment data is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }

        return $this->request( 'POST', '/shipments/createlabel', [], $shipment );
    }

    // -------------------------
    // Helpers
    // -------------------------

    /**
     * Map shipments[] -> compact labels[]
    */
    public function labelsFromShipments( array $shipments, bool $includeData = false ): array {
        $labels = [];
        foreach ( $shipments as $s ) {
            // Some shipments include labelId/labelData on the shipment object.
            if ( empty( $s['labelId'] ) && empty( $s['labelData'] ) ) { continue; }
            $labels[] = [
                'shipmentId'     => $s['shipmentId']     ?? null,
                'labelId'        => $s['labelId']        ?? null,
                'carrierCode'    => $s['carrierCode']    ?? null,
                'serviceCode'    => $s['serviceCode']    ?? null,
                'trackingNumber' => $s['trackingNumber'] ?? null,
                'shipDate'       => $s['shipDate']       ?? null,
                'labelCreateDate'=> $s['createDate']     ?? null,
                'labelData'      => $includeData ? ( $s['labelData'] ?? null ) : null,
            ];
        }
        return $labels;
    }


}