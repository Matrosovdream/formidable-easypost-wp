<?php

class FrmSaveEntryPdf {

    public function saveEntryPdf( int $entry_id ) {

        if ( ! class_exists('FrmPdfsAppController') || ! class_exists('FrmEntry') ) {
            return new WP_Error('missing_addon', 'Formidable PDFs add-on not loaded.');
        }

        // Force "save to tmp" mode (no stream/die)
        $args['mode'] = 'save';

        $entry = \FrmEntry::getOne( $entry_id, true );
        if ( ! $entry ) {
            return new WP_Error('no_entry', 'Entry not found.');
        }

        // 1) Generate into tmp
        $tmp_path = \FrmPdfsAppController::generate_entry_pdf( $entry, $args );
        if ( ! $tmp_path || ! file_exists($tmp_path) ) {
            return new WP_Error('gen_fail', 'PDF generation failed.');
        }

        // 2) Ensure uploads/formidable-pdfs exists
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            @unlink($tmp_path);
            return new WP_Error('uploads_error', $uploads['error']);
        }
        $dest_dir = trailingslashit( $uploads['basedir'] ) . 'formidable-pdfs';
        if ( ! file_exists( $dest_dir ) && ! wp_mkdir_p( $dest_dir ) ) {
            @unlink($tmp_path);
            return new WP_Error('mkdir_fail', 'Could not create storage folder.');
        }

        // 3) Copy/rename to {entry_id}.pdf (overwrite if exists)
        $dest = trailingslashit( $dest_dir ) . $entry_id . '.pdf';
        if ( file_exists( $dest ) ) { @unlink( $dest ); }

        $ok = @rename( $tmp_path, $dest );
        if ( ! $ok ) {
            $ok = @copy( $tmp_path, $dest );
            if ( $ok ) { @unlink( $tmp_path ); }
        }
        if ( ! $ok || ! file_exists( $dest ) ) {
            return new WP_Error('move_fail', 'Could not write PDF to uploads.');
        }

        @chmod( $dest, 0644 );

        // Public URL (if you need to show/download it)
        $url = trailingslashit( $uploads['baseurl'] ) . 'formidable-pdfs/' . $entry_id . '.pdf';

        return [ 'path' => $dest, 'url' => $url ];

    }

}