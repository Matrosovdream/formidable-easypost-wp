<?php

class FrmUpdateApplyForm {

    /**
     * Update Formidable entry status field [7] from "Processing-X" to "Verified"
     * based on specific field conditions.
     *
     * Logic:
     * - Only runs if field [7] currently equals "Processing-X".
     * - Checks that:
     *   • Field [273] contains "verified"
     *   • Field [273] does NOT contain "missing-info"
     *   • Field [670] = "photo-done" OR field [328] = "photo-no"
     *   • Field [70] is not equal to "Provide Later"
     *
     * If all conditions are met:
     * - Updates field [7] to "Verified" if it already exists,
     *   otherwise creates the field meta with "Verified".
     *
     * @param int $entry_id Formidable entry ID to process.
     * @return void
     */
    public function updateEntryStatus($entry_id) {

        if (!$entry_id) { return; }
    
        $helper = new FrmEasypostEntryHelper();
        $metas  = $helper->getEntryMetas((int) $entry_id);
    
        // Get field values safely
        $field7   = $metas[7]   ?? '';
        $field273 = $metas[273] ?? ''; // Array
        $field670 = $metas[670] ?? '';
        $field328 = $metas[328] ?? '';
        $field70  = $metas[70]  ?? '';
    
        // Check initial condition
        if ($field7 === 'Processing-X') {
            $hasVerified     = in_array('verified', (array)$field273, true);
            $notMissingInfo  = !in_array('missing-info', (array)$field273, true);
            $hasPhotoStatus  = ($field670 === 'photo-done' || $field328 === 'photo-no');
            $notProvideLater = ($field70 !== 'Provide Later'); 
    
            if ($hasVerified && $notMissingInfo && $hasPhotoStatus && $notProvideLater) {
                // If [7] exists in metas, update; otherwise add
                $field7Exists = array_key_exists(7, $metas);
        
                if ($field7Exists) {
                    FrmEntryMeta::update_entry_meta($entry_id, 7, '', 'Verified');
                } else {
                    FrmEntryMeta::add_entry_meta($entry_id, 7, '', 'Verified');
                }
            }

        }
    }

}