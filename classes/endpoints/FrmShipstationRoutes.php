<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationRoutes {
    private FrmShipstationApi $api;

    public function __construct() {

        $this->api = new FrmShipstationApi();

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'frm-shipstation/v1';

        // 1) getOrder
        register_rest_route( $ns, '/order', [
            'methods'  => 'GET',
            'callback' => [ $this, 'r_get_order' ],
            'permission_callback' => [ $this, 'perm_admin_or_frm_owner' ],
            'args' => [
                'id'           => [ 'type' => 'integer', 'required' => false ],
                'number'       => [ 'type' => 'string',  'required' => false ],
                'store_id'     => [ 'type' => 'integer', 'required' => false ],
                // For non-admins this is required and must belong to the current user
                'frm_entry_id' => [ 'type' => 'integer', 'required' => false ],
            ],
        ] );

        // 2) getShipments
        register_rest_route( $ns, '/shipments', [
            'methods'  => 'GET',
            'callback' => [ $this, 'r_get_shipments' ],
            'permission_callback' => [ $this, 'perm_admin_or_frm_owner' ],
            'args' => [
                'id'           => [ 'type' => 'integer', 'required' => false ],
                'number'       => [ 'type' => 'string',  'required' => false ],
                'store_id'     => [ 'type' => 'integer', 'required' => false ],
                'frm_entry_id' => [ 'type' => 'integer', 'required' => false ],
            ],
        ] );

        // 3) createOrderLabel (admins only)
        register_rest_route( $ns, '/orders/label', [
            'methods'  => 'POST',
            'callback' => [ $this, 'r_create_order_label' ],
            'permission_callback' => [ $this, 'perm_admin_only' ],
            'args' => [
                'order_id'     => [ 'type' => 'integer', 'required' => false ],
                'order_number' => [ 'type' => 'string',  'required' => false ],
                'store_id'     => [ 'type' => 'integer', 'required' => false ],
                'carrier_code' => [ 'type' => 'string',  'required' => false ],
                'service_code' => [ 'type' => 'string',  'required' => false ],
                'package_code' => [ 'type' => 'string',  'required' => false ],
                'confirmation' => [ 'type' => 'string',  'required' => false ],
                'weight_value' => [ 'type' => 'number',  'required' => false ],
                'weight_units' => [ 'type' => 'string',  'required' => false, 'enum' => [ 'ounces','pounds','grams','kilograms' ] ],
                'ship_date'    => [ 'type' => 'string',  'required' => false, 'description' => 'YYYY-MM-DD' ],
                'test_label'   => [ 'type' => 'boolean', 'required' => false ],
                'dimensions'   => [ 'type' => 'object',  'required' => false ],
            ],
        ] );

        // 4) voidOrderLabel (admins only)
        register_rest_route( $ns, '/labels/void', [
            'methods'  => 'POST',
            'callback' => [ $this, 'r_void_label' ],
            'permission_callback' => [ $this, 'perm_admin_only' ],
            'args' => [
                'label_id' => [ 'type' => 'integer', 'required' => true ],
            ],
        ] );
    }

    // ------------------ Permissions ------------------

    /** Admins only */
    public function perm_admin_only(): bool|WP_Error {
        return current_user_can( 'manage_options' ) ? true : new WP_Error( 'forbidden', __( 'Admins only.', 'shipstation-wp' ), [ 'status' => 403 ] );
    }

    /** Admins OR owner of provided Formidable entry id */
    public function perm_admin_or_frm_owner( WP_REST_Request $req ) {
        $user = wp_get_current_user(); 
        if ( ! $user || ! $user->exists() ) {
            return new WP_Error('rest_not_logged_in', __('Authentication required.', 'shipstation-wp'), ['status' => 401]);
        }
        if ( user_can($user, 'manage_options') ) {
            return true;
        }
        $entry_id = (int) $req->get_param('frm_entry_id');
        if ( $entry_id <= 0 ) {
            return new WP_Error('forbidden', __('frm_entry_id is required for non-admins.', 'shipstation-wp'), ['status' => 403]);
        }
        if ( $this->user_owns_frm_entry($entry_id, (int)$user->ID) ) {
            return true;
        }
        return new WP_Error('forbidden', __('You do not own this entry.', 'shipstation-wp'), ['status' => 403]);
    }    

    /**
     * Ownership check using Formidable entries table.
     * Falls back to direct DB query so it works without loading Formidable classes.
     */
    private function user_owns_frm_entry( int $entry_id, int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'frm_items';
        $owner = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d LIMIT 1", $entry_id ) );
        if ( $owner !== null && (int) $owner === $user_id ) { return true; }

        // Extension point: allow custom ownership logic if your form stores user id differently
        $entry = null;
        if ( class_exists( 'FrmEntry' ) ) {
            $entry = \FrmEntry::getOne( $entry_id, true );
            if ( $entry && isset( $entry->user_id ) && (int) $entry->user_id === $user_id ) { return true; }
        }
        return (bool) apply_filters( 'shipstation_wp_entry_owner_check', false, $entry, $user_id );
    }

    // ------------------ Handlers ------------------

    public function r_get_order( WP_REST_Request $req ) {
        $args = [
            'id'       => $req->get_param( 'id' ),
            'number'   => $req->get_param( 'number' ),
            'store_id' => $req->get_param( 'store_id' ),
        ];
        $out = $this->api->getOrder( array_filter( $args, fn($v)=>$v!==null && $v!=='' ) );
        return rest_ensure_response( $out );
    }

    public function r_get_shipments( WP_REST_Request $req ) {
        $args = [
            'id'       => $req->get_param( 'id' ),
            'number'   => $req->get_param( 'number' ),
            'store_id' => $req->get_param( 'store_id' ),
        ];
        $out = $this->api->getShipmentsByOrder( array_filter( $args, fn($v)=>$v!==null && $v!=='' ) );
        return rest_ensure_response( $out );
    }

    public function r_create_order_label( WP_REST_Request $req ) {
        $params = [
            'order_id'     => $req->get_param( 'order_id' ),
            'order_number' => $req->get_param( 'order_number' ),
            'store_id'     => $req->get_param( 'store_id' ),
            'carrier_code' => $req->get_param( 'carrier_code' ),
            'service_code' => $req->get_param( 'service_code' ),
            'package_code' => $req->get_param( 'package_code' ),
            'confirmation' => $req->get_param( 'confirmation' ),
            'weight_value' => $req->get_param( 'weight_value' ),
            'weight_units' => $req->get_param( 'weight_units' ),
            'ship_date'    => $req->get_param( 'ship_date' ),
            'test_label'   => $req->get_param( 'test_label' ),
            'dimensions'   => $req->get_param( 'dimensions' ),
        ];
        $out = $this->api->createOrderLabel( array_filter( $params, fn($v)=>$v!==null && $v!=='' ) );
        return rest_ensure_response( $out );
    }

    public function r_void_label( WP_REST_Request $req ) {
        $out = $this->api->voidLabel( $req->get_param( 'label_id' ) );
        return rest_ensure_response( $out );
    }
}


// Bootstrap when both WP and client are available
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'FrmShipstationApi' ) ) {
        new FrmShipstationRoutes( new FrmShipstationApi() );
    }
} );
