<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentAddressModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipment_addresses */
    private const SORTABLE = [
        'id',
        'easypost_id',
        'entry_id',
        'address_type',
        'name',
        'company',
        'street1',
        'street2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'email',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipment_addresses';
    }

    /**
     * Base list query for frm_easypost_shipment_addresses.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - easypost_id (string)
     *  - entry_id (int)
     *  - address_type (string: from|to|return|buyer etc.)
     *  - name, company, street1, street2, city, state, zip, country, phone, email (strings)
     *  - search (LIKE over easypost_id, name, company, street1, city, state, zip, phone, email)
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
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }
        if ( ! empty( $filter['address_type'] ) ) { $where[] = 'address_type = %s'; $params[] = (string) $filter['address_type']; }

        foreach ( ['name','company','street1','street2','city','state','zip','country','phone','email'] as $col ) {
            if ( ! empty( $filter[$col] ) ) {
                $where[]  = "{$col} = %s";
                $params[] = (string) $filter[$col];
            }
        }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '('
                . 'easypost_id LIKE %s OR '
                . 'name LIKE %s OR company LIKE %s OR '
                . 'street1 LIKE %s OR city LIKE %s OR state LIKE %s OR zip LIKE %s OR '
                . 'phone LIKE %s OR email LIKE %s'
                . ')';
            // order of params must match placeholders above
            array_push( $params, $like, $like, $like, $like, $like, $like, $like, $like, $like );
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

    /** Convenience: all addresses by entry */
    public function getAllByEntryId( int $entryId, array $opts = [] ) {
        return $this->getList( [ 'entry_id' => $entryId ], $opts );
    }

    /** Get by EasyPost address id (e.g., adr_...) */
    public function getByEasypostId( string $easypostId ) {
        $rows = $this->getList( [ 'easypost_id' => $easypostId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Get one address for entry by type (from|to|return|buyer, etc.) */
    public function getByEntryAndType( int $entryId, string $addressType ) {
        $rows = $this->getList(
            [ 'entry_id' => $entryId, 'address_type' => $addressType ],
            [ 'limit' => 1 ]
        );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /**
     * Bulk upsert for frm_easypost_shipment_addresses.
     * Unique key is 'easypost_id'.
     */
    public function multipleUpdateCreate( array $rows ) {
        $cols = [
            'easypost_id',
            'entry_id',
            'address_type',
            'name',
            'company',
            'street1',
            'street2',
            'city',
            'state',
            'zip',
            'country',
            'phone',
            'email',
        ];

        $formats = [
            'easypost_id'  => '%s',
            'entry_id'     => '%d',
            'address_type' => '%s',
            'name'         => '%s',
            'company'      => '%s',
            'street1'      => '%s',
            'street2'      => '%s',
            'city'         => '%s',
            'state'        => '%s',
            'zip'          => '%s',
            'country'      => '%s',
            'phone'        => '%s',
            'email'        => '%s',
        ];

        // Normalize types
        foreach ( $rows as &$r ) {
            if ( isset( $r['entry_id'] ) && $r['entry_id'] !== '' ) {
                $r['entry_id'] = (int) $r['entry_id'];
            }
            if ( isset( $r['country'] ) && $r['country'] !== '' ) {
                $r['country'] = strtoupper( (string) $r['country'] ); // store ISO2 consistently
            }
        }
        unset($r);

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'easypost_id' );
    }
}
