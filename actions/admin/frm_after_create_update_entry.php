<?php

/** Hook: after create */
add_action('frm_after_create_entry', function (int $entry_id, int $form_id) {
    
    // Save PDF of entry on the server
    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->saveEntryPdf( $entry_id );

    
}, 5, 2);

/** Hook: after update */
add_action('frm_after_update_entry', function (int $entry_id, int $form_id) {

    // If Entry status [7] is 'Mailed' then change to Complete-m in 7 days, use wp_schedule_single_event()
    if ( 
        FrmEntryMeta::get_entry_meta_by_field( $entry_id, 7 ) === 'Mailed' && 
        $form_id == 1
        ) {
        if ( ! wp_next_scheduled( 'frm_ep_mark_entry_complete', [ $entry_id ] ) ) {
            wp_schedule_single_event( time() + 7 * DAY_IN_SECONDS, 'frm_ep_mark_entry_complete', [ $entry_id ] );
        }
    }


}, 5, 2);

function frm_ep_mark_entry_complete( int $entry_id ) {
    // Update Entry status [7] to 'Complete-M'
    $entry = FrmEntry::getOne( $entry_id, true );
    if ( 
        $entry && 
        FrmEntryMeta::get_entry_meta_by_field( $entry_id, 7 ) === 'Mailed' 
        ) {
        FrmEntryMeta::update_entry_meta( $entry_id, 7, null, 'Complete-M' );
    }
}