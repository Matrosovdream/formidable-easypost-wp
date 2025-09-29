<?php
/**
 * Shortcodes + AJAX: EasyPost shipments + Void modal
 *
 * Usage:
 *   [easypost-void-modal]         // render ONCE per page
 *   [easypost-shipments entry=123]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ---------- Shortcode: Void Modal ----------
 */
add_shortcode('easypost-void-modal', function () {
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;

    $nonce = wp_create_nonce('easyspot_void_shipment');
    $ajax  = admin_url('admin-ajax.php');

    // Enqueue external assets (URL base via your constant)
    wp_enqueue_style(
        'easyspot-void-modal',
        esc_url(FRM_EAP_BASE_PATH. 'assets/css/easypost-void-modal.css?time=' . time()),
        [],
        null
    );
    wp_enqueue_script(
        'easyspot-void-modal',
        esc_url(FRM_EAP_BASE_PATH. 'assets/js/easypost-void-modal.js?time=' . time()),
        [],
        null,
        true
    );

    ob_start(); ?>
    <div id="easyspot-void-modal-overlay"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-ajax="<?php echo esc_url($ajax); ?>">
        <div id="easyspot-void-modal" role="dialog" aria-modal="true" aria-labelledby="easyspot-void-title">
            <div id="easyspot-void-head">
                <div id="easyspot-void-title">Void entry #<span id="easyspot-void-entry">—</span></div>
                <button id="easyspot-void-close" aria-label="Close">&times;</button>
            </div>
            <div id="easyspot-void-body">
                <div id="easyspot-void-msg">
                    Are you sure you want to void this shipment? This action cannot be undone.
                </div>
                <div id="easyspot-void-result"></div>
                <input type="hidden" id="easyspot-void-easypost-id" value="">
                <input type="hidden" id="easyspot-void-entry-id" value="">
            </div>
            <div id="easyspot-void-actions">
                <button class="easyspot-btn" id="easyspot-void-cancel">Cancel</button>
                <button class="easyspot-btn easyspot-btn-primary" id="easyspot-void-confirm">Void</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * ---------- Shortcode: Shipments list ----------
 */
add_shortcode('easypost-shipments', function ($atts) {
    $atts     = shortcode_atts(['entry' => ''], $atts, 'easypost-shipments');
    $entry_id = (int) $atts['entry'];

    if ($entry_id <= 0) return '<div>Param <code>entry</code> is required.</div>';
    if (!class_exists('FrmEasypostShipmentModel')) return '<div>Shipment model not found.</div>';

    // Enqueue external assets for the list
    $base_url = defined('FRM_EAP_BASE_PATH') ? trailingslashit(FRM_EAP_BASE_PATH) : trailingslashit(plugin_dir_url(__FILE__));
    wp_enqueue_style(
        'easyspot-shipments',
        esc_url(FRM_EAP_BASE_PATH . 'assets/css/easypost-shipments.css?time=' . time()),
        [],
        null
    );
    wp_enqueue_script(
        'easyspot-shipments',
        esc_url(FRM_EAP_BASE_PATH . 'assets/js/easypost-shipments.js?time=' . time()),
        [],
        null,
        true
    );

    try {
        $model     = new FrmEasypostShipmentModel();
        $shipments = $model->getAllByEntryId($entry_id);
    } catch (Throwable $e) {
        return '<div>Failed to load shipments.</div>';
    }

    if (empty($shipments) || !is_array($shipments)) return '<div>No shipments found.</div>';

    ob_start(); ?>
    <div class="easyspot-shipments" data-entry-id="<?php echo esc_attr($entry_id); ?>">
        <?php foreach ($shipments as $s):
            $easypost_id   = (string)($s['easypost_id'] ?? '');
            $tracking      = (string)($s['tracking_code'] ?? '');
            $status        = (string)($s['status'] ?? '');
            $created_at    = (string)($s['created_at'] ?? '');
            $updated_at    = (string)($s['updated_at'] ?? '');
            $label_url     = '';
            $isRefundable  = $s['is_refundable'] ?? false;
            $refundStatus  = (string)($s['refund_status'] ?? '');
            $tracking_url  = (string)($s['tracking_url'] ?? '');

            if( $refundStatus !== '' ) { /* continue; */ }

            if (!empty($s['label']) && is_array($s['label'])) {
                $label_url = (string)($s['label']['label_url'] ?? $s['label']['url'] ?? '');
            }

            $isVoided = ($refundStatus !== '');
        ?>
            <div 
                class="easyspot-shipment" 
                data-easypost-id="<?php echo esc_attr($easypost_id); ?>"
                <?php if( $refundStatus !== '' ) { ?> style="opacity: 0.65" <?php } ?>
                >
                <div class="easyspot-info">
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

                    <div><strong>Status:</strong> <?php echo esc_html($status ?: '—'); ?></div>
                    <?php if( $refundStatus !== '' ) { ?>
                        <div><strong>Refund Status:</strong> <?php echo esc_html($refundStatus); ?></div>
                    <?php } ?>
                    <div class="easyspot-muted"><strong>Created:</strong> <?php echo esc_html($created_at ?: '—'); ?></div>
                    <div class="easyspot-muted"><strong>Updated:</strong> <?php echo esc_html($updated_at ?: '—'); ?></div>
                </div>
                <div class="easyspot-actions">
                    <a href="#"
                       class="easyspot-btn easyspot-btn-primary"
                       onClick="window.open('<?php echo esc_attr($label_url); ?>','Print label','width=610,height=700'); return false;">
                        Print Label
                    </a>

                    <?php if ($isVoided) { ?>
                        <!-- Big "Voided" flag placed right next to Print Label -->
                        <span class="easyspot-voided-flag" aria-label="Shipment has been voided">
                            Voided
                        </span>
                    <?php } ?>

                    <?php if( $isRefundable ) { ?>
                        <button class="easyspot-btn js-easyspot-void-open"
                                data-easypost-id="<?php echo esc_attr($easypost_id); ?>"
                                data-entry-id="<?php echo esc_attr($entry_id); ?>"
                                <?php echo empty($easypost_id) ? 'disabled' : ''; ?>>
                            Void
                        </button>
                    <?php } ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});
