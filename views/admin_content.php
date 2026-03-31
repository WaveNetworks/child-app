<?php
/**
 * Admin Content Catalog — Browse and filter the content library
 */
$page_title = 'Content Catalog';

$catalog_raw = get_content_catalog();
$catalog = $catalog_raw['contentCatalog'] ?? $catalog_raw;
if (!is_array($catalog)) $catalog = [];

$stages = get_all_stages();

// Collect filter options
$all_types = [];
$all_statuses = [];
$all_stages_used = [];
$all_dimensions = [];
foreach ($catalog as $item) {
    $all_types[$item['type'] ?? 'unknown'] = true;
    $all_statuses[$item['status'] ?? 'unknown'] = true;
    foreach ($item['stages'] ?? [] as $s) $all_stages_used[$s] = true;
    foreach ($item['dimensions'] ?? [] as $d) $all_dimensions[$d] = true;
}
ksort($all_types);
ksort($all_statuses);
ksort($all_stages_used);
ksort($all_dimensions);

// Status badge color
function content_status_badge($status) {
    $map = [
        'published' => 'success',
        'draft'     => 'warning',
        'archived'  => 'secondary',
        'deprecated'=> 'dark',
    ];
    return $map[$status] ?? 'info';
}

// Dimension badge color
function dim_badge($dim) {
    $map = [
        'Mindset'  => 'purple',
        'Toolset'  => 'primary',
        'Skillset' => 'success',
        'mindset'  => 'purple',
        'toolset'  => 'primary',
        'skillset' => 'success',
    ];
    $color = $map[$dim] ?? 'secondary';
    if ($color === 'purple') return 'style="background-color:#6f42c1" class="badge"';
    return 'class="badge bg-' . $color . '"';
}
?>

<h4 class="mb-3"><i class="bi bi-journal-text me-2"></i>Content Catalog</h4>

<?php if (empty($catalog)) { ?>
<div class="alert alert-warning">No content catalog loaded. <a href="index.php?page=admin-framework">Sync framework first</a>.</div>
<?php } else { ?>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold"><?= count($catalog) ?></div>
                <small class="text-muted">Total Items</small>
            </div>
        </div>
    </div>
    <?php
    $status_counts = [];
    foreach ($catalog as $item) {
        $s = $item['status'] ?? 'unknown';
        $status_counts[$s] = ($status_counts[$s] ?? 0) + 1;
    }
    foreach ($status_counts as $status => $count) { ?>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold"><?= $count ?></div>
                <small class="text-muted"><span class="badge bg-<?= content_status_badge($status) ?>"><?= h(ucfirst($status)) ?></span></small>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<!-- Filters -->
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <select class="form-select form-select-sm" id="filterStage">
            <option value="">All Stages</option>
            <?php foreach ($all_stages_used as $s => $_) {
                $stage_data = $stages["stage_" . sprintf('%02d', (int)$s)] ?? null;
                $label = $stage_data ? sprintf('%02d', (int)$s) . ' — ' . ($stage_data['name'] ?? '') : $s;
            ?>
            <option value="<?= h(sprintf('%02d', (int)$s)) ?>"><?= h($label) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" id="filterDimension">
            <option value="">All Dimensions</option>
            <?php foreach ($all_dimensions as $d => $_) { ?>
            <option value="<?= h($d) ?>"><?= h($d) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" id="filterType">
            <option value="">All Types</option>
            <?php foreach ($all_types as $t => $_) { ?>
            <option value="<?= h($t) ?>"><?= h($t) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select form-select-sm" id="filterStatus">
            <option value="">All Statuses</option>
            <?php foreach ($all_statuses as $s => $_) { ?>
            <option value="<?= h($s) ?>"><?= h($s) ?></option>
            <?php } ?>
        </select>
    </div>
</div>

<!-- Content Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="contentCatalog">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Stages</th>
                    <th>Dimensions</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Items</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catalog as $item) {
                    // Locate content files on disk
                    $content_files = function_exists('get_content_files') ? get_content_files($item) : [];
                    $thumbnail_url = function_exists('get_content_thumbnail_url') ? get_content_thumbnail_url($item) : null;

                    // Build hidden data attributes for detail row
                    $detail_json = json_encode([
                        'description'    => $item['description'] ?? '',
                        'deliveryFormat' => $item['deliveryFormat'] ?? '',
                        'dateAdded'      => $item['dateAdded'] ?? '',
                        'addedBy'        => $item['addedBy'] ?? '',
                        'version'        => $item['version'] ?? '',
                        'dateUpdated'    => $item['dateUpdated'] ?? '',
                        'changeNote'     => $item['changeNote'] ?? '',
                        'itemRefs'       => $item['itemRefs'] ?? [],
                        'tags'           => $item['tags'] ?? [],
                        'files'          => $content_files,
                        'thumbnail'      => $thumbnail_url,
                    ]);
                ?>
                <tr data-detail="<?= h($detail_json) ?>">
                    <td class="dt-control text-center" style="cursor:pointer;"><i class="bi bi-chevron-right"></i></td>
                    <td>
                        <strong><?= h($item['title'] ?? '') ?></strong>
                        <br><small class="text-muted"><?= h($item['id'] ?? '') ?></small>
                    </td>
                    <td><span class="badge bg-info"><?= h($item['type'] ?? '') ?></span></td>
                    <td>
                        <?php foreach ($item['stages'] ?? [] as $s) {
                            $sd = $stages["stage_" . sprintf('%02d', (int)$s)] ?? null;
                        ?>
                        <span class="badge" style="background-color: #<?= h($sd['tileColor'] ?? '6c757d') ?>; color: #<?= h($sd['numColor'] ?? 'fff') ?>;"><?= h(sprintf('%02d', (int)$s)) ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php foreach ($item['dimensions'] ?? [] as $d) { ?>
                        <span <?= dim_badge($d) ?>><?= h($d) ?></span>
                        <?php } ?>
                    </td>
                    <td><span class="badge bg-<?= content_status_badge($item['status'] ?? '') ?>"><?= h($item['status'] ?? '') ?></span></td>
                    <td class="small"><?= h($item['estimatedDuration'] ?? '') ?></td>
                    <td><span class="badge bg-secondary"><?= count($item['itemRefs'] ?? []) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-link p-0 text-muted" title="Feedback"
                                onclick="openProposalModal('content', '<?= h($item['id'] ?? '') ?>', '<?= h($item['title'] ?? '') ?>')">
                            <i class="bi bi-chat-left-text"></i>
                        </button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include(__DIR__ . '/../snippets/proposal_modal.php'); ?>

<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.bootstrap5.min.js"></script>
<script>
(function initContentCatalog() {
    // Wait for jQuery + DataTables if loaded async by SPA nav
    if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
        return setTimeout(initContentCatalog, 100);
    }
    var $ = jQuery;

    // Destroy previous instance if SPA re-navigated
    if ($.fn.DataTable.isDataTable('#contentCatalog')) {
        $('#contentCatalog').DataTable().destroy();
    }

    var table = $('#contentCatalog').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: [3, 4], orderable: false },
            { targets: 8, orderable: false, searchable: false }
        ]
    });

    // Expand/collapse detail row
    $('#contentCatalog tbody').off('click', 'td.dt-control').on('click', 'td.dt-control', function() {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        var icon = $(this).find('i');

        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('table-active');
            icon.removeClass('bi-chevron-down').addClass('bi-chevron-right');
        } else {
            var raw = tr.attr('data-detail');
            try { var data = JSON.parse(raw); } catch(e) { return; }
            var esc = function(s) { return $('<span>').text(s || '').html(); };
            var html = '<div class="p-3 bg-body-secondary">';
            if (data.description) html += '<p class="mb-2">' + esc(data.description) + '</p>';
            html += '<div class="row small"><div class="col-md-6">';
            html += '<strong>Delivery:</strong> ' + esc(data.deliveryFormat || 'N/A') + '<br>';
            html += '<strong>Added:</strong> ' + esc(data.dateAdded) + ' by ' + esc(data.addedBy) + '<br>';
            html += '<strong>Version:</strong> ' + esc(data.version);
            if (data.dateUpdated) html += ' (updated ' + esc(data.dateUpdated) + ')';
            if (data.changeNote) html += '<br><strong>Change Note:</strong> ' + esc(data.changeNote);
            html += '</div><div class="col-md-6">';
            if (data.itemRefs && data.itemRefs.length) {
                html += '<strong>Diagnostic Item Refs:</strong><div class="mt-1">';
                data.itemRefs.forEach(function(ref) {
                    html += '<span class="badge border text-body me-1 mb-1">' + esc(ref) + '</span>';
                });
                html += '</div>';
            }
            html += '</div></div>';
            if (data.files && data.files.length) {
                html += '<div class="mt-3"><strong><i class="bi bi-folder2-open me-1"></i>Content Files:</strong><div class="mt-1 d-flex flex-wrap gap-2">';
                var icons = {md:'file-earmark-text',docx:'file-earmark-word',doc:'file-earmark-word',pdf:'file-earmark-pdf',png:'file-earmark-image',jpg:'file-earmark-image',jpeg:'file-earmark-image',mp4:'file-earmark-play',mp3:'file-earmark-music'};
                data.files.forEach(function(f) {
                    var icon = icons[f.ext] || 'file-earmark';
                    html += '<a href="' + esc(f.url) + '" target="_blank" class="btn btn-sm btn-outline-secondary">';
                    html += '<i class="bi bi-' + icon + ' me-1"></i>' + esc(f.name);
                    if (f.size) html += ' <small class="text-muted">(' + esc(f.type) + ')</small>';
                    html += '</a>';
                });
                html += '</div></div>';
            }
            if (data.thumbnail) {
                html += '<div class="mt-3"><strong>Thumbnail:</strong><br>';
                html += '<img src="' + esc(data.thumbnail) + '" alt="Thumbnail" class="img-thumbnail mt-1" style="max-height:120px;">';
                html += '</div>';
            }
            if (data.tags && data.tags.length) {
                html += '<div class="mt-2">';
                data.tags.forEach(function(tag) {
                    html += '<span class="badge bg-body-tertiary text-body border small me-1">' + esc(tag) + '</span>';
                });
                html += '</div>';
            }
            html += '</div>';
            row.child(html).show();
            tr.addClass('table-active');
            icon.removeClass('bi-chevron-right').addClass('bi-chevron-down');
        }
    });

    // Custom column filters
    $('#filterStage').off('change').on('change', function() { table.column(3).search(this.value).draw(); });
    $('#filterDimension').off('change').on('change', function() { table.column(4).search(this.value).draw(); });
    $('#filterType').off('change').on('change', function() { table.column(2).search(this.value).draw(); });
    $('#filterStatus').off('change').on('change', function() { table.column(5).search(this.value).draw(); });
})();
</script>
<?php } ?>
