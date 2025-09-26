<?php

/** Hook: after create */
add_action('frm_after_create_entry', function (int $entry_id, int $form_id) {
    
    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->saveEntryPdf( $entry_id );

    
}, 20, 2);

/** Hook: after update */
add_action('frm_after_update_entry', function (int $entry_id, int $form_id) {
    
    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->saveEntryPdf( $entry_id );

}, 20, 2);