<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentStatusModel extends FrmEasypostAbstractModel {

    public function __construct() {

    }

    /**
     * All Statuses are hardcoded for now
     */
    public function getList() {
        
        return [
            'unknown'      => __( 'Unknown', 'easypost-wp' ),
            'pre_transit'  => __( 'Pre-Transit', 'easypost-wp' ),
            'in_transit'   => __( 'In Transit', 'easypost-wp' ),
            'out_for_delivery' => __( 'Out for Delivery', 'easypost-wp' ),
            'delivered'    => __( 'Delivered', 'easypost-wp' ),
            'return_to_sender' => __( 'Return to Sender', 'easypost-wp' ),
            'available_for_pickup' => __( 'Available for Pickup', 'easypost-wp' ),
            'failure'      => __( 'Failure', 'easypost-wp' ),
            'cancelled'    => __( 'Cancelled', 'easypost-wp' ),
        ];  

    }

}
