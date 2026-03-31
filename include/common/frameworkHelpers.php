<?php
/**
 * frameworkHelpers.php
 * Helper functions for accessing framework data from the database.
 */

function get_active_framework_version() {
    $stmt = child_db_query_prepared(
        "SELECT * FROM framework_versions WHERE is_active = 1 ORDER BY loaded_at DESC LIMIT 1", []
    );
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

function get_framework_data($file_type, $file_key = null) {
    $version = get_active_framework_version();
    if (!$version) return null;

    if ($file_key) {
        $stmt = child_db_query_prepared(
            "SELECT data_json FROM framework_data WHERE version_id = ? AND file_type = ? AND file_key = ? LIMIT 1",
            [$version['id'], $file_type, $file_key]
        );
    } else {
        $stmt = child_db_query_prepared(
            "SELECT data_json FROM framework_data WHERE version_id = ? AND file_type = ? LIMIT 1",
            [$version['id'], $file_type]
        );
    }
    if (!$stmt) return null;
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['data_json'], true) : null;
}

function get_all_stages() {
    $stages = [];
    for ($i = 0; $i <= 10; $i++) {
        $key = sprintf('stage_%02d', $i);
        $data = get_framework_data('stage', $key);
        if ($data) $stages[$key] = $data;
    }
    return $stages;
}

function get_stage($stage_num) {
    $key = sprintf('stage_%02d', (int)$stage_num);
    return get_framework_data('stage', $key);
}

function get_content_catalog() {
    return get_framework_data('content_catalog') ?: [];
}

function get_question_matrix() {
    return get_framework_data('question_matrix') ?: [];
}

function get_quotes_bank() {
    return get_framework_data('quotes_bank') ?: [];
}

// get_content_rules() and get_content_sequence() are in contentHelpers.php
// with filesystem fallback to the framework JSON files

function get_framework_header() {
    return get_framework_data('header') ?: [];
}

function get_tiers() {
    return get_framework_data('tiers') ?: [];
}

function get_hard_walls() {
    $header = get_framework_header();
    return $header['hardWalls'] ?? [];
}

/**
 * Find which stage and dimension a diagnostic item belongs to.
 * Scans all stages to locate the item by ID.
 */
function find_item_stage_dimension($item_id) {
    static $cache = [];
    if (isset($cache[$item_id])) return $cache[$item_id];

    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        $stage_id = sprintf('%02d', $i);

        // Stage files use mindset/toolset/skillset arrays (not diagnosticItems)
        foreach (['mindset', 'toolset', 'skillset'] as $dim) {
            foreach ($stage[$dim] ?? [] as $item) {
                $id = $item['id'] ?? '';
                if ($id === $item_id) {
                    $result = ['stage' => $stage_id, 'dimension' => $dim];
                    $cache[$item_id] = $result;
                    return $result;
                }
            }
        }

        // Fallback: check diagnosticItems key (old format from frameworkSync)
        foreach ($stage['diagnosticItems'] ?? [] as $item) {
            $id = $item['id'] ?? '';
            if ($id === $item_id) {
                $dim = $item['dimension'] ?? 'mindset';
                $result = ['stage' => $stage_id, 'dimension' => $dim];
                $cache[$item_id] = $result;
                return $result;
            }
        }
    }
    $cache[$item_id] = null;
    return null;
}

/**
 * Look up KPI metadata (label, levels) by KPI ID.
 * Scans all stages for a matching KPI definition.
 */
function find_kpi_meta($kpi_id) {
    static $cache = [];
    if (isset($cache[$kpi_id])) return $cache[$kpi_id];

    for ($i = 0; $i <= 10; $i++) {
        $stage = get_stage($i);
        if (!$stage) continue;
        foreach ($stage['kpis'] ?? [] as $kpi) {
            if (($kpi['id'] ?? '') === $kpi_id) {
                $cache[$kpi_id] = $kpi;
                return $kpi;
            }
        }
    }
    $cache[$kpi_id] = ['id' => $kpi_id, 'label' => $kpi_id];
    return $cache[$kpi_id];
}

function get_contextual_quote($stage = null, $dimension = null) {
    $quotes = get_quotes_bank();
    if (empty($quotes)) return null;

    // Handle both array and object formats
    $items = isset($quotes['quotes']) ? $quotes['quotes'] : $quotes;
    if (!is_array($items) || empty($items)) return null;

    $matches = [];
    foreach ($items as $q) {
        $stageMatch = true;
        $dimMatch = true;
        if ($stage !== null && !empty($q['stages'])) {
            $stageMatch = in_array($stage, (array)$q['stages']);
        }
        if ($dimension !== null && !empty($q['dimensions'])) {
            $dimMatch = in_array($dimension, (array)$q['dimensions']);
        }
        if ($stageMatch && $dimMatch) {
            $matches[] = $q;
        }
    }

    if (empty($matches)) {
        // Fallback: random from all quotes
        return $items[array_rand($items)];
    }
    return $matches[array_rand($matches)];
}
