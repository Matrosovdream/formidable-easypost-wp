<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode: [frm-shipments-list]
 * Same visual style as frm-emails-list, plus:
 * - Per-row "Show details" toggle (addresses, parcel, rate)
 * - "Void" button (AJAX post to easyspot_void_shipment)
 */
add_action('init', function () {
    add_shortcode('frm-shipments-list', 'frm_shipments_list_shortcode');
});

function frm_shipments_list_shortcode($atts = []) {
    if ( ! class_exists('FrmEasypostShipmentModel') ) {
        return '<div style="color:#b00">FrmEasypostShipmentModel not found.</div>';
    }

    // Enqueue external CSS (resource connection style)
    wp_enqueue_style(
        'frm-shipments-list',
        esc_url(FRM_EAP_BASE_PATH . 'assets/css/easypost-shipments-list.css?time=' . time()),
        [],
        null
    );

    wp_enqueue_script(
        'frm-shipments-list-js',
        esc_url(FRM_EAP_BASE_PATH . 'assets/js/easypost-shipments-list.js?time=' . time()),
        ['jquery'],
        null,
        true
    );

    global $wpdb;
    $table = $wpdb->prefix . 'frm_easypost_shipments';

    // Read query
    $q = wp_unslash($_GET);

    // Raw first, then cleaned versions
    $entry_id_raw    = array_key_exists('entry_id', $q) ? trim((string)$q['entry_id']) : '';
    $entry_id        = ($entry_id_raw !== '' && ctype_digit($entry_id_raw) && (int)$entry_id_raw > 0) ? (int)$entry_id_raw : null;

    $tracking_raw    = isset($q['tracking_code']) ? trim((string)$q['tracking_code']) : '';
    $status_raw      = isset($q['status']) ? trim((string)$q['status']) : '';
    $created_from    = isset($q['created_from']) ? trim((string)$q['created_from']) : '';
    $created_to      = isset($q['created_to']) ? trim((string)$q['created_to']) : '';

    // Paging/sort (keep same param names as emails list)
    $page      = isset($q['fel_page'])     ? max(1, (int)$q['fel_page']) : 1;
    $per_page  = isset($q['fel_per_page']) ? max(1, (int)$q['fel_per_page']) : 25;
    $order     = isset($q['fel_order'])    ? strtoupper((string)$q['fel_order']) : 'DESC';
    $order     = in_array($order, ['ASC','DESC'], true) ? $order : 'DESC';

    // Only sort by entry_id as requested
    $order_by = 'created_at';

    // Build WHERE + params (contains for tracking_code; exact for status; date range on created_at)
    $where  = [];
    $params = [];

    if ($entry_id !== null) {
        $where[]  = 'entry_id = %d';
        $params[] = (int)$entry_id;
    }

    if ($tracking_raw !== '') {
        $like     = '%' . $wpdb->esc_like($tracking_raw) . '%';
        $where[]  = 'tracking_code LIKE %s';
        $params[] = $like;
    }

    if ($status_raw !== '') {
        $where[]  = 'status = %s';
        $params[] = $status_raw;
    }

    if ($created_from !== '') {
        $where[]  = 'created_at >= %s';
        $params[] = frm_shipments_to_mysql_dt($created_from, false);
    }
    if ($created_to !== '') {
        $where[]  = 'created_at <= %s';
        $params[] = frm_shipments_to_mysql_dt($created_to, true);
    }

    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Count
    $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
    $count_prepared = $params ? $wpdb->prepare($count_sql, $params) : $count_sql;
    $total = (int)$wpdb->get_var($count_prepared);
    if ($wpdb->last_error) {
        return '<div style="color:#b00">DB error: ' . esc_html($wpdb->last_error) . '</div>';
    }

    // Rows
    $offset = ($page - 1) * $per_page;
    $rows_sql = "SELECT id, easypost_id, entry_id, status, refund_status, tracking_code, tracking_url, created_at
                 FROM {$table}
                 {$where_sql}
                 ORDER BY {$order_by} {$order}
                 LIMIT %d OFFSET %d";

    $rows_args = array_merge($params, [ $per_page, $offset ]);
    $rows_prepared = $wpdb->prepare($rows_sql, $rows_args);
    $rows_min = $wpdb->get_results($rows_prepared, ARRAY_A);
    if ($rows_min === null) {
        return '<div style="color:#b00">DB query failed.</div>';
    }

    // Expand each row via model (to get addresses/parcel/label/rate)
    $shipmentModel = new FrmEasypostShipmentModel();
    $rows = [];
    foreach ($rows_min as $row) {
        $rows[] = $shipmentModel->getById( (int)$row['id'] );
    }

    // Status labels via the model
    $status_labels = $shipmentModel->shipmentStatuses();

    // Persist current filters when building links
    $persist = [
        'entry_id'      => $entry_id_raw !== '' ? $entry_id_raw : null,
        'tracking_code' => $tracking_raw ?: null,
        'status'        => $status_raw ?: null,
        'created_from'  => $created_from ?: null,
        'created_to'    => $created_to ?: null,
        'fel_per_page'  => $per_page,
        'fel_order'     => $order,
    ];
    $persist = array_filter($persist, fn($v) => $v !== null && $v !== '');

    $build_url = function(array $overrides = []) use ($persist) {
        $args = array_merge($persist, $overrides);
        return esc_url( add_query_arg($args, get_permalink()) );
    };

    // Total pages etc.
    $total_pages = max(1, (int)ceil($total / $per_page));
    $toggle_order = ($order === 'ASC') ? 'DESC' : 'ASC';
    $sort_url = $build_url(['fel_order' => $toggle_order, 'fel_page' => 1]);

    // Nonce for voiding
    $void_nonce = wp_create_nonce('easyspot_void_shipment');
    $ajax_url   = admin_url('admin-ajax.php');

    /*
    echo "<pre>";
    print_r($rows);
    echo "</pre>";
    */

    ob_start();
    ?>

    <?php
    // Void confirmation modal
    echo do_shortcode('[easypost-void-modal]');  
    ?>

    <div class="frm-emails-wrap frm-shipments-wrap">
        <?php $reset_url = esc_url( get_permalink() ); ?>
        <form method="get" class="frm-emails-filters" action="<?php echo esc_url( get_permalink() ); ?>">
            <div class="field">
                <label for="fs-entry">Entry ID</label>
                <input id="fs-entry" type="number" name="entry_id" value="<?php echo esc_attr($entry_id_raw); ?>" placeholder="e.g. 14485">
            </div>
            <div class="field">
                <label for="fs-tracking">Tracking Code</label>
                <input id="fs-tracking" type="text" name="tracking_code" value="<?php echo esc_attr($tracking_raw); ?>" placeholder="contains...">
            </div>
            <div class="field">
                <label for="fs-status">Status</label>
                <select id="fs-status" name="status">
                    <option value="">— Any —</option>
                    <?php foreach ($status_labels as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($status_raw, $val); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="fs-created-from">Created From</label>
                <input id="fs-created-from" type="date" name="created_from" value="<?php echo esc_attr($created_from); ?>">
            </div>
            <div class="field">
                <label for="fs-created-to">Created To</label>
                <input id="fs-created-to" type="date" name="created_to" value="<?php echo esc_attr($created_to); ?>">
            </div>

            <!-- carry current sort + per-page, and reset to page 1 on filter -->
            <input type="hidden" name="fel_order" value="<?php echo esc_attr($order); ?>">
            <input type="hidden" name="fel_per_page" value="<?php echo esc_attr($per_page); ?>">
            <input type="hidden" name="fel_page" value="1">

            <div class="field">
                <label>&nbsp;</label>
                <button class="submit-btn" type="submit">Filter</button>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <a class="reset-btn" href="<?php echo $reset_url; ?>">Reset</a>
            </div>
            <div class="field">
                <label for="fs-per-page">Rows</label>
                <select id="fs-per-page" name="fel_per_page" onchange="this.form.submit()">
                    <?php foreach ([10,25,50,100] as $opt): ?>
                        <option value="<?php echo (int)$opt; ?>" <?php selected($per_page, $opt); ?>><?php echo (int)$opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <table class="frm-emails-table frm-shipments-table">
            <thead>
            <tr>
                <th><a href="<?php echo esc_url($sort_url); ?>">Entry ID <?php echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                <th>Tracking Code</th>
                <th>Status</th>
                <th>Refund Status</th>
                <th>Created At</th>
                <th>Tracking URL</th>
                <th style="text-align:right;">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="frm-empty">No results.</td></tr>
            <?php else: foreach ($rows as $r):
                $id           = (int)($r['id'] ?? 0);
                $ep_id        = (string)($r['easypost_id'] ?? '');
                $entryVal     = (int)($r['entry_id'] ?? 0);
                $trackingCode = (string)($r['tracking_code'] ?? '');
                $statusVal    = (string)($r['status'] ?? '');
                $statusText   = $status_labels[$statusVal] ?? $statusVal;
                $refund       = (string)($r['refund_status'] ?? '');
                $createdRaw   = (string)($r['created_at'] ?? '');
                $createdFmt   = $createdRaw ? date_i18n('Y-m-d H:i', strtotime($createdRaw)) : '';
                $url          = trim((string)($r['tracking_url'] ?? ''));
                $isRefundable = $r['is_refundable'] ?? false;

                // Sub-blocks
                $addrFrom = $r['addresses']['from'] ?? [];
                $addrTo   = $r['addresses']['to'] ?? [];
                $parcel   = $r['parcel'] ?? [];
                $rate     = $r['rate'] ?? [];
                $rowKey   = 'fs-details-' . $id;
            ?>
                <!-- Main row -->
                <tr>
                    <td class="entry-id-cell">
                        <?php echo $entryVal ?: ''; ?>
                        <button type="button"
                                class="fs-details-toggle"
                                data-target="<?php echo esc_attr($rowKey); ?>"
                                aria-expanded="false">
                            Show details
                        </button>
                    </td>
                    <td class="frm-td-break"><?php echo esc_html($trackingCode); ?></td>
                    <td><?php echo esc_html($statusText); ?></td>
                    <td><?php echo esc_html($refund); ?></td>
                    <td><?php echo esc_html($createdFmt); ?></td>
                    <td>
                        <?php if ($url !== ''): ?>
                            <a class="open-link-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Open</a>
                        <?php else: ?>
                            <span class="frm-dash">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">

                        <?php if ($isRefundable): ?>
                            <button type="button"
                                    class="button button-link-delete fs-void-btn"
                                    id="easypost-void-confirm"
                                    data-void-nonce="<?php echo esc_attr($void_nonce); ?>"
                                    data-ajax="<?php echo esc_url($ajax_url); ?>"
                                    data-ep-id="<?php echo esc_attr($ep_id); ?>"
                                    data-row-id="<?php echo $entryVal ?: ''; ?>">
                                Void
                            </button>
                        <?php endif; ?>
                        
                    </td>
                </tr>

                <!-- Details row (hidden by default) -->
                <tr id="<?php echo esc_attr($rowKey); ?>" class="fs-details-row" style="display:none;">
                    <td colspan="7">
                        <div class="fs-details-wrap">
                            <div class="fs-details-grid">
                                <div class="fs-card">
                                    <div class="fs-card-title">Address From</div>
                                    <?php echo fs_render_address_block($addrFrom); ?>
                                </div>
                                <div class="fs-card">
                                    <div class="fs-card-title">Address To</div>
                                    <?php echo fs_render_address_block($addrTo); ?>
                                </div>
                                <div class="fs-card">
                                    <div class="fs-card-title">Parcel</div>
                                    <table class="fs-mini-table">
                                        <tbody>
                                        <tr><th>Length</th><td><?php echo esc_html($parcel['length'] ?? ''); ?></td></tr>
                                        <tr><th>Width</th><td><?php echo esc_html($parcel['width'] ?? ''); ?></td></tr>
                                        <tr><th>Height</th><td><?php echo esc_html($parcel['height'] ?? ''); ?></td></tr>
                                        <tr><th>Weight</th><td><?php echo esc_html($parcel['weight'] ?? ''); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="fs-card">
                                    <div class="fs-card-title">Rate</div>
                                    <table class="fs-mini-table">
                                        <tbody>
                                        <tr><th>Service</th><td><?php echo esc_html($rate['service'] ?? ''); ?></td></tr>
                                        <tr><th>Carrier</th><td><?php echo esc_html($rate['carrier'] ?? ''); ?></td></tr>
                                        <tr><th>Rate</th><td><?php echo esc_html(isset($rate['rate']) ? (string)$rate['rate'] : ''); ?> <?php echo esc_html($rate['currency'] ?? ''); ?></td></tr>
                                        <tr><th>Delivery days</th><td><?php echo esc_html($rate['delivery_days'] ?? ''); ?></td></tr>
                                        <tr><th>Delivery date</th><td><?php echo esc_html($rate['delivery_date'] ?? ''); ?></td></tr>
                                        <tr><th>Guaranteed</th><td><?php echo !empty($rate['delivery_date_guaranteed']) ? 'Yes' : 'No'; ?></td></tr>
                                        <tr><th>Est. days</th><td><?php echo esc_html($rate['est_delivery_days'] ?? ''); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php
        $total_pages = max(1, $total_pages);
        $prev_page = max(1, $page - 1);
        $next_page = min($total_pages, $page + 1);
        $page_url = fn($n) => $build_url(['fel_page' => max(1,(int)$n)]);
        ?>
        <div class="frm-emails-pager">
            <div class="pages">
                <a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page_url($prev_page); ?>">Prev</a>
                <?php
                $window = 3;
                $start  = max(1, $page - $window);
                $end    = min($total_pages, $page + $window);
                if ($start > 1) {
                    echo '<a href="' . $page_url(1) . '">1</a>';
                    if ($start > 2) echo '<span>…</span>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    $cls = $i === $page ? 'active' : '';
                    echo '<a class="' . $cls . '" href="' . $page_url($i) . '">' . $i . '</a>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="' . $page_url($total_pages) . '">' . $total_pages . '</a>';
                }
                ?>
                <a class="<?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page_url($next_page); ?>">Next</a>
            </div>
            <div>
                <span class="frm-muted">Showing page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?> (<?php echo (int)$total; ?> total)</span>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * Normalize date inputs to MySQL DATETIME boundaries
 * - Accepts YYYY-MM-DD or any strtotime()-parsable value
 */
function frm_shipments_to_mysql_dt($val, $endOfDay = false) {
    $val = trim((string)$val);
    if ($val === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        $val .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
    }
    $ts = strtotime($val);
    return $ts ? gmdate('Y-m-d H:i:s', $ts) : $val;
}

/**
 * Small renderer for address blocks
 * Expected keys:
 *  name, company, street1, street2, city, state, zip, country, phone, email
 */
function fs_render_address_block($addr) {
    $fields = [
        'name'    => 'Name',
        'company' => 'Company',
        'street1' => 'Address 1',
        'street2' => 'Address 2',
        'city'    => 'City',
        'state'   => 'State',
        'zip'     => 'ZIP',
        'country' => 'Country',
        'phone'   => 'Phone',
        'email'   => 'Email',
    ];

    ob_start(); ?>
    <table class="fs-mini-table">
        <tbody>
        <?php foreach ($fields as $k => $label): ?>
            <tr>
                <th><?php echo esc_html($label); ?></th>
                <td><?php echo esc_html( isset($addr[$k]) ? (string)$addr[$k] : '' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
