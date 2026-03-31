<?php
/**
 * Framework Sync — Admin page for managing framework data
 */
$page_title = 'Framework Sync';

$versions = get_framework_versions();
$active = get_active_framework_version();
?>

<h4 class="mb-3"><i class="bi bi-database me-2"></i>Framework Data Sync</h4>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Current Version</h6>
        <form method="post">
            <input type="hidden" name="action" value="sync_framework">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Check for Updates</button>
        </form>
    </div>
    <div class="card-body">
        <?php if ($active) { ?>
        <dl class="row mb-0">
            <dt class="col-sm-3">Version</dt><dd class="col-sm-9"><?= h($active['version_label']) ?></dd>
            <dt class="col-sm-3">Schema</dt><dd class="col-sm-9"><?= h($active['schema_version']) ?></dd>
            <dt class="col-sm-3">Source</dt><dd class="col-sm-9"><?= h($active['source']) ?></dd>
            <dt class="col-sm-3">Loaded At</dt><dd class="col-sm-9"><?= h($active['loaded_at']) ?></dd>
            <dt class="col-sm-3">Checksum</dt><dd class="col-sm-9"><code class="small"><?= h(substr($active['checksum'], 0, 16)) ?>...</code></dd>
        </dl>
        <?php } else { ?>
        <p class="text-muted mb-0">No framework data loaded. Click "Check for Updates" to sync.</p>
        <?php } ?>
    </div>
</div>

<?php if (!empty($versions)) { ?>
<div class="card">
    <div class="card-header"><h6 class="mb-0">Version History</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Version</th><th>Schema</th><th>Source</th><th>Loaded</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($versions as $v) { ?>
                <tr>
                    <td><?= h($v['version_label']) ?></td>
                    <td><?= h($v['schema_version']) ?></td>
                    <td><?= h($v['source']) ?></td>
                    <td><?= h($v['loaded_at']) ?></td>
                    <td><?= $v['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <?php if (!$v['is_active']) { ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="rollback_framework">
                            <input type="hidden" name="version_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Rollback to this version?')">Rollback</button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>
