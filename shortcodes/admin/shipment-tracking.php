<?php
/**
 * Shortcode: [shipment-tracking code="9470100208303111134530"]
 * Shows the "Track Number" block for a single shipment found by tracking code.
 * Parameters:
 * - code (required): The tracking code to look for.
 * - mode (optional): Display mode, either "full" (default) or "url".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('init', function () {
    add_shortcode('shipment-tracking', 'ep_short_shipment_tracking');
});

function ep_short_shipment_tracking($atts) {

    $atts  = shortcode_atts(['code' => '', 'mode' => 'full'], $atts, 'shipment-tracking');
    $code  = trim((string)$atts['code']);
    $mode = trim((string)$atts['mode']);

    if ($code === '') {
        return '<div class="ep-track-error">Param <code>code</code> is required.</div>';
    }

    if (!class_exists('FrmEasypostShipmentModel')) {
        return '<div class="ep-track-error">Shipment model not found.</div>';
    }

    try {
        $model   = new FrmEasypostShipmentModel();
        $shipment = $model->getByTrackingCode($code);
    } catch (Throwable $e) {
        return '<div class="ep-track-error">Failed to load shipment.</div>';
    }

    if (empty($shipment) || !is_array($shipment)) {
        return '<div class="ep-track-empty">No shipment found for code <code>' . esc_html($code) . '</code>.</div>';
    }

    $tracking     = (string)($shipment['tracking_code'] ?? '');
    $tracking_url = (string)($shipment['tracking_url'] ?? '');

    ob_start(); ?>

    <?php if( $mode == 'url' ) { ?>

        <?php echo $tracking_url ?? ''; ?>

    <?php } else { ?>     

        <style>
            .easyspot-badge{
                display:inline-block; padding:2px 6px; border-radius:3px;
                font-size:12px; line-height:1.3; background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe;
                text-decoration:none;
            }
        </style>

        <div class="ep-track-item" data-code="<?php echo esc_attr($code); ?>">
            <div style="display:flex;align-items:center;gap:8px;">
                <strong>Track Number:</strong>

                <?php if ($tracking_url !== '') { ?>
                    <a href="<?php echo esc_url($tracking_url); ?>"
                    target="_blank"
                    rel="noopener"
                    class="easyspot-badge">
                    #<?php echo esc_html($tracking); ?>
                    </a>
                <?php } else { ?>
                    <span class="easyspot-badge">#<?php echo esc_html($tracking); ?></span>
                <?php } ?>
            </div>
        </div>

    <?php } ?>

    <?php
    return ob_get_clean();
}
