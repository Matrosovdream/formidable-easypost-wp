<?php
/**
 * Shortcodes + AJAX: EasyPost shipments + Void modal
 *
 * Usage:
 *   [easypost-void-modal]
 *   [easypost-shipments entry=123]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ---------- Shortcode: Void Modal ----------
 */
add_shortcode('easypost-void-modal', function () {
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    $nonce = wp_create_nonce('easyspot_void_shipment');
    $ajax  = admin_url('admin-ajax.php');

    wp_enqueue_style(
        'easyspot-void-modal',
        esc_url(FRM_EAP_BASE_PATH . 'assets/css/easypost-void-modal.css?time=' . time()),
        [],
        null
    );
    wp_enqueue_script(
        'easyspot-void-modal',
        esc_url(FRM_EAP_BASE_PATH . 'assets/js/easypost-void-modal.js?time=' . time()),
        [],
        null,
        true
    );

    ob_start(); ?>
    <div id="easyspot-void-modal-overlay"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-ajax="<?php echo esc_url($ajax); ?>">
        <div id="easyspot-void-modal" role="dialog" aria-modal="true">
            <div id="easyspot-void-head">
                <div id="easyspot-void-title">Void entry #<span id="easyspot-void-entry">—</span></div>
                <button id="easyspot-void-close">&times;</button>
            </div>
            <div id="easyspot-void-body">
                <div id="easyspot-void-msg">
                    Are you sure you want to void this shipment? This action cannot be undone.
                </div>
                <div id="easyspot-void-result"></div>
                <input type="hidden" id="easyspot-void-easypost-id">
                <input type="hidden" id="easyspot-void-entry-id">
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

    static $history_js = false;

    $atts     = shortcode_atts(['entry' => ''], $atts);
    $entry_id = (int) $atts['entry'];

    if ($entry_id <= 0) return '<div>Param <code>entry</code> is required.</div>';
    if (!class_exists('FrmEasypostShipmentModel')) return '<div>Shipment model not found.</div>';

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

    $model     = new FrmEasypostShipmentModel();
    $shipments = $model->getAllByEntryId($entry_id);

    if (empty($shipments)) return '<div>No shipments found.</div>';

    ob_start(); ?>
    <div class="easyspot-shipments" data-entry-id="<?php echo esc_attr($entry_id); ?>">

        <?php foreach ($shipments as $s):

            $easypost_id  = (string)($s['easypost_id'] ?? '');
            $tracking     = (string)($s['tracking_code'] ?? '');
            $status       = (string)($s['status'] ?? '');
            $created_at   = (string)($s['created_at'] ?? '');
            $updated_at   = (string)($s['updated_at'] ?? '');
            $label_url    = (string)($s['label']['label_url'] ?? '');
            $isRefundable = (bool)($s['is_refundable'] ?? false);
            $refundStatus = (string)($s['refund_status'] ?? '');
            $history      = $s['history'] ?? [];
            $has_history  = is_array($history) && !empty($history);

            $history_id = 'easyspot-history-' . $entry_id . '-' . preg_replace('/[^a-z0-9]/i', '', $easypost_id);
        ?>

        <div class="easyspot-shipment" <?php if ($refundStatus) echo 'style="opacity:.65"'; ?>>

            <!-- INFO -->
            <div class="easyspot-info">

                <div style="display:flex;gap:8px;align-items:center;">
                    <strong>Track Number:</strong>
                    <span class="easyspot-badge">#<?php echo esc_html($tracking); ?></span>
                </div>

                <div><strong>Status:</strong> <?php echo esc_html($status ?: '—'); ?></div>

                <?php if ($refundStatus): ?>
                    <div><strong>Refund Status:</strong> <?php echo esc_html($refundStatus); ?></div>
                <?php endif; ?>

                <div class="easyspot-muted"><strong>Created:</strong> <?php echo esc_html($created_at); ?></div>
                <div class="easyspot-muted"><strong>Updated:</strong> <?php echo esc_html($updated_at); ?></div>

                <?php if ($has_history): ?>
                    <!-- HISTORY BUTTON -->
                    <div style="margin-top:10px;text-align:right;">
                        <button class="easyspot-btn easyspot-btn-secondary js-easyspot-history-toggle"
                                data-target="<?php echo esc_attr($history_id); ?>">
                            History
                        </button>
                    </div>

                    <!-- HISTORY TABLE (directly under button) -->
                    <div id="<?php echo esc_attr($history_id); ?>"
                         class="easyspot-history-wrap"
                         style="display:none;margin-top:10px;">

                        <table class="easyspot-history-table"
                               style="width:100%;border-collapse:collapse;">
                            <thead>
                            <tr>
                                <th>Change type</th>
                                <th>Date</th>
                                <th>User</th>
                                <th>Description</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?php echo esc_html($h['change_type'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($h['created_at'] ?? '—'); ?></td>
                                    <td>
                                        <?php echo esc_html($h['user']['name'] ?? '—'); ?> (<?php echo esc_html($h['user']['email'] ?? '—'); ?>)
                                    </td>
                                    <td><?php echo esc_html($h['description'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                    </div>
                <?php endif; ?>

            </div>

            <!-- ACTIONS -->
            <div class="easyspot-actions">
                <a href="#"
                   class="easyspot-btn easyspot-btn-primary"
                   onclick="window.open('<?php echo esc_attr($label_url); ?>','Print','width=610,height=700');return false;">
                    Print Label
                </a>

                <?php if ($refundStatus): ?>
                    <span class="easyspot-voided-flag">Voided</span>
                <?php endif; ?>

                <?php if ($isRefundable): ?>
                    <button class="easyspot-btn js-easyspot-void-open"
                            data-easypost-id="<?php echo esc_attr($easypost_id); ?>"
                            data-entry-id="<?php echo esc_attr($entry_id); ?>">
                        Void
                    </button>
                <?php endif; ?>
            </div>

        </div>

        <?php endforeach; ?>
    </div>

    <?php if (!$history_js): $history_js = true; ?>
        <script>
        document.addEventListener('click', function(e){
            const btn = e.target.closest('.js-easyspot-history-toggle');
            if (!btn) return;

            const wrap = document.getElementById(btn.dataset.target);
            if (!wrap) return;

            const open = wrap.style.display === 'block';
            wrap.style.display = open ? 'none' : 'block';
            btn.textContent = open ? 'History' : 'Hide History';
        });
        </script>
    <?php endif;

    return ob_get_clean();
});
