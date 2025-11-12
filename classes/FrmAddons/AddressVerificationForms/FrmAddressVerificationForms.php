<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Injects front-end address verification UI for Formidable Forms
 * based on admin mappings from the "Address verifier" page.
 *
 * - Reads option: frm_address_verification_forms (rows: form_id, page, street1, street2, city, state, zipcode, test_mode)
 * - Outputs a modal + jQuery logic
 * - Builds JS var ADDRESSES from the option for the current form only
 */
final class FrmAddressVerificationForms {

    private const OPTION_KEY  = 'frm_address_verification_forms';
    private const AJAX_ACTION = 'entry_verify_address'; // your existing AJAX handler name

    public static function init(): void {
        add_action( 'frm_display_form_action', [ __CLASS__, 'maybe_inject_for_form' ], 10, 1 );
    }

    /**
     * Runs for each rendered Formidable form
     * @param array $form Formidable form context
     */
    public static function maybe_inject_for_form( array $form ): void {
        $form_id = isset($form['form_id']) ? (int) $form['form_id'] : 0;
        if ( $form_id <= 0 ) {
            return;
        }

        // Build page=>fields mapping for this form from saved settings
        $addresses = self::get_addresses_mapping_for_form( $form_id );
        if ( empty( $addresses ) ) {
            return; // No mapping configured for this form
        }

        add_action( 'wp_footer', function () use ( $addresses ) {
            $ajax_url  = admin_url( 'admin-ajax.php' );
            $ajaxNonce = wp_create_nonce( 'ep_entry_verify_nonce' );
            ?>
            <style>
                .epv-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; z-index: 99998; }
                .epv-modal { position: fixed; left: 50%; top: 50%; transform: translate(-50%,-50%); width: min(640px, 92vw); background: #fff; border-radius: 12px; box-shadow: 0 10px 35px rgba(0,0,0,.25); z-index: 99999; display: none; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; }
                .epv-modal header { padding: 16px 20px; border-bottom: 1px solid #eee; font-weight: 600; }
                .epv-modal .epv-body { padding: 16px 20px; }
                .epv-modal footer { padding: 14px 20px; border-top: 1px solid #eee; display: flex; gap: 8px; justify-content: flex-end; }
                .epv-note { color:#666; font-size: 13px; margin-top: 8px; }
                .epv-choice { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px; }
                .epv-choice strong { display: block; margin-bottom: 2px; font-size: 14px; }
                .epv-btn { cursor:pointer; border: 1px solid transparent; border-radius: 8px; padding: 10px 14px; font-weight:600; }
                .epv-btn-primary { background:#111827; color:#fff; }
                .epv-btn-ghost { background:#fff; color:#111827; border-color:#e5e7eb; }
            </style>

            <div class="epv-modal-backdrop" id="epvBackdrop"></div>
            <div class="epv-modal" id="epvModal" role="dialog" aria-modal="true" aria-labelledby="epvTitle">
                <header>
                    <div id="epvTitle">Confirm your address</div>
                </header>
                <div class="epv-body">
                    <div id="epvChoices"></div>
                    <div class="epv-note" id="epvNote"></div>
                </div>
                <footer>
                    <button type="button" class="epv-btn epv-btn-ghost" id="epvCancel">Cancel</button>
                    <button type="button" class="epv-btn epv-btn-primary" id="epvContinue">Continue</button>
                </footer>
            </div>

            <script>
            (function($){
                $(function(){

                    var ajaxUrl   = <?php echo wp_json_encode( $ajax_url ); ?>;
                    var ajaxNonce = <?php echo wp_json_encode( $ajaxNonce ); ?>;

                    var epvBypass     = false;
                    var $lastNextBtn  = null;
                    var lastResult    = null;

                    // Injected from PHP: { [pageNum]: { fields: {street1, street2, city, state, zipcode} } }
                    var ADDRESSES = <?php echo wp_json_encode( $addresses ); ?>;

                    /** Detect the current visible page number (class .frm_page_num_X and visible) */
                    function getCurrentPageNumber() {
                        var current = null;
                        $('[class*="frm_page_num_"]').each(function(){
                            var $this = $(this);
                            if ($this.is(':visible')) {
                                var m = this.className.match(/frm_page_num_(\d+)/);
                                if (m) current = parseInt(m[1], 10);
                                return false;
                            }
                        });
                        return current;
                    }

                    function setFieldValue(id, value) {
                        var $sel = $('select[name="item_meta['+id+']"]');
                        if ($sel.length) {
                            $sel.val(value).trigger('change');
                            return;
                        }
                        var $inp = $('[name="item_meta['+id+']"]');
                        if ($inp.length) {
                            $inp.val(value).trigger('change');
                        }
                    }

                    function collectPageValues(pageNum) {
                        var section = ADDRESSES[pageNum];
                        if (!section) return null;

                        var fields = section.fields || {};
                        var data = {};
                        $.each(fields, function(key, id){
                            var $el = $('[name="item_meta[' + id + ']"]');
                            data[key] = $el.length ? ($el.val() || '').toString().trim() : '';
                        });
                        return data;
                    }

                    function composeLineFromOriginal(addr) {
                        var a = addr || {};
                        var firstLine = [];
                        if (a.street1) firstLine.push(a.street1);
                        if (a.street2) firstLine.push(a.street2);
                        var parts = [];
                        if (firstLine.length) parts.push(firstLine.join(', '));

                        var cityStateZip = [];
                        if (a.city)  cityStateZip.push(a.city);
                        if (a.state) cityStateZip.push(a.state);
                        if (a.zipcode) cityStateZip.push(a.zipcode);
                        if (cityStateZip.length) parts.push(cityStateZip.join(' '));

                        return parts.join(', ');
                    }

                    function composeLineFromNormalized(n) {
                        if (!n) return '';
                        // Prefer delivery_line_1 + delivery_line_2 + last_line
                        var segs = [];
                        if (n.delivery_line_1) segs.push(n.delivery_line_1);
                        if (n.delivery_line_2) segs.push(n.delivery_line_2);
                        if (n.last_line)       segs.push(n.last_line);
                        if (segs.length) return segs.join(', ');
                        if (n.full_address) return String(n.full_address);
                        return '';
                    }

                    function showModalSuccess(normalizedText, originalText) {
                        var $choices = $('#epvChoices').empty();
                        var $note = $('#epvNote').empty();
                        var groupName = 'epv_address_choice';

                        var $opt1 = $('<label class="epv-choice"></label>').append(
                            $('<input type="radio" name="'+groupName+'" value="normalized" checked>'),
                            $('<div/>').append(
                                $('<strong/>').text('Verified address'),
                                $('<div/>').text(normalizedText || '')
                            )
                        );
                        var $opt2 = $('<label class="epv-choice"></label>').append(
                            $('<input type="radio" name="'+groupName+'" value="original">'),
                            $('<div/>').append(
                                $('<strong/>').text('Entered address'),
                                $('<div/>').text(originalText || '')
                            )
                        );

                        $choices.append($opt1, $opt2);
                        $note.text('We found a verified version of your address. Choose which one to use and continue.');

                        $('#epvBackdrop').fadeIn(120);
                        $('#epvModal').fadeIn(120);
                    }

                    function showModalFailure(originalText) {
                        var $choices = $('#epvChoices').empty();
                        var $note = $('#epvNote').empty();

                        var groupName = 'epv_address_choice';
                        var $optOnly = $('<label class="epv-choice"></label>').append(
                            $('<input type="radio" name="'+groupName+'" value="original" checked>'),
                            $('<div/>').append(
                                $('<strong/>').text('Entered address'),
                                $('<div/>').text(originalText || '')
                            )
                        );

                        $choices.append($optOnly);
                        $note.text("Address couldn't be verified with USPS database. Please double check and if correct, click continue.");

                        $('#epvBackdrop').fadeIn(120);
                        $('#epvModal').fadeIn(120);
                    }

                    function closeModal() {
                        $('#epvModal').fadeOut(120, function(){
                            $('#epvChoices').empty();
                            $('#epvNote').empty();
                        });
                        $('#epvBackdrop').fadeOut(120);
                    }

                    // Modal actions
                    $(document).on('click', '#epvCancel', function(){
                        closeModal();
                    });

                    $(document).on('click', '#epvContinue', function(){
                        var choice = $('input[name="epv_address_choice"]:checked').val();
                        if (choice === 'normalized' && lastResult && lastResult.normalized && lastResult.fieldsMap) {
                            var n   = lastResult.normalized;
                            var map = lastResult.fieldsMap;
                            var c   = (n && n.components) ? n.components : {};

                            // Write normalized → form fields
                            if (map.street1) setFieldValue(map.street1, n.delivery_line_1 || '');
                            if (map.street2) setFieldValue(map.street2, n.delivery_line_2 || '');
                            if (map.city)    setFieldValue(map.city,    (c.default_city_name || n.default_city_name || ''));
                            if (map.state)   setFieldValue(map.state,   (c.state_abbreviation || n.state_abbreviation || ''));
                            if (map.zipcode) setFieldValue(map.zipcode, (c.full_zipcode || '')); // use full_zipcode
                        }

                        closeModal();
                        epvBypass = true;
                        if ($lastNextBtn && $lastNextBtn.length) {
                            $lastNextBtn.trigger('click');
                        }
                    });

                    // === Ajax request with loading indicator on Next button ===
                    function verifyAddressAjax(fields, fieldsMap, $btn) {
                        var originalTitle = $btn.attr('title') || '';
                        var originalText  = $btn.text();
                        $btn.prop('disabled', true).attr('title', 'Verifying address...').text('Verifying address...');

                        return $.post(ajaxUrl, {
                            action: <?php echo wp_json_encode( self::AJAX_ACTION ); ?>,
                            _ajax_nonce: ajaxNonce,
                            address: JSON.stringify(fields),
                            strict: 1
                        })
                        .done(function(resp){
                            try {
                                var okFlag     = !!(resp && resp.success === true);
                                var normalized = (resp && resp.data && resp.data.normalized) ? resp.data.normalized : null;

                                var normalizedText      = composeLineFromNormalized(normalized);
                                var originalTextDisplay = composeLineFromOriginal(fields);

                                lastResult = {
                                    success: okFlag,
                                    normalized: normalized,
                                    normalizedText: normalizedText,
                                    originalText: originalTextDisplay,
                                    fieldsMap: fieldsMap
                                };

                                if (okFlag && normalized) {
                                    showModalSuccess(normalizedText, originalTextDisplay);
                                } else {
                                    showModalFailure(originalTextDisplay);
                                }

                            } catch (e) {
                                lastResult = {
                                    success: false,
                                    normalized: null,
                                    normalizedText: '',
                                    originalText: composeLineFromOriginal(fields),
                                    fieldsMap: fieldsMap
                                };
                                showModalFailure(lastResult.originalText);
                            }
                        })
                        .fail(function(){
                            lastResult = {
                                success: false,
                                normalized: null,
                                normalizedText: '',
                                originalText: composeLineFromOriginal(fields),
                                fieldsMap: fieldsMap
                            };
                            showModalFailure(lastResult.originalText);
                        })
                        .always(function(){
                            $btn.prop('disabled', false)
                                .attr('title', originalTitle)
                                .text(originalText);
                        });
                    }

                    /**
                     * Helper: Are street1 or street2 present & visible?
                     * Returns true only if at least one mapped street field exists and is :visible.
                     */
                    function isAddressVisible(fieldsMap) {
                        if (!fieldsMap) return false;

                        var ids = [];
                        if (fieldsMap.street1) ids.push(fieldsMap.street1);
                        if (fieldsMap.street2) ids.push(fieldsMap.street2);

                        if (!ids.length) return false;

                        for (var i=0; i<ids.length; i++) {
                            var sel = '[name="item_meta[' + ids[i] + ']"]';
                            var $el = $(sel);
                            if ($el.length && $el.is(':visible')) return true;
                        }
                        return false;
                    }

                    // Intercept Next/Submit
                    $(document).on('click', '.frm_button_submit', function(e){
                        var currentPage = getCurrentPageNumber();

                        if (epvBypass) {
                            epvBypass = false;
                            return; // allow natural submit
                        }

                        if (ADDRESSES[currentPage]) {
                            var fieldsMap = (ADDRESSES[currentPage] || {}).fields || {};

                            // Only run verification if at least one street field is visible
                            if (!isAddressVisible(fieldsMap)) {
                                //return; // don't interfere with other scripts
                            }

                            e.preventDefault();
                            $lastNextBtn = $(this);

                            var fields = collectPageValues(currentPage);
                            verifyAddressAjax(fields, fieldsMap, $lastNextBtn);
                        }
                    });

                });
            })(jQuery);
            </script>
            <?php
        } );
    }

    /**
     * Build the mapping for the current form from saved admin options.
     * Returns: [ pageNum => [ 'fields' => [ 'street1'=>ID, 'street2'=>ID, 'city'=>ID, 'state'=>ID, 'zipcode'=>ID ] ] ]
     *
     * - Includes rows where:
     *   - form_id matches the current form
     *   - page > 0 and at least one field id is present
     *   - if test_mode==1 then only include for admins (manage_options)
     */
    private static function get_addresses_mapping_for_form( int $form_id ): array {
        $opt  = get_option( self::OPTION_KEY, [] );
        $rows = isset( $opt['rows'] ) && is_array( $opt['rows'] ) ? $opt['rows'] : [];

        if ( empty( $rows ) ) {
            return [];
        }

        $is_admin = current_user_can( 'manage_options' );
        $out = [];

        foreach ( $rows as $row ) {
            $r_form = (int) ( $row['form_id'] ?? 0 );
            if ( $r_form !== $form_id ) {
                continue;
            }

            $page = (int) ( $row['page'] ?? 0 );
            if ( $page <= 0 ) {
                continue;
            }

            $test_mode = ! empty( $row['test_mode'] ) ? 1 : 0;
            if ( $test_mode && ! $is_admin ) {
                // Row is admin-only, and current user isn't admin → skip
                continue;
            }

            $street1 = (int) ( $row['street1'] ?? 0 );
            $street2 = (int) ( $row['street2'] ?? 0 );
            $city    = (int) ( $row['city']    ?? 0 );
            $state   = (int) ( $row['state']   ?? 0 );
            $zipcode = (int) ( $row['zipcode'] ?? 0 );

            if ( $street1 <= 0 && $street2 <= 0 && $city <= 0 && $state <= 0 && $zipcode <= 0 ) {
                continue; // nothing to map
            }

            // Keep structure: {page: { fields: {...} } }
            $out[ $page ] = [
                'fields' => array_filter([
                    'street1' => $street1 ?: null,
                    'street2' => $street2 ?: null,
                    'city'    => $city ?: null,
                    'state'   => $state ?: null,
                    'zipcode' => $zipcode ?: null,
                ])
            ];
        }

        if ( ! empty( $out ) ) {
            ksort( $out, SORT_NUMERIC );
        }

        return $out;
    }
}

FrmAddressVerificationForms::init();

/**
 * Automatically toggle checkbox item_meta[42][]
 * based on whether item_meta[37] contains "PO Box" variants.
 */
final class FrmPoBoxAutoCheck {
    public static function init(): void {
        add_action( 'frm_display_form_action', [ __CLASS__, 'maybe_inject_for_form' ], 10, 1 );
    }

    public static function maybe_inject_for_form( array $form ): void {
        // Optional: limit to a specific form ID
        // $form_id = isset($form['form_id']) ? (int)$form['form_id'] : 0;
        // if ( $form_id !== 123 ) return;

        add_action( 'wp_footer', [ __CLASS__, 'print_script' ] );
    }

    public static function print_script(): void {
        ?>
        <script>
        (function($){
            $(function(){

                var ADDR_SELECTOR = '[name="item_meta[37]"]';
                var CHECKBOX_SELECTOR = '[name="item_meta[42][]"]';
                var POBOX_REGEX = /\bP\.?\s*O\.?\s*Box\b/i; // Matches PO Box, P.O Box, P.O. Box (case-insensitive)

                function toggleCheckboxBasedOnAddress() {
                    var $addr = $(ADDR_SELECTOR);
                    var $cbs  = $(CHECKBOX_SELECTOR);
                    if (!$addr.length || !$cbs.length) return;

                    var val = $addr.val() || '';
                    var match = POBOX_REGEX.test(val);

                    $cbs.each(function(){
                        var $cb = $(this);
                        if (match && !this.checked) {
                            this.checked = true;
                            $cb.trigger('change');
                        } else if (!match && this.checked) {
                            this.checked = false;
                            $cb.trigger('change');
                        }
                    });
                }

                // Initial check on load
                toggleCheckboxBasedOnAddress();

                // Re-check on input or change
                $(document).on('input change blur', ADDR_SELECTOR, toggleCheckboxBasedOnAddress);

                // Handle dynamically loaded fields (Formidable AJAX)
                $(document).ajaxComplete(toggleCheckboxBasedOnAddress);

            });
        })(jQuery);
        </script>
        <?php
    }
}

FrmPoBoxAutoCheck::init();
