<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentLabelModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipment_label */
    private const SORTABLE = [
        'id',
        'easypost_id',
        'easypost_shipment_id',
        'entry_id',
        'date_advance',
        'integrated_form',
        'label_date',
        'label_resolution',
        'label_size',
        'label_type',
        'label_file_type',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipment_label';
    }

    /**
     * Base list query for frm_easypost_shipment_label.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - easypost_id (string)
     *  - entry_id (int)
     *  - date_advance (int)
     *  - integrated_form (string)
     *  - label_resolution (int)
     *  - label_size (string)
     *  - label_type (string)
     *  - label_file_type (string)
     *  - label_date_from / label_date_to (Y-m-d or datetime)
     *  - created_from / created_to (Y-m-d or datetime)
     *  - updated_from / updated_to (Y-m-d or datetime)
     *  - search (LIKE on easypost_id, integrated_form, label_type, label_file_type)
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
        if ( ! empty( $filter['easypost_shipment_id'] ) ) { $where[] = 'easypost_shipment_id = %s'; $params[] = (string) $filter['easypost_shipment_id']; }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }
        if ( isset( $filter['date_advance'] ) && $filter['date_advance'] !== '' ) { $where[] = 'date_advance = %d'; $params[] = (int) $filter['date_advance']; }
        if ( ! empty( $filter['integrated_form'] ) ) { $where[] = 'integrated_form = %s'; $params[] = (string) $filter['integrated_form']; }
        if ( isset( $filter['label_resolution'] ) && $filter['label_resolution'] !== '' ) { $where[] = 'label_resolution = %d'; $params[] = (int) $filter['label_resolution']; }
        if ( ! empty( $filter['label_size'] ) ) { $where[] = 'label_size = %s'; $params[] = (string) $filter['label_size']; }
        if ( ! empty( $filter['label_type'] ) ) { $where[] = 'label_type = %s'; $params[] = (string) $filter['label_type']; }
        if ( ! empty( $filter['label_file_type'] ) ) { $where[] = 'label_file_type = %s'; $params[] = (string) $filter['label_file_type']; }

        if ( ! empty( $filter['label_date_from'] ) ) { $where[] = 'label_date >= %s'; $params[] = $this->dateToMysql( $filter['label_date_from'] ); }
        if ( ! empty( $filter['label_date_to'] ) )   { $where[] = 'label_date <= %s'; $params[] = $this->dateToMysql( $filter['label_date_to'] ); }

        if ( ! empty( $filter['created_from'] ) ) { $where[] = 'created_at >= %s'; $params[] = $this->dateToMysql( $filter['created_from'] ); }
        if ( ! empty( $filter['created_to'] ) )   { $where[] = 'created_at <= %s'; $params[] = $this->dateToMysql( $filter['created_to'] ); }
        if ( ! empty( $filter['updated_from'] ) ) { $where[] = 'updated_at >= %s'; $params[] = $this->dateToMysql( $filter['updated_from'] ); }
        if ( ! empty( $filter['updated_to'] ) )   { $where[] = 'updated_at <= %s'; $params[] = $this->dateToMysql( $filter['updated_to'] ); }


        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(easypost_id LIKE %s OR integrated_form LIKE %s OR label_type LIKE %s OR label_file_type LIKE %s)';
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

    /** All labels by entry */
    public function getAllByEntryId( int $entryId, array $opts = [] ) {
        return $this->getList( [ 'entry_id' => $entryId ], $opts );
    }

    /** Single label by EasyPost id */
    public function getByEasypostId( string $easypostId ) {
        $rows = $this->getList( [ 'easypost_id' => $easypostId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Latest label for entry (by label_date desc, fallback created_at) */
    public function getLatestByEntryId( int $entryId ) {
        return $this->getList(
            [ 'entry_id' => $entryId ],
            [ 'order_by' => 'label_date', 'order' => 'DESC', 'limit' => 1 ]
        );
    }

    /** Single label by shipment_id */
    public function getByShipmentId( string $shipmentId ) {
        $rows = $this->getList( [ 'easypost_shipment_id' => $shipmentId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /**
     * Bulk upsert for frm_easypost_shipment_label.
     * Unique key is 'easypost_id'.
     */
    public function multipleUpdateCreate( array $rows ) {
        $cols = [
            'easypost_id',
            'easypost_shipment_id',
            'entry_id',
            'date_advance',
            'integrated_form',
            'label_date',
            'label_resolution',
            'label_size',
            'label_type',
            'label_file_type',
            'label_url',
            'label_pdf_url',
            'label_zpl_url',
            'label_epl2_url',
            'created_at',
            'updated_at',
        ];

        $formats = [
            'easypost_id'     => '%s',
            'easypost_shipment_id' => '%s',
            'entry_id'        => '%d',
            'date_advance'    => '%d',
            'integrated_form' => '%s',
            'label_date'      => '%s',
            'label_resolution'=> '%d',
            'label_size'      => '%s',
            'label_type'      => '%s',
            'label_file_type' => '%s',
            'label_url'       => '%s',
            'label_pdf_url'   => '%s',
            'label_zpl_url'   => '%s',
            'label_epl2_url'  => '%s',
            'created_at'      => '%s',
            'updated_at'      => '%s',
        ];

        // Normalize ints and ISO8601 -> MySQL DATETIME
        foreach ( $rows as &$r ) {
            foreach ( ['entry_id','date_advance','label_resolution'] as $k ) {
                if ( isset($r[$k]) && $r[$k] !== '' ) { $r[$k] = (int) $r[$k]; }
            }
            if ( isset($r['label_date']) && $r['label_date'] !== '' ) {
                $r['label_date'] = $this->dateToMysql( (string) $r['label_date'] );
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

    // ----------------------- utils -----------------------
    /*
    private function dateToMysql( string $in ): string {
        $t = strtotime( $in );
        if ( ! $t ) { return $in; }
        return gmdate( 'Y-m-d H:i:s', $t );
    }
    */
}
