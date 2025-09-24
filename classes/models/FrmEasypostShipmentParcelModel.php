<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentParcelModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipment_parcel */
    private const SORTABLE = [
        'id',
        'easypost_id',
        'easypost_shipment_id',
        'entry_id',
        'length',
        'width',
        'height',
        'weight',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipment_parcel';
    }

    /**
     * Base list query for frm_easypost_shipment_parcel.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - easypost_id (string)
     *  - entry_id (int)
     *  - length_from / length_to (float)
     *  - width_from / width_to (float)
     *  - height_from / height_to (float)
     *  - weight_from / weight_to (float)
     *  - search (LIKE on easypost_id)
     *
     * $opts:
     *  - order_by (one of self::SORTABLE) default 'id'
     *  - order ('ASC'|'DESC') default 'DESC'
     *  - limit (int) default 50
     *  - offset (int) default 0 (or use page/per_page)
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( ! empty( $filter['easypost_id'] ) ) { $where[] = 'easypost_id = %s'; $params[] = (string) $filter['easypost_id']; }
        if ( ! empty( $filter['easypost_shipment_id'] ) ) { $where[] = 'easypost_shipment_id = %s'; $params[] = (string) $filter['easypost_shipment_id']; }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }

        // Ranged filters
        if ( isset( $filter['length_from'] ) && $filter['length_from'] !== '' ) { $where[] = 'length >= %f'; $params[] = (float) $filter['length_from']; }
        if ( isset( $filter['length_to'] ) && $filter['length_to'] !== '' )     { $where[] = 'length <= %f'; $params[] = (float) $filter['length_to']; }

        if ( isset( $filter['width_from'] ) && $filter['width_from'] !== '' )   { $where[] = 'width >= %f'; $params[] = (float) $filter['width_from']; }
        if ( isset( $filter['width_to'] ) && $filter['width_to'] !== '' )       { $where[] = 'width <= %f'; $params[] = (float) $filter['width_to']; }

        if ( isset( $filter['height_from'] ) && $filter['height_from'] !== '' ) { $where[] = 'height >= %f'; $params[] = (float) $filter['height_from']; }
        if ( isset( $filter['height_to'] ) && $filter['height_to'] !== '' )     { $where[] = 'height <= %f'; $params[] = (float) $filter['height_to']; }

        if ( isset( $filter['weight_from'] ) && $filter['weight_from'] !== '' ) { $where[] = 'weight >= %f'; $params[] = (float) $filter['weight_from']; }
        if ( isset( $filter['weight_to'] ) && $filter['weight_to'] !== '' )     { $where[] = 'weight <= %f'; $params[] = (float) $filter['weight_to']; }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(easypost_id LIKE %s)';
            $params[] = $like;
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = isset( $opts['order_by'] ) && in_array( $opts['order_by'], self::SORTABLE, true ) ? $opts['order_by'] : 'id';
        $order   = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $limit  = isset( $opts['limit'] ) ? max( 1, (int) $opts['limit'] ) : 50;
        $offset = isset( $opts['offset'] ) ? max( 0, (int) $opts['offset'] ) : 0;
        if ( isset( $opts['page'] ) || isset( $opts['per_page'] ) ) {
            $pp     = isset( $opts['per_page'] ) ? max( 1, (int) $opts['per_page'] ) : 50;
            $page   = isset( $opts['page'] ) ? max( 1, (int) $opts['page'] ) : 1;
            $limit  = $pp;
            $offset = ( $page - 1 ) * $pp;
        }

        $sql  = "SELECT * FROM {$this->table} {$whereSql} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $args = array_merge( $params, [ $limit, $offset ] );
        $prepared = $this->db->prepare( $sql, $args );
        if ( false === $prepared ) {
            return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'easypost-wp' ) );
        }
        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( null === $rows ) {
            return new WP_Error( 'db_query_failed', __( 'Database query failed.', 'easypost-wp' ), [ 'last_error' => $this->db->last_error ] );
        }
        return $rows;
    }

    /** Convenience: get by entry id */
    public function getAllByEntryId( int $entryId, array $opts = [] ) {
        return $this->getList( [ 'entry_id' => $entryId ], $opts );
    }

    /** Get by EasyPost object id (e.g., prcl_..., if you map that here) */
    public function getByEasypostId( string $easypostId ) {
        $rows = $this->getList( [ 'easypost_id' => $easypostId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Single label by shipment_id */
    public function getByShipmentId( string $shipmentId ) {
        $rows = $this->getList( [ 'easypost_shipment_id' => $shipmentId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /**
     * Bulk upsert for frm_easypost_shipment_parcel.
     * Unique key is 'easypost_id'.
     */
    public function multipleUpdateCreate( array $rows ) {
        $cols = [
            'easypost_id',
            'easypost_shipment_id',
            'entry_id',
            'length',
            'width',
            'height',
            'weight',
        ];

        $formats = [
            'easypost_id' => '%s',
            'easypost_shipment_id' => '%s',
            'entry_id'    => '%d',
            'length'      => '%f',
            'width'       => '%f',
            'height'      => '%f',
            'weight'      => '%f',
        ];

        // Normalize types
        foreach ( $rows as &$r ) {
            if ( isset($r['entry_id']) && $r['entry_id'] !== '' ) { $r['entry_id'] = (int) $r['entry_id']; }
            foreach ( ['length','width','height','weight'] as $k ) {
                if ( isset($r[$k]) && $r[$k] !== '' ) { $r[$k] = (float) $r[$k]; }
            }
        }
        unset($r);

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'easypost_id' );
    }
}
