<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentHistoryModel extends FrmEasypostAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_easypost_shipment_history */
    private const SORTABLE = [
        'id',
        'shipment_id',
        'easypost_shipment_id',
        'user_id',
        'change_type',
        'description',
        'created_at',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_easypost_shipment_history';
    }

    /**
     * Base list query for frm_easypost_shipment_history.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - shipment_id (int|string)               // your internal shipment id
     *  - easypost_shipment_id (string)
     *  - user_id (int)
     *  - change_type (string)
     *  - created_from / created_to (Y-m-d or datetime or ISO8601)
     *  - search (LIKE on shipment_id, easypost_shipment_id, change_type, description)
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

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) {
            $where[]  = 'id = %d';
            $params[] = (int) $filter['id'];
        }

        if ( isset( $filter['shipment_id'] ) && $filter['shipment_id'] !== '' ) {
            // shipment_id might be numeric or a string depending on your schema
            if ( is_numeric( $filter['shipment_id'] ) ) {
                $where[]  = 'shipment_id = %d';
                $params[] = (int) $filter['shipment_id'];
            } else {
                $where[]  = 'shipment_id = %s';
                $params[] = (string) $filter['shipment_id'];
            }
        }

        if ( ! empty( $filter['easypost_shipment_id'] ) ) {
            $where[]  = 'easypost_shipment_id = %s';
            $params[] = (string) $filter['easypost_shipment_id'];
        }

        if ( isset( $filter['user_id'] ) && $filter['user_id'] !== '' ) {
            $where[]  = 'user_id = %d';
            $params[] = (int) $filter['user_id'];
        }

        if ( ! empty( $filter['change_type'] ) ) {
            $where[]  = 'change_type = %s';
            $params[] = (string) $filter['change_type'];
        }

        if ( ! empty( $filter['created_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $this->dateToMysql( (string) $filter['created_from'] );
        }

        if ( ! empty( $filter['created_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $this->dateToMysql( (string) $filter['created_to'] );
        }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';

            // shipment_id may be stored as int; LIKE on int columns is okay in MySQL (casts to string).
            $where[]  = '(shipment_id LIKE %s OR easypost_shipment_id LIKE %s OR change_type LIKE %s OR description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = ( isset( $opts['order_by'] ) && in_array( (string) $opts['order_by'], self::SORTABLE, true ) )
            ? (string) $opts['order_by']
            : 'created_at';

        $order = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' )
            ? 'ASC'
            : 'DESC';

        $limit  = isset( $opts['limit'] ) ? max( 1, (int) $opts['limit'] ) : 50;
        $offset = isset( $opts['offset'] ) ? max( 0, (int) $opts['offset'] ) : 0;

        if ( isset( $opts['page'] ) || isset( $opts['per_page'] ) ) {
            $pp     = isset( $opts['per_page'] ) ? max( 1, (int) $opts['per_page'] ) : 50;
            $page   = isset( $opts['page'] ) ? max( 1, (int) $opts['page'] ) : 1;
            $limit  = $pp;
            $offset = ( $page - 1 ) * $pp;
        }

        $sql = "SELECT * FROM {$this->table} {$whereSql} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $args = array_merge( $params, [ $limit, $offset ] );

        $prepared = $this->db->prepare( $sql, $args );
        if ( false === $prepared ) {
            return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'easypost-wp' ) );
        }

        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( null === $rows ) {
            return new WP_Error(
                'db_query_failed',
                __( 'Database query failed.', 'easypost-wp' ),
                [ 'last_error' => $this->db->last_error ]
            );
        }

        return $rows;
    }

    public function getByShipmentNumber( string $easypost_shipment_id='' ): array|null {

        if ( empty( $easypost_shipment_id ) ) {
            return null;
        }

        $filter = [
            'easypost_shipment_id' => $easypost_shipment_id,
        ];
        $opts   = [
            'order_by' => 'created_at',
            'order'    => 'DESC'
        ];

        $items = $this->getList( $filter, $opts );
        
        foreach ( $items as &$item ) {
            
            // user_id to user
            if ( !empty( $item['user_id'] ) ) {
                $user = get_user_by( 'id', (int) $item['user_id'] );
                if ( $user ) {
                    $item['user'] = [
                        'id'       => $user->ID,
                        'username' => $user->user_login,
                        'email'    => $user->user_email,
                        'name'     => $user->display_name
                    ];
                }
            }

            // Date format to US readable
            $item['created_at'] = date( 'Y-m-d H:i', strtotime( $item['created_at'] ) );


        }


        return $items;
    }

}
