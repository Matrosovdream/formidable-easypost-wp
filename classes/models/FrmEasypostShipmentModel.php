<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipments */
    private const SORTABLE = [
        'id',
        'easypost_id',
        'entry_id',
        'is_return',
        'status',
        'tracking_code',
        'tracking_url',
        'refund_status',
        'mode',
        'created_at',
        'updated_at',
    ];

    protected $addressModel;
    protected $parcelModel;
    protected $labelModel;
    protected $rateModel;

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipments';

        // Models
        $this->addressModel = new FrmEasypostShipmentAddressModel();
        $this->parcelModel = new FrmEasypostShipmentParcelModel();
        $this->labelModel = new FrmEasypostShipmentLabelModel();
        $this->rateModel = new FrmEasypostShipmentRateModel();
    }

    /**
     * Base list query for frm_easypost_shipments.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - easypost_id (string)
     *  - entry_id (int)
     *  - tracking_code (string)
     *  - status (string)
     *  - refund_status (string)
     *  - mode (string: test|production)
     *  - is_return (0|1)
     *  - created_from / created_to (Y-m-d or datetime)
     *  - updated_from / updated_to (Y-m-d or datetime)
     *  - search (string; LIKE on easypost_id, tracking_code, status, refund_status, mode)
     *
     * $opts:
     *  - order_by (one of self::SORTABLE) default 'created_at'
     *  - order ('ASC'|'DESC') default 'DESC'
     *  - limit (int) default 50
     *  - offset (int) default 0 (or use page/per_page)
     *  - page, per_page (ints) — convenience
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( ! empty( $filter['easypost_id'] ) ) { $where[] = 'easypost_id = %s'; $params[] = (string) $filter['easypost_id']; }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }
        if ( ! empty( $filter['tracking_code'] ) ) { $where[] = 'tracking_code = %s'; $params[] = (string) $filter['tracking_code']; }
        if ( ! empty( $filter['status'] ) ) { $where[] = 'status = %s'; $params[] = (string) $filter['status']; }
        if ( ! empty( $filter['refund_status'] ) ) { $where[] = 'refund_status = %s'; $params[] = (string) $filter['refund_status']; }
        if ( ! empty( $filter['mode'] ) ) { $where[] = 'mode = %s'; $params[] = (string) $filter['mode']; }
        if ( isset( $filter['is_return'] ) && $filter['is_return'] !== '' ) { $where[] = 'is_return = %d'; $params[] = (int) $filter['is_return']; }

        if ( ! empty( $filter['created_from'] ) ) { $where[] = 'created_at >= %s'; $params[] = $this->dateToMysql( $filter['created_from'] ); }
        if ( ! empty( $filter['created_to'] ) )   { $where[] = 'created_at <= %s'; $params[] = $this->dateToMysql( $filter['created_to'] ); }
        if ( ! empty( $filter['updated_from'] ) ) { $where[] = 'updated_at >= %s'; $params[] = $this->dateToMysql( $filter['updated_from'] ); }
        if ( ! empty( $filter['updated_to'] ) )   { $where[] = 'updated_at <= %s'; $params[] = $this->dateToMysql( $filter['updated_to'] ); }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(easypost_id LIKE %s OR tracking_code LIKE %s OR status LIKE %s OR refund_status LIKE %s OR mode LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = isset( $opts['order_by'] ) && in_array( $opts['order_by'], self::SORTABLE, true ) ? $opts['order_by'] : 'created_at';
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

    /** Get by entry_id */
    public function getByEntryId( int $entryId ) {
        $rows = $this->getList( [ 'entry_id' => $entryId ], [ 'limit' => 1 ] );

        foreach ( $rows as $key => $row ) {
            $rows[$key] = $this->attachModelData( $row );
        }

        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** All shipments by WP entry id */
    public function getAllByEntryId( int $entryId, array $opts = [] ) {
        $rows = $this->getList( [ 'entry_id' => $entryId ], $opts );

        foreach ( $rows as $key => $row ) {
            $rows[$key] = $this->attachModelData( $row );
        }

        return $rows;
    }

    /** Single shipment by EasyPost shipment id (e.g., shp_xxx) */
    public function getByEasypostId( string $easypostId ) {
        $rows = $this->getList( [ 'easypost_id' => $easypostId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Single shipment by tracking code */
    public function getByTrackingCode( string $trackingCode ) {
        $rows = $this->getList( [ 'tracking_code' => $trackingCode ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    private function attachModelData( array $shipment ) {
        
        // Addresses 
        $addresses = $this->addressModel->getAllByEntryId( $shipment['entry_id'] );
        // Split into to/from
        $to = [];
        $from = [];
        foreach ( $addresses as $a ) {
            if( $a['address_type'] === 'to' ) {
                $to = $a;
            } elseif ( $a['address_type'] === 'from' ) {
                $from = $a;
            }
        }

        // Parcel
        $parcel = $this->parcelModel->getByEntryId( $shipment['entry_id'] );

        // Labels
        $label = $this->labelModel->getByShipmentId( $shipment['easypost_id'] );

        // Rate
        $rate = $this->rateModel->getByEntryId( $shipment['entry_id'] );

        return array_merge( $shipment, [
            'addresses' => [
                'to' => $to,
                'from' => $from,
            ],
            'parcel' => $parcel,
            'label' => $label,
            'rate' => $rate,
            'is_refundable' => $this->isRefundable( $shipment )
        ] );

    }

    public function isRefundable( array $shipment ): bool {

        $allowedStatuses = [
            '', // Even empty
            'unknown',
            'pre_transit',
        ];

        if( 
            ! in_array( $shipment['status'], $allowedStatuses, true )
            ) {
            return false;
        }

        return true;
    }

    /**
     * Bulk upsert for frm_easypost_shipments.
     * Each $row should contain keys that map to the table columns below.
     * Unique key is 'easypost_id'.
     */
    public function multipleUpdateCreate( array $rows ) {
        $cols = [
            'easypost_id',
            'entry_id',
            'is_return',
            'status',
            'tracking_code',
            'tracking_url',
            'refund_status',
            'mode',
            'created_at',
            'updated_at',
        ];

        $formats = [
            'easypost_id'   => '%s',
            'entry_id'      => '%d',
            'is_return'     => '%d',
            'status'        => '%s',
            'tracking_code' => '%s',
            'tracking_url'  => '%s',
            'refund_status' => '%s',
            'mode'          => '%s',
            'created_at'    => '%s',
            'updated_at'    => '%s',
        ];

        // Convert incoming ISO8601 to MySQL DATETIME if present
        foreach ( $rows as &$r ) {
            if ( isset( $r['created_at'] ) && $r['created_at'] !== '' ) {
                $r['created_at'] = $this->dateToMysql( (string) $r['created_at'] );
            }
            if ( isset( $r['updated_at'] ) && $r['updated_at'] !== '' ) {
                $r['updated_at'] = $this->dateToMysql( (string) $r['updated_at'] );
            }
            if ( isset( $r['is_return'] ) ) {
                $r['is_return'] = (int) ! empty( $r['is_return'] );
            }
        }
        unset($r);

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'easypost_id' );
    }

}
