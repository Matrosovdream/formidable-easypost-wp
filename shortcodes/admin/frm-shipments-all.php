<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode: [frm-shipments-list]
 * Same visual style as frm-emails-list
 */
add_action('init', function () {
    add_shortcode('frm-shipments-list', 'frm_shipments_list_shortcode');
});

function frm_shipments_list_shortcode($atts = []) {
    if ( ! class_exists('FrmEasypostShipmentModel') ) {
        return '<div style="color:#b00">FrmEasypostShipmentModel not found.</div>';
    }

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
    $order_by = 'entry_id';

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
    $rows = $wpdb->get_results($rows_prepared, ARRAY_A);
    if ($rows === null) {
        return '<div style="color:#b00">DB query failed.</div>';
    }

    // Status labels via the model
    $model = new FrmEasypostShipmentModel();
    $status_labels = $model->shipmentStatuses();

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

    ob_start();
    ?>
    <style>
        /* Reuse the exact same styling block from frm-emails-list */
        .frm-emails-wrap { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
        .frm-emails-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin-bottom:12px; }
        .frm-emails-filters .field { display:flex; flex-direction:column; }
        .frm-emails-filters input[type="text"], .frm-emails-filters input[type="number"], .frm-emails-filters input[type="date"], .frm-emails-filters select {
            padding:6px 8px; border:1px solid #d0d7de; border-radius:6px; min-width:160px;
        }
        .frm-emails-filters .submit-btn { padding:8px 12px; border:1px solid #1f6feb; background:#1f6feb; color:#fff; border-radius:6px; cursor:pointer; }
        .frm-emails-filters .reset-btn { padding:8px 12px; border:1px solid #d0d7de; background:#fff; color:#24292f; border-radius:6px; cursor:pointer; }
        .frm-emails-table { width:100%; border-collapse: collapse; }
        .frm-emails-table th, .frm-emails-table td { border-bottom:1px solid #eaeef2; padding:8px 10px; vertical-align: top; }
        .frm-emails-table th { text-align:left; background:#f6f8fa; }
        .frm-emails-table .view-btn, .frm-emails-table .open-link-btn {
            padding:6px 10px; border:1px solid #1f6feb; background:#1f6feb; color:#fff; border-radius:6px;
            cursor:pointer; text-decoration:none; display:inline-block;
        }
        .frm-emails-pager { display:flex; gap:8px; align-items:center; justify-content:space-between; margin-top:12px; }
        .frm-emails-pager a, .frm-emails-pager span { padding:6px 10px; border:1px solid #d0d7de; border-radius:6px; text-decoration:none; color:#24292f; }
        .frm-emails-pager .active { background:#1f6feb; color:#fff; border-color:#1f6feb; }
        .frm-emails-pager .disabled { opacity:.45; pointer-events:none; }
    </style>

    <div class="frm-emails-wrap">
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

        <table class="frm-emails-table">
            <thead>
            <tr>
                <th><a href="<?php echo esc_url($sort_url); ?>">Entry ID <?php echo $order === 'ASC' ? '▲' : '▼'; ?></a></th>
                <th>Tracking Code</th>
                <th>Status</th>
                <th>Refund Status</th>
                <th>Created At</th>
                <th>Tracking URL</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" style="color:#57606a;">No results.</td></tr>
            <?php else: foreach ($rows as $r):
                $entryVal     = isset($r['entry_id']) ? (int)$r['entry_id'] : 0;
                $trackingCode = (string)($r['tracking_code'] ?? '');
                $statusVal    = (string)($r['status'] ?? '');
                $statusText   = $status_labels[$statusVal] ?? $statusVal;
                $refund       = (string)($r['refund_status'] ?? '');
                $createdRaw   = (string)($r['created_at'] ?? '');
                $createdFmt   = $createdRaw ? date_i18n('Y-m-d H:i', strtotime($createdRaw)) : '';
                $url          = trim((string)($r['tracking_url'] ?? ''));
            ?>
                <tr>
                    <td><?php echo $entryVal ? (int)$entryVal : ''; ?></td>
                    <td style="max-width:280px; word-break:break-word;"><?php echo esc_html($trackingCode); ?></td>
                    <td><?php echo esc_html($statusText); ?></td>
                    <td><?php echo esc_html($refund); ?></td>
                    <td><?php echo esc_html($createdFmt); ?></td>
                    <td>
                        <?php if ($url !== ''): ?>
                            <a class="open-link-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Open</a>
                        <?php else: ?>
                            <span style="color:#57606a;">—</span>
                        <?php endif; ?>
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
                <span style="color:#57606a;">Showing page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?> (<?php echo (int)$total; ?> total)</span>
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
