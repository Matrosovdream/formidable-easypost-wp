<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * FrmEntryStatusRuleEngine
 *
 * Declarative rules for updating a Formidable "status" field based on other field metas.
 *
 * Supported operators:
 *  - equals, not_equals
 *  - contains, not_contains     (array: in_array; string: substring)
 *  - in, not_in                 (expected is an array list)
 *  - exists, not_exists
 *
 * Grouping:
 *  - Top level uses 'all' (AND) for each mapping
 *  - Nested groups: ['any' => [ ... ]]  // OR
 *                   ['and' => [ ... ]]  // AND
 *
 * Optional mapping keys:
 *  - 'form_id'        => (int) only evaluate rule if entry->form_id matches
 *  - 'when_status_is' => array<string> precondition: current status must match ANY of these (OR)
 *
 * Mapping schema example:
 * [
 *   'status_field'   => 7,                 // REQUIRED: Field ID to set
 *   'set_status_to'  => 'Verified',        // REQUIRED: Value to set
 *   'form_id'        => 1,                 // OPTIONAL
 *   'when_status_is' => ['Processing-X','Pending Review'], // OPTIONAL (array => OR)
 *   'all' => [                             // REQUIRED: all clauses must pass
 *     ['field' => 273, 'op' => 'contains',     'value' => 'verified',     'field_type' => 'array'],
 *     ['field' => 273, 'op' => 'not_contains', 'value' => 'missing-info', 'field_type' => 'array'],
 *     ['any' => [
 *        ['field' => 670, 'op' => 'contains', 'value' => 'photo-done', 'field_type' => 'array'],
 *        ['field' => 328, 'op' => 'contains', 'value' => 'photo-no',   'field_type' => 'array'],
 *     ]],
 *     ['field' => 70, 'op' => 'not_equals', 'value' => 'Provide Later'],
 *   ],
 * ]
 */
final class FrmEntryStatusRuleEngine
{
    /** @var FrmEasypostEntryHelper */
    private $helper;

    public function __construct($helper = null)
    {
        $this->helper = $helper ?: new FrmEasypostEntryHelper();
    }

    /**
     * Apply all mappings to a single entry.
     */
    public function applyMappings(int $entry_id, array $mappings): void
    {
        if (!$entry_id) { return; }

        $entry = \FrmEntry::getOne($entry_id, true);
        if (!$entry) { return; }

        $formId = isset($entry->form_id) ? (int)$entry->form_id : 0;

        $metas = $this->helper->getEntryMetas($entry_id);
        if (!is_array($metas)) { $metas = []; }

        foreach ($mappings as $map) {
            $statusField   = (int)($map['status_field'] ?? 0);
            $setStatusTo   = $map['set_status_to'] ?? null;
            $whenStatusIs  = $map['when_status_is'] ?? null; // array|null
            $allConditions = $map['all'] ?? [];
            $requireFormId = isset($map['form_id']) ? (int)$map['form_id'] : null;

            if (!$statusField || $setStatusTo === null || !is_array($allConditions)) {
                continue; // invalid rule
            }

            if ($requireFormId !== null && $requireFormId !== $formId) {
                continue;
            }

            $currentStatusRaw = $metas[$statusField] ?? '';

            // Precondition: if provided, must be an array; OR matching
            if (!$this->statusPreconditionMatches($currentStatusRaw, $whenStatusIs)) {
                continue;
            }

            if ($this->evaluateAll($allConditions, $metas)) {
                $this->upsertMeta($entry_id, $statusField, $setStatusTo);
            }
        }
    }

    /* =======================
       Precondition helper (array OR)
       ======================= */

    /**
     * If whenStatusIs is null (not set) => pass.
     * If it's an array => pass if ANY current status equals any element.
     * Empty array => fail (no allowed statuses).
     *
     * @param mixed             $currentStatusRaw Scalar or array from meta
     * @param array<string>|null $whenStatusIs
     */
    private function statusPreconditionMatches($currentStatusRaw, $whenStatusIs): bool
    {
        if ($whenStatusIs === null) {
            return true; // no precondition
        }

        // Force to array of strings
        $needles = array_values(array_filter(array_map('strval', (array)$whenStatusIs), static function($v) {
            return $v !== '';
        }));

        if (empty($needles)) {
            return false; // explicit but empty => never matches
        }

        // Normalize current status into array of strings
        $currentValues = is_array($currentStatusRaw)
            ? array_map('strval', $currentStatusRaw)
            : [(string)$currentStatusRaw];

        return count(array_intersect($currentValues, $needles)) > 0;
    }

    /* =======================
       Group evaluators
       ======================= */

    /** Evaluate an AND list. Every clause must pass. */
    private function evaluateAll(array $clauses, array $metas): bool
    {
        foreach ($clauses as $clause) {
            if (isset($clause['any'])) {
                if (!$this->evaluateAny((array)$clause['any'], $metas)) {
                    return false;
                }
                continue;
            }
            if (isset($clause['and'])) {
                if (!$this->evaluateAll((array)$clause['and'], $metas)) {
                    return false;
                }
                continue;
            }
            if (!$this->evaluateCondition($clause, $metas)) {
                return false;
            }
        }
        return true;
    }

    /** Evaluate an OR list. At least one condition must pass. */
    private function evaluateAny(array $conditions, array $metas): bool
    {
        foreach ($conditions as $cond) {
            if (isset($cond['any'])) {
                if ($this->evaluateAny((array)$cond['any'], $metas)) {
                    return true;
                }
                continue;
            }
            if (isset($cond['and'])) {
                if ($this->evaluateAll((array)$cond['and'], $metas)) {
                    return true;
                }
                continue;
            }
            if ($this->evaluateCondition($cond, $metas)) {
                return true;
            }
        }
        return false;
    }

    /* =======================
       Leaf evaluator
       ======================= */

    private function evaluateCondition(array $cond, array $metas): bool
    {
        $fieldId   = $cond['field'] ?? null;
        $op        = $cond['op']    ?? 'equals';
        $expected  = $cond['value'] ?? null;
        $fieldType = $cond['field_type'] ?? null;

        if ($fieldId === null) {
            return false;
        }

        $raw = $metas[$fieldId] ?? null;

        if ($fieldType === 'array') {
            $value = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [(string)$raw]);
        } elseif ($fieldType === 'string') {
            $value = $this->asString($raw);
        } else {
            $value = is_array($raw) ? $raw : $this->asString($raw);
        }

        switch ($op) {
            case 'equals':
                return !is_array($value) && ((string)$value === (string)$expected);

            case 'not_equals':
                return !is_array($value) && ((string)$value !== (string)$expected);

            case 'contains':
                if (is_array($value)) {
                    return in_array($expected, $value, true);
                }
                return ($expected !== null && $expected !== '' && $value !== '')
                    ? (mb_strpos((string)$value, (string)$expected) !== false)
                    : false;

            case 'not_contains':
                if (is_array($value)) {
                    return !in_array($expected, $value, true);
                }
                if ($expected === null || $expected === '') {
                    return true;
                }
                return $value === '' || mb_strpos((string)$value, (string)$expected) === false;

            case 'in': {
                $list = is_array($expected) ? $expected : [$expected];
                if (is_array($value)) {
                    return count(array_intersect($value, $list)) > 0;
                }
                return in_array((string)$value, array_map('strval', $list), true);
            }

            case 'not_in': {
                $list = is_array($expected) ? $expected : [$expected];
                if (is_array($value)) {
                    return count(array_intersect($value, $list)) === 0;
                }
                return !in_array((string)$value, array_map('strval', $list), true);
            }

            case 'exists':
                return is_array($value) ? !empty($value) : ($value !== null && $value !== '');

            case 'not_exists':
                return is_array($value) ? empty($value) : ($value === null || $value === '');

            default:
                return false;
        }
    }

    /* =======================
       Meta helpers
       ======================= */

    private function upsertMeta(int $entry_id, int $field_id, $value): void
    {
        $updated = \FrmEntryMeta::update_entry_meta($entry_id, $field_id, '', $value);

        if (!$updated) {
            if (method_exists('\FrmEntryMeta', 'delete_entry_meta')) {
                \FrmEntryMeta::delete_entry_meta($entry_id, $field_id);
            }
            \FrmEntryMeta::add_entry_meta($entry_id, $field_id, '', $value);
        }
    }

    private function asString($v): string
    {
        if ($v === null) { return ''; }
        if (is_array($v)) { return implode(',', array_map('strval', $v)); }
        return (string)$v;
    }
}
