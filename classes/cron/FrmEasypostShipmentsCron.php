<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmEasypostShipmentsCron {
    public const HOOK = 'frm_easypost_update_shipments';

    /** Register schedule + callback. Safe to call multiple times. */
    public static function init(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_five_min_schedule' ] );
        add_action( self::HOOK, [ __CLASS__, 'run_update_shipments' ] );

        // Ensure an event is queued (in case activation hook was missed on deploy)
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

    /** Add a 5-minute recurrence to WP-Cron */
    public static function add_five_min_schedule( array $schedules ): array {
        if ( ! isset( $schedules['five_minutes'] ) ) {
            $schedules['five_minutes'] = [
                'interval' => 300, // 5 minutes
                'display'  => __( 'Every 5 Minutes', 'easypost-wp' ),
            ];
        }
        return $schedules;
    }

    /** The job handler — calls your model's updateShipmentsApi() */
    public static function run_update_shipments(): void {
        if ( ! class_exists( 'FrmEasypostShipmentHelper' ) ) { return; }
        try {
            $model = new FrmEasypostShipmentHelper();
            //$res   = $model->updateShipmentsApi();
            if ( is_wp_error( $res ) ) {
                error_log( '[Easypost] updateShipmentsApi error: ' . $res->get_error_message() );
            }
            update_option( 'easypost_wp_last_shipments_sync', time() );
        } catch ( \Throwable $e ) {
            error_log( '[Easypost] Cron exception: ' . $e->getMessage() );
        }
    }

    /** Called on plugin activation */
    public static function activate(): void {
        // Make sure our schedule exists early
        add_filter( 'cron_schedules', [ __CLASS__, 'add_five_min_schedule' ] );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

    /** Called on plugin deactivation */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }
}


