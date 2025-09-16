<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentRateModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipment_rate */
    private const SORTABLE = [
        'id',
        'easypost_id',
        'entry_id',
        'mode',
        'service',
        'carrier',
        'rate',
        'currency',
        'retail_rate',
        'retail_currency',
        'list_rate',
        'list_currency',
        'billing_type',
        'delivery_days',
        'delivery_date',
        'delivery_date_guaranteed',
        'est_delivery_days',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipment_rate';
    }

    /**
     * Base list query for frm_easypost_shipment_rate.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - easypost_id (string)
     *  - entry_id (int)
     *  - mode (string)
     *  - service (string)
     *  - carrier (string)
     *  - currency, retail_currency, list_currency (string)
     *  - billing_type (string)
     *  - delivery_days (int)
     *  - delivery_date_from / delivery_date_to (Y-m-d or datetime)
     *  - delivery_date_guaranteed (0|1)
     *  - est_delivery_days (int)
     *  - created_from / created_to (Y-m-d or datetime)
     *  - updated_from / updated_to (Y-m-d or datetime)
     *  - search (LIKE on easypost_id, service, carrier, billing_type)
     *
     * $opts:
     *  - order_by (one of self::SORTABLE) default 'created_at'
     *  - order ('ASC'|'DESC') default 'DESC'
     *  - limit (int) default 50
     *  - offset (int) default 0 (or use page/per_page)
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( ! empty( $filter['easypost_id'] ) ) { $where[] = 'easypost_id = %s'; $params[] = (string) $filter['easypost_id']; }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }
        if ( ! empty( $filter['mode'] ) ) { $where[] = 'mode = %s'; $params[] = (string) $filter['mode']; }
        if ( ! empty( $filter['service'] ) ) { $where[] = 'service = %s'; $params[] = (string) $filter['service']; }
        if ( ! empty( $filter['carrier'] ) ) { $where[] = 'carrier = %s'; $params[] = (string) $filter['carrier']; }
        if ( ! empty( $filter['currency'] ) ) { $where[] = 'currency = %s'; $params[] = (string) $filter['currency']; }
        if ( ! empty( $filter['retail_currency'] ) ) { $where[] = 'retail_currency = %s'; $params[] = (string) $filter['retail_currency']; }
        if ( ! empty( $filter['list_currency'] ) ) { $where[] = 'list_currency = %s'; $params[] = (string) $filter['list_currency']; }
        if ( ! empty( $filter['billing_type'] ) ) { $where[] = 'billing_type = %s'; $params[] = (string) $filter['billing_type']; }
        if ( isset( $filter['delivery_days'] ) && $filter['delivery_days'] !== '' ) { $where[] = 'delivery_days = %d'; $params[] = (int) $filter['delivery_days']; }
        if ( isset( $filter['delivery_date_guaranteed'] ) && $filter['delivery_date_guaranteed'] !== '' ) { $where[] = 'delivery_date_guaranteed = %d'; $params[] = (int) $filter['delivery_date_guaranteed']; }
        if ( isset( $filter['est_delivery_days'] ) && $filter['est_delivery_days'] !== '' ) { $where[] = 'est_delivery_days = %d'; $params[] = (int) $filter['est_delivery_days']; }

        if ( ! empty( $filter['delivery_date_from'] ) ) { $where[] = 'delivery_date >= %s'; $params[] = $this->dateToMysql( $filter['delivery_date_from'] ); }
        if ( ! empty( $filter['delivery_date_to'] ) )   { $where[] = 'delivery_date <= %s'; $params[] = $this->dateToMysql( $filter['delivery_date_to'] ); }

        if ( ! empty( $filter['created_from'] ) ) { $where[] = 'created_at >= %s'; $params[] = $this->dateToMysql( $filter['created_from'] ); }
        if ( ! empty( $filter['created_to'] ) )   { $where[] = 'created_at <= %s'; $params[] = $this->dateToMysql( $filter['created_to'] ); }
        if ( ! empty( $filter['updated_from'] ) ) { $where[] = 'updated_at >= %s'; $params[] = $this->dateToMysql( $filter['updated_from'] ); }
        if ( ! empty( $filter['updated_to'] ) )   { $where[] = 'updated_at <= %s'; $params[] = $this->dateToMysql( $filter['updated_to'] ); }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(easypost_id LIKE %s OR service LIKE %s OR carrier LIKE %s OR billing_type LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
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

    /** Convenience: all rates by entry */
    public function getAllByEntryId( int $entryId, array $opts = [] ) {
        return $this->getList( [ 'entry_id' => $entryId ], $opts );
    }

    /** One by EasyPost rate id (if you store distinct IDs per rate row) */
    public function getByEasypostId( string $easypostId ) {
        $rows = $this->getList( [ 'easypost_id' => $easypostId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Optional: narrow by carrier+service */
    public function getByCarrierService( string $carrier, string $service, array $opts = [] ) {
        $filter = [ 'carrier' => $carrier, 'service' => $service ];
        return $this->getList( $filter, $opts );
    }

    /**
     * Bulk upsert for frm_easypost_shipment_rate.
     * Unique key is 'easypost_id'.
     */
    public function multipleUpdateCreate( array $rows ) {
        $cols = [
            'easypost_id',
            'entry_id',
            'mode',
            'service',
            'carrier',
            'rate',
            'currency',
            'retail_rate',
            'retail_currency',
            'list_rate',
            'list_currency',
            'billing_type',
            'delivery_days',
            'delivery_date',
            'delivery_date_guaranteed',
            'est_delivery_days',
            'created_at',
            'updated_at',
        ];

        $formats = [
            'easypost_id'               => '%s',
            'entry_id'                  => '%d',
            'mode'                      => '%s',
            'service'                   => '%s',
            'carrier'                   => '%s',
            'rate'                      => '%f',
            'currency'                  => '%s',
            'retail_rate'               => '%f',
            'retail_currency'           => '%s',
            'list_rate'                 => '%f',
            'list_currency'             => '%s',
            'billing_type'              => '%s',
            'delivery_days'             => '%d',
            'delivery_date'             => '%s',
            'delivery_date_guaranteed'  => '%d',
            'est_delivery_days'         => '%d',
            'created_at'                => '%s',
            'updated_at'                => '%s',
        ];

        // Normalize types and convert ISO8601 -> MySQL DATETIME
        foreach ( $rows as &$r ) {
            foreach ( ['rate','retail_rate','list_rate'] as $k ) {
                if ( isset($r[$k]) && $r[$k] !== '' ) { $r[$k] = (float) $r[$k]; }
            }
            foreach ( ['entry_id','delivery_days','est_delivery_days'] as $k ) {
                if ( isset($r[$k]) && $r[$k] !== '' ) { $r[$k] = (int) $r[$k]; }
            }
            if ( isset($r['delivery_date_guaranteed']) ) {
                $r['delivery_date_guaranteed'] = (int)!empty($r['delivery_date_guaranteed']);
            }
            if ( isset($r['delivery_date']) && $r['delivery_date'] !== '' ) {
                $r['delivery_date'] = $this->dateToMysql( (string) $r['delivery_date'] );
            }
            if ( isset($r['created_at']) && $r['created_at'] !== '' ) {
                $r['created_at'] = $this->dateToMysql( (string) $r['created_at'] );
            }
            if ( isset($r['updated_at']) && $r['updated_at'] !== '' ) {
                $r['updated_at'] = $this->dateToMysql( (string) $r['updated_at'] );
            }
        }
        unset($r);

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'easypost_id' );
    }

}
