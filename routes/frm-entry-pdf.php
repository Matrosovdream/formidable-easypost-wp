<?php

add_action('init', 'frmShowEntryPdf');
function frmShowEntryPdf() {

    if( isset( $_GET['entry_pdf'] ) ) {
        
        $entry_id = (int) $_GET['entry_pdf'];
        if ( $entry_id <= 0 ) {
            status_header(400);
            wp_die('Invalid entry ID', '400 - Invalid', [ 'response' => 400 ]);
        }   

        if( isset( $_GET['origin'] ) ) {
            $entryPdf = new FrmSaveEntryPdf();
            $entryPdf->streamOriginEntryPdf( $entry_id, false, $create = true );
        } else {
            $entryPdf = new FrmSaveEntryPdf();
            $entryPdf->streamTmpEntryPdf( $entry_id );
        }

        // exits    
        exit();

    }    

}    
