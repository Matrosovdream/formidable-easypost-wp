<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostMigrations {

    public const DB_VERSION     = '1.0.0';
    public const VERSION_OPTION = 'easypost_wp_db_version';

    /** Run on plugin activation */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        // Build SQL for all tables
        $shipments_sql   = self::sql_easypost_shipments( $prefix, $charset_collate );
        $addresses_sql   = self::sql_easypost_shipment_addresses( $prefix, $charset_collate );
        $parcel_sql      = self::sql_easypost_shipment_parcel( $prefix, $charset_collate );
        $label_sql       = self::sql_easypost_shipment_label( $prefix, $charset_collate );
        $rate_sql        = self::sql_easypost_shipment_rate( $prefix, $charset_collate );

        // Create/upgrade tables
        dbDelta( $shipments_sql );
        dbDelta( $addresses_sql );
        dbDelta( $parcel_sql );
        dbDelta( $label_sql );
        dbDelta( $rate_sql );

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /** Optional: call this on 'plugins_loaded' to auto-upgrade when version changes */
    public static function maybe_upgrade(): void {
        $installed = get_option( self::VERSION_OPTION );
        if ( $installed !== self::DB_VERSION ) {
            self::install();
        }
    }

    /**
     * 1) frm_easypost_shipments
     * - Example fields in prompt:
     *   easypost_id, entry_id, is_return, status, tracking_code, refund_status, mode, created_at, updated_at
     */
    private static function sql_easypost_shipments( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_easypost_shipments';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            easypost_id varchar(64) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            is_return tinyint(1) NOT NULL DEFAULT 0,
            status varchar(50) NULL,
            tracking_code varchar(100) NULL,
            refund_status varchar(50) NULL,
            mode varchar(20) NOT NULL DEFAULT 'test',
            created_at datetime NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_easypost_id (easypost_id),
            KEY idx_entry_id (entry_id),
            KEY idx_tracking_code (tracking_code),
            KEY idx_status (status)
        ) {$collate};";
    }

    /**
     * 2) frm_easypost_shipment_addresses
     * - Example fields in prompt (address_type like 'from', 'to', etc.)
     */
    private static function sql_easypost_shipment_addresses( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_easypost_shipment_addresses';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            easypost_id varchar(64) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            address_type varchar(20) NOT NULL,
            name varchar(255) NULL,
            company varchar(255) NULL,
            street1 varchar(255) NULL,
            street2 varchar(255) NULL,
            city varchar(100) NULL,
            state varchar(100) NULL,
            zip varchar(20) NULL,
            country varchar(2) NULL,
            phone varchar(30) NULL,
            email varchar(190) NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_easypost_id (easypost_id),
            KEY idx_entry_id (entry_id),
            KEY idx_type (address_type),
            KEY idx_zip (zip),
            KEY idx_country (country)
        ) {$collate};";
    }

    /**
     * 3) frm_easypost_shipment_parcel
     */
    private static function sql_easypost_shipment_parcel( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_easypost_shipment_parcel';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            easypost_id varchar(64) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            length decimal(10,2) NULL,
            width decimal(10,2) NULL,
            height decimal(10,2) NULL,
            weight decimal(10,2) NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_easypost_id (easypost_id),
            KEY idx_entry_id (entry_id)
        ) {$collate};";
    }

    /**
     * 4) frm_easypost_shipment_label
     * - URLs as TEXT; label_date/created_at/updated_at as DATETIME
     */
    private static function sql_easypost_shipment_label( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_easypost_shipment_label';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            easypost_id varchar(64) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            date_advance int NULL DEFAULT 0,
            integrated_form varchar(50) NULL,
            label_date datetime NULL,
            label_resolution int NULL,
            label_size varchar(20) NULL,
            label_type varchar(50) NULL,
            label_file_type varchar(50) NULL,
            label_url text NULL,
            label_pdf_url text NULL,
            label_zpl_url text NULL,
            label_epl2_url text NULL,
            created_at datetime NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_easypost_id (easypost_id),
            KEY idx_entry_id (entry_id),
            KEY idx_label_date (label_date)
        ) {$collate};";
    }

    /**
     * 5) frm_easypost_shipment_rate
     */
    private static function sql_easypost_shipment_rate( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_easypost_shipment_rate';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            easypost_id varchar(64) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            mode varchar(20) NOT NULL DEFAULT 'test',
            service varchar(100) NULL,
            carrier varchar(100) NULL,
            rate decimal(12,2) NULL,
            currency char(3) NULL,
            retail_rate decimal(12,2) NULL,
            retail_currency char(3) NULL,
            list_rate decimal(12,2) NULL,
            list_currency char(3) NULL,
            billing_type varchar(50) NULL,
            delivery_days int NULL,
            delivery_date datetime NULL,
            delivery_date_guaranteed tinyint(1) NULL,
            est_delivery_days int NULL,
            created_at datetime NULL,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_easypost_id (easypost_id),
            KEY idx_entry_id (entry_id),
            KEY idx_carrier (carrier),
            KEY idx_service (service),
            KEY idx_mode (mode),
            KEY idx_delivery_date (delivery_date)
        ) {$collate};";
    }

}
