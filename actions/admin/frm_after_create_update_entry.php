<?php

/** Hook: after create */
add_action('frm_after_create_entry', function (int $entry_id, int $form_id) {

    // Update entry status based on field conditions
    $applyForm = new FrmUpdateApplyForm();
    $applyForm->updateEntryStatus($entry_id);
    
    // Save PDF of entry on the server
    $entryPdf = new FrmSaveEntryPdf();
    $entryPdf->saveEntryPdf( $entry_id );

    
}, 20, 2);

/** Hook: after update */
add_action('frm_after_update_entry', function (int $entry_id, int $form_id) {

    // Update entry status based on field conditions
    $applyForm = new FrmUpdateApplyForm();
    $applyForm->updateEntryStatus($entry_id);

}, 20, 2);