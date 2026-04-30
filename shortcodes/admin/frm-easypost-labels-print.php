<?php

add_shortcode('frm-easypost-labels-print', function($atts = []) {

    $urls = [];


    if( isset( $_GET['shipment_ids'] ) ) {

        $shipment_ids = explode(',', sanitize_text_field($_GET['shipment_ids']));

        $labelModel = new FrmEasypostShipmentLabelModel();
        $labels = $labelModel->getAllByShipmentIds($shipment_ids);

        foreach($labels as $label) {
            if( !empty($label['label_url']) ) {
                $urls[] = $label['label_url'];
            }
        }

    }

    $urls = array_values(array_filter(array_map('esc_url_raw', $urls)));
    if (empty($urls)) {
        return '<div>No labels configured.</div>';
    }

    $uid = 'ep_print_area_' . wp_rand(1000, 999999);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="ep-print-area">
        <button type="button" class="ep-print-btn">Print</button>

        <div class="ep-print-images">
            <?php foreach ($urls as $u): ?>
                <img class="ep-print-img" src="<?php echo esc_url($u); ?>" alt="Label" loading="lazy" />
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        /* On-screen styling */
        #<?php echo esc_attr($uid); ?>.ep-print-area{
            position: relative;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            background: #fff;
            max-width: 920px;
        }
        #<?php echo esc_attr($uid); ?> .ep-print-btn{
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #111827;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }
        #<?php echo esc_attr($uid); ?> .ep-print-images{
            margin-top: 42px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        #<?php echo esc_attr($uid); ?> .ep-print-img{
            width: 98%;
            max-width: 900px;
            height: auto;
            display: flex;
            padding:  5px 5px 0px 12px;
        }

        /* PRINT: hide everything except this shortcode block */
        @media print {
            body *{
                visibility: hidden !important;
            }
            #<?php echo esc_attr($uid); ?>,
            #<?php echo esc_attr($uid); ?> *{
                visibility: visible !important;
            }
            #<?php echo esc_attr($uid); ?>{
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: none !important;
                border: none !important;
                padding: 0 !important;
            }
            #<?php echo esc_attr($uid); ?> .ep-print-btn{
                display: none !important;
            }
            #<?php echo esc_attr($uid); ?> .ep-print-images{
                margin-top: 0 !important;
                gap: 0 !important;
            }
            #<?php echo esc_attr($uid); ?> .ep-print-img{
                border: none !important;
                border-radius: 0 !important;
                page-break-after: always;
                break-after: page;
            }
            #<?php echo esc_attr($uid); ?> .ep-print-img:last-child{
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>

    <script>
    (function(){
        var root = document.getElementById(<?php echo json_encode($uid); ?>);
        if(!root) return;

        var btn = root.querySelector('.ep-print-btn');
        btn.addEventListener('click', function(){
            // Ensure images start loading before print
            window.print();
        });
    })();
    </script>
    <?php

    return ob_get_clean();
});