<?php

class FrmUpdateApplyForm {

    const FORM_ID = 1;

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

        $mappings = [
            [
                'form_id'        => 1,                 // OPTIONAL: restrict to a specific form
                'status_field'   => 7,                 // target field [7]
                'when_status_is' => 'Processing-X',    // only run when [7] = Processing-X
                'set_status_to'  => 'Verified',        // value to set
                'all' => [
                    // AND group: [273] has "verified" AND not "missing-info"
                    ['and' => [
                        ['field' => 273, 'op' => 'in',     'value' => 'verified',     'field_type' => 'array'],
                        ['field' => 273, 'op' => 'not_in', 'value' => 'missing-info', 'field_type' => 'array'],
                    ]],
        
                    // OR group: [670] has "photo-done" OR [328] has "photo-no"
                    ['any' => [
                        ['field' => 670, 'op' => 'in', 'value' => 'photo-done', 'field_type' => 'array'],
                        ['field' => 328, 'op' => 'in', 'value' => 'photo-no',   'field_type' => 'array'],
                    ]],
        
                    // [70] != "Provide Later"
                    ['field' => 70, 'op' => 'not_equals', 'value' => 'Provide Later'],
                ],
            ],
        ];

        $engine = new FrmEntryStatusRuleEngine();
        $engine->applyMappings($entry_id, $mappings);

        /*
        $helper = new FrmEasypostEntryHelper();
        $metas  = $helper->getEntryMetas((int) $entry_id);
    
        // Get field values safely
        $field7   = $metas[7]   ?? '';
        $field273 = $metas[273] ?? ''; // Array
        $field670 = $metas[670] ?? ''; // Array
        $field328 = $metas[328] ?? ''; // Array
        $field70  = $metas[70]  ?? '';

        // Check initial condition
        if ($field7 === 'Processing-X') {

            if( empty($field273) ) { $field273 = []; }
            if( empty($field670) ) { $field670 = []; }
            if( empty($field328) ) { $field328 = []; }

            $hasVerified     = in_array('verified', (array)$field273, true);
            $notMissingInfo  = !in_array('missing-info', (array)$field273, true);
            $hasPhotoStatus  = ( in_array('photo-done', $field670) || in_array('photo-no', $field328) );
            $notProvideLater = ( $field70 !== 'Provide Later' ); 

            if ( $hasVerified && $hasPhotoStatus && $notMissingInfo && $notProvideLater ) {

                // If [7] exists in metas, update; otherwise add
                $field7Exists = array_key_exists(7, $metas);
        
                if ($field7Exists) {
                    FrmEntryMeta::update_entry_meta($entry_id, 7, '', 'Verified');
                } else {
                    FrmEntryMeta::add_entry_meta($entry_id, 7, '', 'Verified');
                }
            }

        }
        */

    }

    public function updateAllEntries( ?int $formId = null, int $batchSize = 500 ): int {
        global $wpdb;
    
        $formId = $formId ?: self::FORM_ID;
        $batchSize = max(1, (int) $batchSize);
    
        $table = $wpdb->prefix . 'frm_items';
        $offset = 0;
        $processed = 0;
    
        // Optional: reduce cache churn while doing bulk updates
        if ( function_exists('wp_suspend_cache_addition') ) {
            wp_suspend_cache_addition(true);
        }
    
        // Process in batches to avoid timeouts/memory spikes
        while ( true ) {
            // Get a batch of entry IDs for this form
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE form_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
                    (int) $formId,
                    (int) $batchSize,
                    (int) $offset
                )
            );
    
            if ( empty($ids) ) {
                break;
            }
    
            foreach ( $ids as $id ) {

                if( $id != 17029 ) continue; // For testing a single entry

                $this->updateEntryStatus( (int) $id );
                $processed++;
            }
    
            $offset += $batchSize;
        }
    
        // Re-enable cache additions if we disabled it
        if ( function_exists('wp_suspend_cache_addition') ) {
            wp_suspend_cache_addition(false);
        }
    
        return $processed; // number of entries processed
    }
    

}