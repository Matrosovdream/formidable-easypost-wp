<?php

class FrmUpdateEntryStatusCron extends FrmEasypostAbstractCron {

    public const HOOK = 'frm_update_apply_entry_status';

    public static function run_update_apply_entry_status(): void {
        
        $entry_id = 17076;
        
        $applyForm = new FrmUpdateApplyForm();
        //$applyForm->updateEntryStatus($entry_id);

    }

    /** Register schedule + callback. Safe to call multiple times. */
    public static function init(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_five_min_schedule' ] );
        add_action( self::HOOK, [ __CLASS__, 'run_update_apply_entry_status' ] );

        // Ensure an event is queued (in case activation hook was missed on deploy)
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

}