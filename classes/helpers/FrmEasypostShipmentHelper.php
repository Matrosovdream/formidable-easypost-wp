<?php

class FrmEasypostShipmentHelper {

    protected $shipmentApi;
    protected $shipmentModel;
    protected $addressModel;
    protected $parcelModel;
    protected $labelModel;
    protected $rateModel;

    public function __construct() {
        $this->shipmentApi = new FrmEasypostShipmentApi();
        $this->shipmentModel = new FrmEasypostShipmentModel();
        $this->addressModel = new FrmEasypostShipmentAddressModel();
        $this->parcelModel = new FrmEasypostShipmentParcelModel();
        $this->labelModel = new FrmEasypostShipmentLabelModel();
        $this->rateModel = new FrmEasypostShipmentRateModel();
    }

    public function updateShipmentApi( string $shipmentId ) {

        $shipment = $this->shipmentApi->getShipmentById( $shipmentId );

        // Update shipment in DB
        $this->updateShipmentsDB( [$shipment] );

    }

    public function updateShipmentsApi( array $filter = [] ) {

        if( isset( $filter['beforeId'] ) ) {
            $beforeId = $filter['beforeId'];
        }

        if( isset( $filter['afterId'] ) ) {
            $afterId = $filter['afterId'];
        }

        // Get orders from ShipStation API
        $shipments = $this->shipmentApi->getAllShipments(
            100, 
            isset($beforeId) ? $beforeId : null, 
            isset($afterId) ? $afterId : null
        );

        // Split into chunks
        $chunks = array_chunk($shipments, 300);
        foreach( $chunks as $chunk ) {
            $this->updateShipmentsDB( $chunk );
        }

        //return $this->updateShipmentsDB( $shipments );

    }

    private function updateShipmentsDB( array $shipments ) {

        if ( empty($shipments) ) {
            return 0;
        }

        // Prepare orders for update/create
        $shipmentsProcessed = [];
        foreach( $shipments as $item ) {  

            // General info
            $general = $item['general'];
            $shipmentsProcessed[] = [
                'easypost_id' => $general['id'],
                'entry_id' => (int) $general['reference'],
                'is_return' => !empty($general['is_return']) ? 1 : 0,
                'status' => !empty($general['status']) ? $general['status'] : null,
                'tracking_code' => !empty($general['tracking_code']) ? $general['tracking_code'] : null,
                'tracking_url' => !empty($general['tracking_url']) ? $general['tracking_url'] : null,
                'refund_status' => !empty($general['refund_status']) ? $general['refund_status'] : null,
                'mode' => !empty($general['mode']) ? $general['mode'] : 'test',
                'created_at' => !empty($general['created_at']) ? date('Y-m-d H:i:s', strtotime($general['created_at'])) : null,
                'updated_at' => !empty($general['updated_at']) ? date('Y-m-d H:i:s', strtotime($general['updated_at'])) : null,
            ];

        }

        // Update records
        $this->shipmentModel->multipleUpdateCreate( $shipmentsProcessed );

        // Update addresses
        $this->updateShipmentAddresses( $shipments );

        // Update parcel
        $this->updateShipmentParcel( $shipments );

        // Update label
        $this->updateShipmentLabel( $shipments );

        // Update rate
        $this->updateShipmentRate( $shipments );

    }

    public function updateShipmentRate( array $shipments ) {
        $itemsProcessed = [];
        foreach( $shipments as $item ) {  

            if ( empty($item['rates']) ) {
                continue;
            }

            // Rate info   
            $data = $item['selected_rate'];
            $itemsProcessed[] = [
                'easypost_id' => $data['id'],
                'easypost_shipment_id' => $item['general']['id'],
                'entry_id' => (int) $data['reference'],
                'mode' => !empty($data['mode']) ? $data['mode'] : 'test',
                'service' => !empty($data['service']) ? $data['service'] : null,
                'carrier' => !empty($data['carrier']) ? $data['carrier'] : null,
                'rate' => !empty($data['rate']) ? $data['rate'] : null,
                'currency' => !empty($data['currency']) ? $data['currency'] : null,
                'retail_rate' => !empty($data['retail_rate']) ? $data['retail_rate'] : null,
                'retail_currency' => !empty($data['retail_currency']) ? $data['retail_currency'] : null,
                'list_rate' => !empty($data['list_rate']) ? $data['list_rate'] : null,
                'list_currency' => !empty($data['list_currency']) ? $data['list_currency'] : null,
                'billing_type' => !empty($data['billing_type']) ? $data['billing_type'] : null,
                'delivery_days' => isset($data['delivery_days']) ? (int)$data['delivery_days'] : null,
                'delivery_date' => !empty($data['delivery_date']) ? date('Y-m-d H:i:s', strtotime($data['delivery_date'])) : null,
                'delivery_date_guaranteed' => isset($data['delivery_date_guaranteed']) ? (int)$data['delivery_date_guaranteed'] : null,
                'est_delivery_days' => isset($data['est_delivery_days']) ? (int)$data['est_delivery_days'] : null,
            ];

        }

        $this->rateModel->multipleUpdateCreate( $itemsProcessed );

    }

    public function updateShipmentLabel( array $shipments ) {
        $itemsProcessed = [];
        foreach( $shipments as $item ) {  

            if ( empty($item['postage_label']) ) {
                continue;
            }

            // Label info
            $data = $item['postage_label'];
            $itemsProcessed[] = [
                'easypost_id' => $data['id'],
                'easypost_shipment_id' => $item['general']['id'],
                'entry_id' => (int) $data['reference'],
                'date_advance' => !empty($data['date_advance']) ? (int)$data['date_advance'] : 0,
                'integrated_form' => !empty($data['integrated_form']) ? $data['integrated_form'] : null,
                'label_date' => !empty($data['label_date']) ? date('Y-m-d H:i:s', strtotime($data['label_date'])) : null,
                'label_resolution' => !empty($data['label_resolution']) ? (int)$data['label_resolution'] : null,
                'label_size' => !empty($data['label_size']) ? $data['label_size'] : null,
                'label_type' => !empty($data['label_type']) ? $data['label_type'] : null,
                'label_file_type' => !empty($data['label_file_type']) ? $data['label_file_type'] : null,
                'label_url' => !empty($data['label_url']) ? $data['label_url'] : null,
                'label_pdf_url' => !empty($data['label_pdf_url']) ? $data['label_pdf_url'] : null,
                'label_zpl_url' => !empty($data['label_zpl_url']) ? $data['label_zpl_url'] : null,
                'label_epl2_url' => !empty($data['label_epl2_url']) ? $data['label_epl2_url'] : null,
                'created_at' => !empty($data['created_at']) ? date('Y-m-d H:i:s', strtotime($data['created_at'])) : null,
                'updated_at' => !empty($data['updated_at']) ? date('Y-m-d H:i:s', strtotime($data['updated_at'])) : null,
            ];

        }

        $this->labelModel->multipleUpdateCreate( $itemsProcessed );

    }                

    public function updateShipmentParcel( array $shipments ) {
        $itemsProcessed = [];
        foreach( $shipments as $item ) {  

            if ( empty($item['parcel']) ) {
                continue;
            }

            // Parcel info
            $data = $item['parcel'];
            $itemsProcessed[] = [
                'easypost_id' => $data['id'],
                'easypost_shipment_id' => $item['general']['id'],
                'entry_id' => (int) $data['reference'],
                'length' => !empty($data['length']) ? $data['length'] : null,
                'width' => !empty($data['width']) ? $data['width'] : null,
                'height' => !empty($data['height']) ? $data['height'] : null,
                'weight' => !empty($data['weight']) ? $data['weight'] : null,
            ];

        }

        $this->parcelModel->multipleUpdateCreate( $itemsProcessed );

    }


    public function updateShipmentAddresses( array $shipments ) {

        $tabs = ['from_address', 'to_address'];

        $itemsProcessed = [];
        foreach( $shipments as $item ) {  

            foreach( $tabs as $tab ) {
                if ( empty($item[$tab]) ) {
                    continue;
                }

                $type = str_replace('_address', '', $tab);
                
                // Addresses info
                $data = $item[$tab];
                $itemsProcessed[] = [
                    'easypost_id' => $data['id'],
                    'easypost_shipment_id' => $item['general']['id'],
                    'entry_id' => (int) $item['general']['reference'],
                    'address_type' => $type,
                    'name' => !empty($data['name']) ? $data['name'] : null,
                    'company' => !empty($data['company']) ? $data['company'] : null,
                    'street1' => !empty($data['street1']) ? $data['street1'] : null,
                    'street2' => !empty($data['street2']) ? $data['street2'] : null,
                    'city' => !empty($data['city']) ? $data['city'] : null,
                    'state' => !empty($data['state']) ? $data['state'] : null,
                    'zip' => !empty($data['zip']) ? $data['zip'] : null,
                    'country' => !empty($data['country']) ? $data['country'] : null,
                    'phone' => !empty($data['phone']) ? $data['phone'] : null,
                    'email' => !empty($data['email']) ? strtolower($data['email']) : null,
                ];

            }

        }

        $this->addressModel->multipleUpdateCreate( $itemsProcessed );

    }
 
    public function updateApiByTrackingNumber( $trackingNumber ) {
        if ( empty( $trackingNumber ) ) {
            return new WP_Error( 'shipstation_trackingnumber_required', __( 'trackingNumber is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->updateShipmentsApi( ['trackingNumber' => $trackingNumber] );
    }

    public function voidShipment( string $shipmentId ) {

        if ( empty( $shipmentId ) ) {
            return ;
        }

        $result = $this->shipmentApi->refundShipment( $shipmentId );

        if( 
            !is_wp_error( $result ) &&
            $result['ok']
            ) {
            // Update shipment in DB
            $this->updateShipmentApi( $shipmentId );
            return true;
        }

        // Prepare errors
        if( !$result['ok'] ) {
            $errors = [];
            foreach( $result['errors'] as $error ) {
                $errors[] = $error['message'];
            }
            $result['errors'] = $errors;
        }

        return $result;

    }

}