<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * -----------------------------------------------------------------------------
 * Robust pre-update timestamp capture for Formidable entries
 * -----------------------------------------------------------------------------
 *
 * - Captures existing created_at / updated_at BEFORE Formidable updates them
 * - Stores in a static in-request cache for later retrieval by rule engine
 * - Hooks both filter (frm_update_entry) and action (frm_before_update_entry)
 * - Optional debug logging: define('FRM_STATUS_ENGINE_DEBUG', true);
 */
final class FrmEntryPrevTimestampCapture
{
    /** @var array<int,array{created_at:?string,updated_at:?string}> */
    private static $cache = [];

    public static function init(): void
    {
        // EARLIEST: filter fires before update, gives us $entry_id and $values
        add_filter('frm_update_entry', [__CLASS__, 'capture_via_filter'], 0, 2);

        // Also capture on the action just in case some paths skip the filter
        add_action('frm_before_update_entry', [__CLASS__, 'capture_via_action'], 0, 2);

        // Optional cleanup after update (not strictly required, but tidy)
        //add_action('frm_after_update_entry', [__CLASS__, 'cleanup_after_update'], 99, 2);

    }

    /**
     * Filter runs before update; MUST return $values unchanged.
     *
     * @param array $values
     * @param int   $entry_id
     * @return array
     */
    public static function capture_via_filter(array $values, int $entry_id): array
    {
        self::maybe_capture($entry_id, 'filter');
        return $values;
    }

    /**
     * Action runs before update.
     *
     * @param int   $entry_id
     * @param array $values
     */
    public static function capture_via_action(int $entry_id, array $values): void
    {
        self::maybe_capture($entry_id, 'action');
    }

    /**
     * Store the current timestamps once per request.
     *
     * @param int    $entry_id
     * @param string $source  'filter'|'action'
     */
    private static function maybe_capture(int $entry_id, string $source): void
    {
        if (!$entry_id) { return; }
        if (isset(self::$cache[$entry_id])) {
            self::debug("skip capture; already cached for #$entry_id (via $source)");
            return;
        }

        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) {
            self::debug("capture failed; entry not found for #$entry_id (via $source)");
            return;
        }

        $created = isset($entry->created_at) ? (string)$entry->created_at : null;
        $updated = isset($entry->updated_at) ? (string)$entry->updated_at : null;

        self::$cache[$entry_id] = [
            'created_at' => $created,
            'updated_at' => $updated,
        ];

        self::debug("captured timestamps for #$entry_id (via $source): created_at={$created} updated_at={$updated}");
    }

    /**
     * Optional: clear cache for this entry after update completes.
     */
    public static function cleanup_after_update(int $entry_id, array $values): void
    {
        if (isset(self::$cache[$entry_id])) {
            unset(self::$cache[$entry_id]);
            self::debug("cleanup cache for #$entry_id");
        }
    }

    /**
     * Retrieve previously captured timestamp string if available.
     *
     * @param int    $entry_id
     * @param string $field 'created_at'|'updated_at'
     * @return string|null
     */
    public static function get_captured(int $entry_id, string $field): ?string
    {
        if (!in_array($field, ['created_at', 'updated_at'], true)) {
            return null;
        }
        return self::$cache[$entry_id][$field] ?? null;
    }

    private static function debug(string $msg): void
    {
        if (defined('FRM_STATUS_ENGINE_DEBUG') && FRM_STATUS_ENGINE_DEBUG) {
            error_log('[FrmEntryPrevTimestampCapture] ' . $msg);
        }
    }
}

// Bootstrap early (plugins_loaded with low priority ensures it’s ready before most saves)
add_action('plugins_loaded', static function () {
    FrmEntryPrevTimestampCapture::init();
}, 1);



/**
 * -----------------------------------------------------------------------------
 * Rule engine (simplified "older than N days" + captured timestamps)
 * -----------------------------------------------------------------------------
 */
final class FrmEntryStatusRuleEngine
{
    /** @var FrmEasypostEntryHelper */
    private $helper;

    public function __construct($helper = null)
    {
        $this->helper = $helper ?: new FrmEasypostEntryHelper();
    }

    public function applyMappings(int $entry_id, array $mappings): void
    {
        if (!$entry_id) { return; }

        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) { return; }

        $formId = (int) ($entry->form_id ?? 0);
        $metas  = $this->helper->getEntryMetas($entry_id);
        if (!is_array($metas)) { $metas = []; }

        foreach ($mappings as $map) {
            $statusField   = (int)($map['status_field'] ?? 0);
            $setStatusTo   = $map['set_status_to'] ?? null;
            $whenStatusIs  = $map['when_status_is'] ?? null;
            $allConditions = $map['all'] ?? [];
            $requireFormId = isset($map['form_id']) ? (int)$map['form_id'] : null;
            $lastChange    = (isset($map['last_change']) && is_array($map['last_change'])) ? $map['last_change'] : null;

            if (!$statusField || $setStatusTo === null || !is_array($allConditions)) {
                continue;
            }
            if ($requireFormId !== null && $requireFormId !== $formId) {
                continue;
            }

            $currentStatusRaw = $metas[$statusField] ?? '';

            // Precondition: current status is in the allowed list (OR)
            if (!$this->statusPreconditionMatches($currentStatusRaw, $whenStatusIs)) {
                continue;
            }

            // last_change: only “older than N days”
            if ($lastChange !== null && !$this->isOlderThan($entry, $entry_id, $lastChange)) {
                continue;
            }

            if ($this->evaluateAll($allConditions, $metas)) {
                $this->upsertMeta($entry_id, $statusField, $setStatusTo);
            }
        }
    }

    private function statusPreconditionMatches($currentStatusRaw, $whenStatusIs): bool
    {
        if ($whenStatusIs === null) return true;

        $needles = array_values(array_filter(array_map('strval', (array)$whenStatusIs), static function($v) {
            return $v !== '';
        }));
        if (empty($needles)) return false;

        $current = is_array($currentStatusRaw) ? array_map('strval', $currentStatusRaw) : [(string)$currentStatusRaw];
        return count(array_intersect($current, $needles)) > 0;
    }

    /**
     * last_change simplified: true if (created_at|updated_at) is older than N days
     * Config: ['value' => <int days>, 'field' => 'updated_at'|'created_at']
     */
    private function isOlderThan($entry, int $entry_id, array $cfg): bool
    {
        $days  = isset($cfg['value']) ? (int)$cfg['value'] : 0;
        $field = isset($cfg['field']) ? (string)$cfg['field'] : 'updated_at';

        if ($days <= 0) return true;
        if (!in_array($field, ['updated_at', 'created_at'], true)) return false;

        $ts = $this->getEntryTimestamp($entry, $entry_id, $field);
        if (!$ts) return false;

        $nowTs   = (int) current_time('timestamp');
        $elapsed = (int) floor(($nowTs - $ts) / DAY_IN_SECONDS);

        $ok = ($elapsed > $days);

        if (defined('FRM_STATUS_ENGINE_DEBUG') && FRM_STATUS_ENGINE_DEBUG) {
            error_log("[FrmEntryStatusRuleEngine] entry #$entry_id {$field} ts=$ts elapsed_days=$elapsed threshold=$days match=" . ($ok ? 'YES' : 'NO'));
        }

        return $ok;
    }

    /**
     * Priority order:
     *  1) captured pre-update timestamp from FrmEntryPrevTimestampCapture
     *  2) current entry object's property
     */
    private function getEntryTimestamp($entry, int $entry_id, string $field): int
    {
        $raw = FrmEntryPrevTimestampCapture::get_captured($entry_id, $field);
        if ($raw === null || $raw === '') {
            $raw = isset($entry->$field) ? (string)$entry->$field : '';
        }
        return $raw ? (int) strtotime($raw) : 0;
    }

    /* ---------------- Condition evaluators ---------------- */

    private function evaluateAll(array $clauses, array $metas): bool
    {
        foreach ($clauses as $c) {
            if (isset($c['any'])) {
                if (!$this->evaluateAny((array)$c['any'], $metas)) return false;
                continue;
            }
            if (isset($c['and'])) {
                if (!$this->evaluateAll((array)$c['and'], $metas)) return false;
                continue;
            }
            if (!$this->evaluateCondition($c, $metas)) return false;
        }
        return true;
    }

    private function evaluateAny(array $conds, array $metas): bool
    {
        foreach ($conds as $c) {
            if (isset($c['any']) && $this->evaluateAny((array)$c['any'], $metas)) return true;
            if (isset($c['and']) && $this->evaluateAll((array)$c['and'], $metas)) return true;
            if ($this->evaluateCondition($c, $metas)) return true;
        }
        return false;
    }

    private function evaluateCondition(array $c, array $metas): bool
    {
        $fid  = $c['field'] ?? null;
        $op   = $c['op'] ?? 'equals';
        $val  = $c['value'] ?? null;
        $type = $c['field_type'] ?? null;

        if ($fid === null) return false;

        $raw   = $metas[$fid] ?? null;
        $value = ($type === 'array')
            ? (is_array($raw) ? $raw : (($raw === null || $raw === '') ? [] : [(string)$raw]))
            : (is_array($raw) ? implode(',', array_map('strval', $raw)) : (string)$raw);

        switch ($op) {
            case 'equals':       return !is_array($value) && ((string)$value === (string)$val);
            case 'not_equals':   return !is_array($value) && ((string)$value !== (string)$val);
            case 'contains':     return is_array($value) ? in_array($val, $value, true) : (mb_strpos((string)$value, (string)$val) !== false);
            case 'not_contains': return is_array($value) ? !in_array($val, $value, true) : (mb_strpos((string)$value, (string)$val) === false);
            case 'in': {
                $list = is_array($val) ? $val : [$val];
                return is_array($value) ? (count(array_intersect($value, $list)) > 0) : in_array((string)$value, array_map('strval', $list), true);
            }
            case 'not_in': {
                $list = is_array($val) ? $val : [$val];
                return is_array($value) ? (count(array_intersect($value, $list)) === 0) : !in_array((string)$value, array_map('strval', $list), true);
            }
            case 'exists':       return is_array($value) ? !empty($value) : ($value !== null && $value !== '');
            case 'not_exists':   return is_array($value) ? empty($value) : ($value === null || $value === '');
        }
        return false;
    }

    private function upsertMeta(int $entry_id, int $field_id, $value): void
    {
        $ok = \FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $value);
        if (!$ok) {
            if (method_exists('\FrmEntryMeta', 'delete_entry_meta')) {
                \FrmEntryMeta::delete_entry_meta($entry_id, $field_id);
            }
            \FrmEntryMeta::add_entry_meta($entry_id, $field_id, '', $value);
        }
    }
}










final class FrmStatusBatchRunner
{
    /**
     * Collect entries that match a single mapping.
     *
     * @param array $mapping  The mapping config (see usage above)
     * @param int   $limit    Optional limit (0 = no limit)
     * @param int   $offset   Optional offset (ignored if $limit=0)
     * @return array<int,array{ id:int, form_id:int, created_at:string, updated_at:string, status:string|null }>
     */
    public function collectEntriesByRule(array $mapping, int $limit = 0, int $offset = 0): array
    {
        global $wpdb;

        $items_tbl = $wpdb->prefix . 'frm_items';
        $meta_tbl  = $wpdb->prefix . 'frm_item_metas';

        $formId        = isset($mapping['form_id']) ? (int)$mapping['form_id'] : null;
        $statusFieldId = (int)($mapping['status_field'] ?? 0);
        $statuses      = (array)($mapping['when_status_is'] ?? []);
        $lastChange    = isset($mapping['last_change']) ? (array)$mapping['last_change'] : [];
        $lastDays      = isset($lastChange['value']) ? max(0, (int)$lastChange['value']) : 0;
        $lastField     = isset($lastChange['field']) && in_array($lastChange['field'], ['updated_at','created_at'], true)
            ? $lastChange['field']
            : 'updated_at';

        if ($statusFieldId <= 0 || empty($statuses)) {
            return []; // nothing to collect
        }

        // Build WHERE parts
        $where  = [];
        $params = [];

        // Optional form restriction
        if (!empty($formId)) {
            $where[]  = "i.form_id = %d";
            $params[] = $formId;
        }

        // Join on status meta (exact match against IN (...))
        // Note: if your status meta can be serialized arrays, adjust to LIKE or FIND_IN_SET as needed.
        $inPlaceholders = implode(',', array_fill(0, count($statuses), '%s'));
        $where[]        = "m.field_id = %d AND m.meta_value IN ($inPlaceholders)";
        array_unshift($statuses, $statusFieldId); // first param is field_id, then all statuses
        $params = array_merge($params, $statuses);

        // "Older than N days" constraint on created_at or updated_at
        // Use WP local "now" so it respects site timezone.
        if ($lastDays > 0) {
            $nowMysql   = current_time('mysql'); // Y-m-d H:i:s (local)
            $where[]    = "DATEDIFF(%s, i.`$lastField`) > %d";
            $params[]   = $nowMysql;
            $params[]   = $lastDays;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT i.id, i.form_id, i.created_at, i.updated_at, m.meta_value AS status
            FROM $items_tbl i
            INNER JOIN $meta_tbl m
                ON m.item_id = i.id
            $whereSql
            ORDER BY i.id ASC
        ";

        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, max(0, $offset));
        }

        // Prepare with our assembled params (note: we already added IN list placeholders above)
        // First build the base prepare with the $where params, then apply LIMIT/OFFSET if present.
        // We need to re-build the query with placeholders filled in; simpler is to rebuild $params in the same order we used.
        $preparedSql = $wpdb->prepare($sql, $params);

        $rows = $wpdb->get_results($preparedSql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        // Normalize types
        foreach ($rows as &$r) {
            $r['id']        = (int)$r['id'];
            $r['form_id']   = (int)$r['form_id'];
            $r['status']    = isset($r['status']) ? (string)$r['status'] : null;
            $r['created_at']= (string)$r['created_at'];
            $r['updated_at']= (string)$r['updated_at'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Print entries with print_r (for your manual inspection).
     * This uses echo so you can see it in CLI/WP-Admin.
     *
     * @param array $entries
     * @return void
     */
    public function printEntries(array $entries): void
    {
        echo "<pre>";
        print_r($entries);
        echo "</pre>";
    }

    /**
     * Process a previously collected list of entries by applying a single mapping to each.
     * Uses your rule engine’s applyMappings() method.
     *
     * @param FrmEntryStatusRuleEngine $engine
     * @param array                    $mapping  Single mapping
     * @param array|null               $entries  If null, it will collect first.
     * @return void
     */
    public function processEntriesByRule(FrmEntryStatusRuleEngine $engine, array $mapping, ?array $entries = null): void
    {
        if ($entries === null) {
            $entries = $this->collectEntriesByRule($mapping);
        }

        // Optional: print first, exactly as you asked.
        $this->printEntries($entries);

        foreach ($entries as $row) {
            $entry_id = (int)$row['id'];
            // Wrap mapping into an array because applyMappings expects a list of mappings.
            $engine->applyMappings($entry_id, [$mapping]);
        }
    }
}