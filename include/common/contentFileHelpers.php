<?php
/**
 * contentFileHelpers.php
 * Locate content files (markdown, docx, images) for catalog items.
 */

function get_content_files($catalog_item) {
    $stages = (array)($catalog_item['stages'] ?? []);
    $dimensions = array_map('strtolower', (array)($catalog_item['dimensions'] ?? []));
    $title = $catalog_item['title'] ?? '';

    if (empty($stages) || empty($dimensions) || !$title) return [];

    $stage_num = $stages[0];
    $dim = $dimensions[0];

    $stage_names = [
        '00' => 'stage_00_the_seedbed', '01' => 'stage_01_the_spark',
        '02' => 'stage_02_the_activator', '03' => 'stage_03_the_producer',
        '04' => 'stage_04_the_connector', '05' => 'stage_05_the_sponsor',
        '06' => 'stage_06_the_team_builder', '07' => 'stage_07_the_culture_architect',
        '08' => 'stage_08_the_leader_developer', '09' => 'stage_09_the_visionary',
        '10' => 'stage_10_the_architect',
    ];
    $stage_folder = $stage_names[$stage_num] ?? null;
    if (!$stage_folder) return [];

    $base_path = realpath(__DIR__ . '/../../../dswa-framework/framework/content');
    if (!$base_path) return [];

    $content_dir = $base_path . '/' . $stage_folder . '/' . $dim;
    if (!is_dir($content_dir)) return [];

    $title_slug = str_replace([' ', "\u{2014}", "\u{2013}", ':', ',', "'", '"'], ['_', '', '', '', '', '', ''], $title);
    $url_base = '/elevateher/content/' . $stage_folder . '/' . $dim . '/';

    $files = [];
    $all_files = glob($content_dir . '/*');
    foreach ($all_files as $f) {
        if (is_dir($f)) continue;
        $fname = basename($f);
        // Skip hidden/meta files
        if ($fname[0] === '.' || $fname === 'dwsync.xml') continue;

        // Match by title slug prefix
        if (stripos($fname, substr($title_slug, 0, 15)) !== false) {
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $files[] = [
                'name' => $fname,
                'ext'  => $ext,
                'url'  => $url_base . rawurlencode($fname),
                'size' => filesize($f),
                'type' => get_file_type_label($ext),
            ];
        }
    }

    // Also check for catalog ID match if no title matches
    if (empty($files)) {
        $cat_id = $catalog_item['id'] ?? '';
        foreach ($all_files as $f) {
            if (is_dir($f)) continue;
            $fname = basename($f);
            if ($cat_id && stripos($fname, $cat_id) !== false) {
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                $files[] = [
                    'name' => $fname,
                    'ext'  => $ext,
                    'url'  => $url_base . rawurlencode($fname),
                    'size' => filesize($f),
                    'type' => get_file_type_label($ext),
                ];
            }
        }
    }

    return $files;
}

function get_file_type_label($ext) {
    $map = [
        'md'   => 'Markdown',
        'docx' => 'Word Document',
        'doc'  => 'Word Document',
        'pdf'  => 'PDF',
        'png'  => 'Image',
        'jpg'  => 'Image',
        'jpeg' => 'Image',
        'svg'  => 'Image',
        'mp4'  => 'Video',
        'mp3'  => 'Audio',
    ];
    return $map[$ext] ?? strtoupper($ext);
}

function format_file_size($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
