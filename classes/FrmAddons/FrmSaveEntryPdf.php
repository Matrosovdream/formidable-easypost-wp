<?php

class FrmSaveEntryPdf {

    /** Absolute server path */
    protected string $saveDir;
    /** Public URL */
    protected string $saveUrl;

    public function __construct() {
        $this->saveDir = ABSPATH . 'wp-content/formidable-pdfs';
        $this->saveUrl = content_url('formidable-pdfs');
    }

    /**
     * Save a freshly generated PDF for an entry to:
     *   /wp-content/formidable-pdfs/{entry_id}.pdf
     */
    public function saveEntryPdf( int $entry_id ) {
        if ( ! class_exists('FrmPdfsAppController') || ! class_exists('FrmEntry') ) {
            return new WP_Error('missing_addon', 'Formidable PDFs add-on not loaded.');
        }
    
        $entry = \FrmEntry::getOne( $entry_id, true );
        if ( ! $entry ) {
            return new WP_Error('no_entry', 'Entry not found.');
        }
    
        $tmp_path = \FrmPdfsAppController::generate_entry_pdf( $entry, [ 'mode' => 'save' ] );
        if ( ! is_string($tmp_path) || ! file_exists($tmp_path) ) {
            return new WP_Error('gen_fail', 'PDF generation failed.');
        }
    
        // Ensure folder exists
        if ( ! is_dir($this->saveDir) ) {
            if ( ! wp_mkdir_p($this->saveDir) ) {
                @unlink($tmp_path);
                return new WP_Error('mkdir_fail', 'Could not create storage folder.');
            }
            // 🔒 Lock down folder (owner only: read/write/execute)
            @chmod($this->saveDir, 0700);
        }
    
        $dest = trailingslashit($this->saveDir) . $entry_id . '.pdf';
        if ( file_exists($dest) ) { @unlink($dest); }
    
        $ok = @rename($tmp_path, $dest);
        if ( ! $ok ) {
            $ok = @copy($tmp_path, $dest);
            if ( $ok ) { @unlink($tmp_path); }
        }
        if ( ! $ok || ! file_exists($dest) ) {
            return new WP_Error('move_fail', 'Could not write PDF to folder.');
        }
    
        // 🔒 Lock down file (owner only: read/write)
        @chmod($dest, 0600);
    
        return [
            'path' => $dest,
            'url'  => trailingslashit($this->saveUrl) . $entry_id . '.pdf',
        ];
    }
    
    

    /**
     * Generate a PDF to a tmp file and stream it immediately to the browser.
     */
    public function streamTmpEntryPdf( int $entry_id, bool $force_download = false ): void {
        if ( ! is_user_logged_in() || ! current_user_can('manage_options') ) {
            $this->fatal('Unauthorized', 403);
        }

        $entry = \FrmEntry::getOne( $entry_id, true );
        if ( ! $entry ) {
            $this->fatal('Entry not found.', 404);
        }

        $tmp_path = \FrmPdfsAppController::generate_entry_pdf( $entry, [
            'mode'     => 'save',
            'filename' => $entry_id . '.pdf',
        ] );
        if ( ! is_string($tmp_path) || ! file_exists($tmp_path) ) {
            $this->fatal('PDF generation failed.', 500);
        }

        register_shutdown_function(static function () use ($tmp_path) {
            if ( file_exists($tmp_path) ) { @unlink($tmp_path); }
        });

        $this->sendPdfHeaders($entry_id . '.pdf', filesize($tmp_path), $force_download);
        $this->streamFile($tmp_path);
    }

    /**
     * Stream an already-saved PDF from /wp-content/formidable-pdfs/{id}.pdf.
     */
    public function streamOriginEntryPdf( int $entry_id, bool $force_download = false, bool $create = false ): void {
        if ( ! is_user_logged_in() || ! current_user_can('manage_options') ) {
            $this->fatal('Unauthorized', 403);
        }

        $path = trailingslashit($this->saveDir) . $entry_id . '.pdf';
        if ( ! file_exists($path) ) {
            if ( $create ) {
                $res = $this->saveEntryPdf( $entry_id );
                if ( is_wp_error($res) ) {
                    $this->fatal('Could not create PDF: ' . $res->get_error_message(), 500);
                }
            } else {
                $this->fatal('PDF not found.', 404);
            }
        }

        $this->sendPdfHeaders($entry_id . '.pdf', filesize($path), $force_download);
        $this->streamFile($path);
    }

    /* ===========================
     *         Helpers
     * =========================== */

    protected function sendPdfHeaders( string $filename, int $filesize = 0, bool $force_download = false ): void {
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

        $disposition = $force_download ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filename) . '"');
        if ( $filesize > 0 ) {
            header('Content-Length: ' . $filesize);
        }
        header('Accept-Ranges: none');
    }

    protected function streamFile( string $path ): void {
        $h = @fopen($path, 'rb');
        if ( $h === false ) {
            $this->fatal('Unable to open PDF.', 500);
        }
        while ( ! feof($h) ) {
            echo fread($h, 8192);
            @flush();
        }
        fclose($h);
        exit;
    }

    protected function fatal( string $message, int $code = 500 ): void {
        status_header($code);
        wp_die( esc_html($message), $code . ' - PDF', [ 'response' => $code ] );
    }
}
