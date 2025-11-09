<?php


add_action( 'frm_display_form_action', function ( $form ) {
    if ( (int) $form['form_id'] !== 1 ) return;

    add_action( 'wp_footer', function () {

        $ajax_url = admin_url( 'admin-ajax.php' );
        $ajaxNonce = wp_create_nonce();( 'ep_entry_verify_nonce' );
        ?>
        <script>
        (function($){
            $(function(){

                // === GLOBAL WP AJAX URL ===
                var ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
                var ajaxNonce = '<?php echo esc_js( $ajaxNonce ); ?>';

                const st = { ajaxUrl: ajaxUrl, nonce: (ajaxNonce) || '' };

                // === DEFINE all address field IDs per page here ===
                var ADDRESSES = {
                    1: { fields: { address1: 37, city: 39, state: 40, zipcode: 41 } },
                };

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

                /** Collect values for a given page block */
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

                function verifyAddressAjax( fields ) {

                    $.post(st.ajaxUrl, {
                        action: 'entry_verify_address',
                        _ajax_nonce: st.nonce,
                        address: JSON.stringify( fields ),
                        strict: 1
                    }).done(function(resp){

                        console.log(resp);

                        /*
                        if (resp && resp.success && resp.data && resp.data.status === 'verified') {
                        setVerifyStatus($group, true, '');
                        setNormalized($group, resp.data.normalized || null);
                        } else {
                        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Verification failed.';
                        setVerifyStatus($group, false, msg);
                        setNormalized($group, null);
                        }
                        */
                    }).fail(function(){
                        /*
                        setVerifyStatus($group, false, 'Server error.');
                        setNormalized($group, null);
                        */
                    });

                }

                // Handle click on Next/Submit
                $(document).on('click', '.frm_button_submit', function(e){
                    var currentPage = getCurrentPageNumber();
                    var collected = {};

                    // If this page has address fields, collect them
                    if (ADDRESSES[currentPage]) {
                        var fields = collectPageValues(currentPage);
                        console.log('Collected ADDRESSES:', fields);

                        verifyAddressAjax( fields );

                        // Prevent default since this page has an address section
                        e.preventDefault();
                        console.log('Submission prevented: address section exists on page ' + currentPage);
                    }
                });

            });
        })(jQuery);
        </script>
        <?php
    });
});