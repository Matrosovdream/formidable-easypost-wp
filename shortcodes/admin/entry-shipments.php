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
    $nonce = wp_create_nonce('easyspot_void_shipment');
    $ajax  = admin_url('admin-ajax.php');

    ob_start(); ?>
    <style>
        #easyspot-void-modal-overlay{
            position:fixed; inset:0;
            background:rgba(0,0,0,.45);
            display:none; opacity:0;
            z-index:2147483647 !important;
            pointer-events:auto;
            align-items:flex-start;
            justify-content:center;
            padding:8vh 12px;
        }
        #easyspot-void-modal-overlay.show{
            display:flex;
            opacity:1;
        }
        #easyspot-void-modal{
            position:relative;
            width:min(560px, 92vw);
            background:#fff;
            border-radius:12px;
            box-shadow:0 20px 60px rgba(0,0,0,.35);
            overflow:hidden;
            font-size:14px;
            z-index:2147483647 !important;
        }
        #easyspot-void-head{ padding:14px 16px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
        #easyspot-void-title{ font-weight:600; }
        #easyspot-void-close{ background:transparent; border:0; font-size:18px; line-height:1; cursor:pointer; }
        #easyspot-void-body{ padding:16px; }
        #easyspot-void-msg{ margin:8px 0 14px; color:#333; }
        #easyspot-void-result{ margin-top:10px; padding:10px; border-radius:6px; display:none; }
        #easyspot-void-result.success{ background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
        #easyspot-void-result.error{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        #easyspot-void-actions{ display:flex; gap:8px; justify-content:flex-end; padding:12px 16px; border-top:1px solid #eee; }
        .easyspot-btn{ padding:8px 14px; border:1px solid #cfcfcf; background:#f8f8f8; cursor:pointer; border-radius:6px; font-size:13px; line-height:1.2; }
        .easyspot-btn:hover{ background:#f1f1f1; }
        .easyspot-btn[disabled]{ opacity:.6; cursor:not-allowed; }
        .easyspot-btn-primary{ background:#2563eb; color:#fff; border-color:#1d4ed8; }
        .easyspot-btn-primary:hover{ background:#1d4ed8; }
        body.easyspot-modal-open{ overflow:hidden !important; }
    </style>

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

    <script>
    (function(){
        const overlay   = document.getElementById('easyspot-void-modal-overlay');
        if (!overlay) return;
        const modal     = document.getElementById('easyspot-void-modal');
        const btnClose  = document.getElementById('easyspot-void-close');
        const btnCancel = document.getElementById('easyspot-void-cancel');
        const btnConfirm= document.getElementById('easyspot-void-confirm');
        const resBox    = document.getElementById('easyspot-void-result');
        const entrySpan = document.getElementById('easyspot-void-entry');
        const fieldId   = document.getElementById('easyspot-void-easypost-id');
        const fieldEntry= document.getElementById('easyspot-void-entry-id');
        const AJAX = overlay.dataset.ajax || '';
        const NONCE= overlay.dataset.nonce || '';

        // Ensure overlay is in body root
        if (overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }

        function openModal(easypostId, entryId) {
            fieldId.value    = easypostId || '';
            fieldEntry.value = entryId || '';
            entrySpan.textContent = entryId || '—';
            resBox.style.display = 'none';
            resBox.className = '';
            resBox.textContent = '';
            btnConfirm.disabled = false;

            overlay.classList.add('show');
            overlay.style.display = 'flex';
            document.body.classList.add('easyspot-modal-open');
            btnClose.focus();
        }
        function closeModal() {
            overlay.classList.remove('show');
            overlay.style.display='none';
            document.body.classList.remove('easyspot-modal-open');
        }

        window.easyspotOpenVoidModal = openModal;

        btnClose.addEventListener('click', closeModal);
        btnCancel.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e){
            if (e.target === overlay) closeModal();
        });
        document.addEventListener('keydown', (ev)=>{
            if (overlay.classList.contains('show') && ev.key === 'Escape') closeModal();
        });

        btnConfirm.addEventListener('click', async function(){
            const easypostId = fieldId.value.trim();
            if (!easypostId) {
                resBox.textContent = 'Missing shipment ID.';
                resBox.className = 'error';
                resBox.style.display = 'block';
                return;
            }
            btnConfirm.disabled = true;
            const orig = btnConfirm.textContent;
            btnConfirm.textContent = 'Voiding…';

            try {
                const body = new URLSearchParams();
                body.set('action','easyspot_void_shipment');
                body.set('easypost_id', easypostId);
                body.set('nonce', NONCE);

                const resp = await fetch(AJAX, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                });
                const json = await resp.json();

                if (json && json.success) {
                    resBox.textContent = 'Success: the shipment has been voided.';
                    resBox.className = 'success';
                    resBox.style.display = 'block';

                    const row = document.querySelector('.easyspot-shipment[data-easypost-id="'+CSS.escape(easypostId)+'"]');
                    if (row) {
                        row.style.opacity = '0.65';
                        const btn = row.querySelector('.js-easyspot-void-open');
                        if (btn) { btn.textContent = 'Voided'; btn.disabled = true; }
                        const statusEl = row.querySelector('.easyspot-info div:nth-child(2)');
                        if (statusEl) { statusEl.innerHTML = '<strong>Status:</strong> Voided'; }
                    }
                } else {
                    const msg = (json && json.data && json.data.message) ? json.data.message : 'Error voiding shipment.';
                    resBox.textContent = msg;
                    resBox.className = 'error';
                    resBox.style.display = 'block';
                    btnConfirm.disabled = false;
                }
            } catch (e) {
                resBox.textContent = 'Network error.';
                resBox.className = 'error';
                resBox.style.display = 'block';
                btnConfirm.disabled = false;
            } finally {
                btnConfirm.textContent = orig;
            }
        });
    })();
    </script>
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

    try {
        $model     = new FrmEasypostShipmentModel();
        $shipments = $model->getAllByEntryId($entry_id);
    } catch (Throwable $e) {
        return '<div>Failed to load shipments.</div>';
    }

    if (empty($shipments) || !is_array($shipments)) return '<div>No shipments found.</div>';

    ob_start(); ?>
    <style>
        .easyspot-shipments { margin: 10px 0; }
        .easyspot-shipment {
            border: 1px solid #ddd; padding: 12px; margin-bottom: 12px;
            display: flex; justify-content: space-between; gap: 12px;
            align-items: center; font-size: 14px; border-radius: 6px; background: #fff;
        }
        .easyspot-info { flex: 1; min-width: 0; }
        .easyspot-info div { margin-bottom: 4px; }
        .easyspot-actions { display: flex; gap: 8px; }
        .easyspot-btn {
            padding: 6px 12px; border: 1px solid #ccc; background: #f8f8f8;
            cursor: pointer; border-radius: 4px; font-size: 13px; line-height: 1.2;
        }
        .easyspot-btn:hover { background: #f1f1f1; }
        .easyspot-btn[disabled] { opacity: 0.6; cursor: not-allowed; }
        .easyspot-badge {
            display: inline-block; padding: 2px 6px; border-radius: 3px;
            font-size: 12px; line-height: 1.3; background: #eef2ff; color: #1e3a8a; border: 1px solid #c7d2fe;
        }
        .easyspot-muted { color: #666; }
        .easyspot-btn-primary{ background:#2563eb; color:#fff; border-color:#1d4ed8; }
        .easyspot-btn-primary:hover{ background:#1d4ed8; }
    </style>

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

            if (!empty($s['label']) && is_array($s['label'])) {
                $label_url = (string)($s['label']['label_url'] ?? $s['label']['url'] ?? '');
            }
        ?>
            <div class="easyspot-shipment" data-easypost-id="<?php echo esc_attr($easypost_id); ?>">
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

    <script>
    (function(){
        document.addEventListener('click', function(e){
            const btn = e.target.closest('.js-easyspot-void-open');
            if (!btn) return;
            e.preventDefault();
            if (btn.disabled) return;
            const easypostId = btn.getAttribute('data-easypost-id') || '';
            const entryId    = btn.getAttribute('data-entry-id') || '';
            if (typeof window.easyspotOpenVoidModal !== 'function') {
                alert('Void modal not available. Place [easypost-void-modal] on this page.');
                return;
            }
            window.easyspotOpenVoidModal(easypostId, entryId);
        });
    })();
    </script>
    <?php
    return ob_get_clean();
});

/**
 * ---------- AJAX: Void shipment ----------
 */
add_action('wp_ajax_easyspot_void_shipment', 'easyspot_ajax_void_shipment');
add_action('wp_ajax_nopriv_easyspot_void_shipment', 'easyspot_ajax_void_shipment');
function easyspot_ajax_void_shipment() {
    check_ajax_referer('easyspot_void_shipment', 'nonce');
    $easypost_id = sanitize_text_field($_POST['easypost_id'] ?? '');
    if ($easypost_id === '') wp_send_json_error(['message' => 'Missing ID']);
    if (!class_exists('FrmEasypostShipmentApi') || !class_exists('FrmEasypostShipmentHelper'))
        wp_send_json_error(['message' => 'Required classes not found']);
    try {

        // Make refund
        $shipmentApi = new FrmEasypostShipmentApi();
        $label = $shipmentApi->refundShipment($easypost_id);

        if( $label['ok'] ) {
            // Update all shipments
            $shipmentHelper = new FrmEasypostShipmentHelper();
            if (method_exists($shipmentHelper, 'updateShipmentsApi')) $shipmentHelper->updateShipmentsApi();

            wp_send_json_success($label);
        } else {

            // Send error
            wp_send_json_error($label);
        }

    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'API error: '.$e->getMessage()]);
    }
}
