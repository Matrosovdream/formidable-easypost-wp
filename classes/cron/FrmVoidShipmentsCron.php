<?php

class FrmVoidShipmentsCron extends FrmEasypostAbstractCron {

    public const HOOK = 'frm_void_shipments_cron_hook';

    public static function run_void_shipments(): void {
        
        $helper = new FrmEasypostEntryHelper();
        $helper->voidShipments();

    }

    /** Register schedule + callback. Safe to call multiple times. */
    public static function init(): void {

        add_action( self::HOOK, [ __CLASS__, 'run_void_shipments' ] );

        // Ensure an event is queued (in case activation hook was missed on deploy)
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            // schedule to run hourly
            wp_schedule_event( time() + 60, 'hourly', self::HOOK );
        }
    }

}