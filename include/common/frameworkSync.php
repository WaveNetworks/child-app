<?php
/**
 * frameworkSync.php
 * Functions to sync framework JSON data from local files or remote URL into the database.
 */

function sync_framework_data() {
    global $framework_config;

    $source = $framework_config['source'] ?? 'local';
    $result = ['changed' => false, 'version' => null, 'errors' => []];

    if ($source === 'local') {
        $path = $framework_config['local_path'] ?? '';
        if (!$path || !is_dir($path)) {
            $result['errors'][] = "Framework path not found: $path";
            return $result;
        }
        return sync_from_local($path);
    }

    $result['errors'][] = "Remote sync not yet implemented.";
    return $result;
}

function sync_from_local($base_path) {
    $result = ['changed' => false, 'version' => null, 'errors' => [], 'files_loaded' => 0];

    // Map of file patterns to file_type and key
    $file_map = [
        'v5.0_framework_header.json' => ['type' => 'header', 'key' => 'header'],
        'v5.0_tiers.json'            => ['type' => 'tiers', 'key' => 'tiers'],
        'v5.0_contentCatalog.json'   => ['type' => 'content_catalog', 'key' => 'content_catalog'],
        'v5.0_questionMatrix.json'   => ['type' => 'question_matrix', 'key' => 'question_matrix'],
        'v1.0_profileMatrix.json'    => ['type' => 'profile_matrix', 'key' => 'profile_matrix'],
        'v5.0_quotesBank.json'       => ['type' => 'quotes_bank', 'key' => 'quotes_bank'],
        'v5.0_contentRules.json'     => ['type' => 'content_rules', 'key' => 'content_rules'],
        'v5.0_contentSequence.json'  => ['type' => 'content_sequence', 'key' => 'content_sequence'],
    ];

    // Add stage files — pick highest version available (v5.1 > v5.0)
    for ($i = 0; $i <= 10; $i++) {
        // Glob for all versions of this stage file
        $stage_files = glob($base_path . sprintf("/v5.*_stage_%02d*.json", $i));
        if (empty($stage_files)) {
            $stage_files = glob($base_path . "/v5.*_stage_{$i}*.json");
        }
        if (!empty($stage_files)) {
            // Sort descending so highest version comes first
            rsort($stage_files);
            $sf = $stage_files[0]; // Use the highest version
            $fn = basename($sf);
            $key = sprintf('stage_%02d', $i);
            $file_map[$fn] = ['type' => 'stage', 'key' => $key];
        }
    }

    // Compute checksums for all files
    $file_checksums = [];
    $combined_hash = '';
    foreach ($file_map as $filename => $meta) {
        $filepath = $base_path . '/' . $filename;
        if (!file_exists($filepath)) continue;
        $content = file_get_contents($filepath);
        $checksum = hash('sha256', $content);
        $file_checksums[$filename] = $checksum;
        $combined_hash .= $checksum;
    }

    $overall_checksum = hash('sha256', $combined_hash);

    // Check if this version already loaded
    $existing = get_active_framework_version();
    if ($existing && $existing['checksum'] === $overall_checksum) {
        $result['version'] = $existing['version_label'];
        return $result; // No changes
    }

    // Deactivate current version
    child_db_query_prepared("UPDATE framework_versions SET is_active = 0 WHERE is_active = 1", []);

    // Create new version record
    $version_label = '5.0-' . date('Ymd-His');
    child_db_query_prepared(
        "INSERT INTO framework_versions (version_label, schema_version, source, checksum, file_checksums, is_active, loaded_by)
         VALUES (?, '5.0', 'local', ?, ?, 1, ?)",
        [$version_label, $overall_checksum, json_encode($file_checksums), $_SESSION['user_id'] ?? 0]
    );
    $version_id = child_db_insert_id();

    // Load each file
    foreach ($file_map as $filename => $meta) {
        $filepath = $base_path . '/' . $filename;
        if (!file_exists($filepath)) continue;

        $content = file_get_contents($filepath);
        $checksum = $file_checksums[$filename] ?? hash('sha256', $content);

        child_db_query_prepared(
            "INSERT INTO framework_data (version_id, file_type, file_key, data_json, checksum) VALUES (?, ?, ?, ?, ?)",
            [$version_id, $meta['type'], $meta['key'], $content, $checksum]
        );
        $result['files_loaded']++;
    }

    $result['changed'] = true;
    $result['version'] = $version_label;
    return $result;
}

function rollback_framework($target_version_id) {
    child_db_query_prepared("UPDATE framework_versions SET is_active = 0 WHERE is_active = 1", []);
    child_db_query_prepared("UPDATE framework_versions SET is_active = 1 WHERE id = ?", [$target_version_id]);
}

function get_framework_versions() {
    $stmt = child_db_query_prepared(
        "SELECT * FROM framework_versions ORDER BY loaded_at DESC", []
    );
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
