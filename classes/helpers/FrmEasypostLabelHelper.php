<?php

if ( ! defined('ABSPATH') ) { exit; }


/**
 * Helper: builds an indexed query for wp_frm_items + wp_frm_item_metas
 * and returns FrmEntry objects, with pagination.
 */
class FrmEasypostLabelHelper {

    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    public function getMassUpdateEntries( array $args ): array {

        $defaults = [
            'form_id'        => null,
            'page'           => 1,
            'per_page'       => 20, // default 20
            'include_drafts' => false,

            'status' => [
                'field_id' => 7,
                'value'    => 'Verified',
            ],
            'exclude_flag' => [
                'field_id'  => 273,
                'contains'  => 'label-printed',
            ],
            'photo' => [
                'mode'             => 'without', // 'without' OR 'with'
                'field_id_without' => 328,
                'value_without'    => 'photo-no',
                'field_id_with'    => 670,
                'value_with'       => 'photo-done',
            ],
            'service' => [
                'field_id' => 12,
                'values'   => [], // array of strings
            ],

            // Processing time filter (field 211)
            // Values:
            // 145 = Standard, 175 = Expedited, 375 = Rush
            'processing_time' => [
                'field_id' => 211,
                'value'    => 0, // 0 = All, 145/175/375 filter
            ],

            'order_by'  => 'i.id',
            'order_dir' => 'ASC',
        ];

        $args = array_replace_recursive($defaults, $args);

        $page     = max(1, (int) $args['page']);
        $per_page = max(1, min(500, (int) $args['per_page']));
        $offset   = ($page - 1) * $per_page;

        $form_id = isset($args['form_id']) && $args['form_id'] !== null ? (int) $args['form_id'] : null;

        $status_field_id = (int) ($args['status']['field_id'] ?? 7);
        $status_value    = (string) ($args['status']['value'] ?? 'Verified');

        $exclude_field_id = (int) ($args['exclude_flag']['field_id'] ?? 273);
        $exclude_contains = (string) ($args['exclude_flag']['contains'] ?? 'label-printed');

        $photo_mode = (string) ($args['photo']['mode'] ?? 'without');
        $photo_mode = ($photo_mode === 'with') ? 'with' : 'without';

        $photo_field_id = ($photo_mode === 'with')
            ? (int) ($args['photo']['field_id_with'] ?? 670)
            : (int) ($args['photo']['field_id_without'] ?? 328);

        $photo_value = ($photo_mode === 'with')
            ? (string) ($args['photo']['value_with'] ?? 'photo-done')
            : (string) ($args['photo']['value_without'] ?? 'photo-no');

        $service_field_id = (int) ($args['service']['field_id'] ?? 12);
        $service_values   = (array) ($args['service']['values'] ?? []);
        $service_values   = array_values(array_filter(array_map('strval', $service_values), fn($v) => $v !== ''));

        // Processing time
        $pt_field_id = (int) ($args['processing_time']['field_id'] ?? 211);
        $pt_value    = (int) ($args['processing_time']['value'] ?? 0);

        // If no services passed, safe empty result.
        if (empty($service_values)) {
            return [
                'items' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'total_pages' => 0,
                    'offset' => $offset,
                ],
            ];
        }

        $order_by = (string) ($args['order_by'] ?? 'i.id');
        $allowed_order_by = ['i.id', 'i.created_at', 'i.updated_at'];
        if (!in_array($order_by, $allowed_order_by, true)) {
            $order_by = 'i.id';
        }
        $order_dir = strtoupper((string) ($args['order_dir'] ?? 'ASC'));
        $order_dir = ($order_dir === 'DESC') ? 'DESC' : 'ASC';

        $items_table = $this->db->prefix . 'frm_items';
        $metas_table = $this->db->prefix . 'frm_item_metas';

        $where_parts = [];
        $params = [];

        // Draft filter
        if (empty($args['include_drafts'])) {
            $where_parts[] = "i.is_draft = 0";
        }

        // Optional form_id (kept for compatibility)
        if ($form_id) {
            $where_parts[] = "i.form_id = %d";
            $params[] = $form_id;
        }

        // status (#7)
        $where_parts[] = "EXISTS (
            SELECT 1 FROM {$metas_table} ms
            WHERE ms.item_id = i.id
              AND ms.field_id = %d
              AND ms.meta_value = %s
        )";
        $params[] = $status_field_id;
        $params[] = $status_value;

        // photo (#328 or #670)
        $where_parts[] = "EXISTS (
            SELECT 1 FROM {$metas_table} mp
            WHERE mp.item_id = i.id
              AND mp.field_id = %d
              AND mp.meta_value LIKE %s
        )";
        $params[] = $photo_field_id;
        $params[] = '%' . $photo_value. '%';

        // service (#12 IN (...))
        $in_placeholders = implode(',', array_fill(0, count($service_values), '%s'));
        $where_parts[] = "EXISTS (
            SELECT 1 FROM {$metas_table} msvc
            WHERE msvc.item_id = i.id
              AND msvc.field_id = %d
              AND msvc.meta_value IN ($in_placeholders)
        )";
        $params[] = $service_field_id;
        foreach ($service_values as $v) {
            $params[] = $v;
        }

        // Processing time (#211) optional filter (145/175/375)
        if ($pt_value !== 0) {
            $where_parts[] = "EXISTS (
                SELECT 1 FROM {$metas_table} mpt
                WHERE mpt.item_id = i.id
                  AND mpt.field_id = %d
                  AND mpt.meta_value = %s
            )";
            $params[] = $pt_field_id;
            $params[] = (string) $pt_value; // meta_value is stored as text
        }

        // exclude flag (#273 NOT contains label-printed) — handles missing row correctly
        $where_parts[] = "NOT EXISTS (
            SELECT 1 FROM {$metas_table} mx
            WHERE mx.item_id = i.id
              AND mx.field_id = %d
              AND mx.meta_value LIKE %s
        )";
        $params[] = $exclude_field_id;
        $params[] = '%' . $this->db->esc_like($exclude_contains) . '%';

        $where_sql = implode("\n  AND ", $where_parts);

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$items_table} i WHERE {$where_sql}";
        $count_sql = $this->db->prepare($count_sql, $params);
        $total = (int) $this->db->get_var($count_sql);

        $total_pages = ($total > 0) ? (int) ceil($total / $per_page) : 0;

        // List
        $list_sql = "
            SELECT i.id, i.form_id, i.created_at, i.updated_at
            FROM {$items_table} i
            WHERE {$where_sql}
            ORDER BY {$order_by} {$order_dir}
            LIMIT %d OFFSET %d
        ";

        $list_params = array_merge($params, [$per_page, $offset]);
        $list_sql = $this->db->prepare($list_sql, $list_params);

        //echo $list_sql;

        $rows = $this->db->get_results($list_sql, ARRAY_A);

        $items = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) { continue; }
            $items[] = FrmEntry::getOne($id, true);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'total_pages'  => $total_pages,
                'offset'       => $offset,
            ],
        ];
    }
}
