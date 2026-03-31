<?php
/**
 * Content — Personalized content recommendations and progress
 * Pills navigation, thumbnails, in-progress toggle, completed checkbox.
 */
$page_title = 'My Content';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$tab = $_GET['tab'] ?? 'all';

// Load all visible content with states
$all_content = resolve_all_content_states($uid, $sid);

// Load progress records for status lookups
$progress_rows = get_content_progress_list($uid, $sid);
$progress_map = [];
foreach ($progress_rows as $row) {
    $progress_map[$row['catalog_item_id']] = $row;
}

// Filter: show everything except locked
$visible = [];
$in_progress_count = 0;
$completed_count = 0;
foreach ($all_content as $entry) {
    if ($entry['state'] === 'locked') continue;
    $item_id = $entry['item']['id'] ?? '';
    $db_status = $progress_map[$item_id]['status'] ?? null;
    $entry['db_status'] = $db_status;
    $visible[] = $entry;
    if ($db_status === 'started') $in_progress_count++;
    if ($db_status === 'completed') $completed_count++;
}

// Apply tab filter
$filtered = [];
foreach ($visible as $entry) {
    if ($tab === 'in_progress' && $entry['db_status'] !== 'started') continue;
    if ($tab === 'completed' && $entry['db_status'] !== 'completed') continue;
    $filtered[] = $entry;
}

$instance = get_user_instance($uid, $sid, 'ai-assessment');
$quote = get_contextual_quote(
    ($instance && ($instance['instance_data'] ?? null)) ? ($instance['instance_data']['placement']['currentStage'] ?? null) : null
);
?>

<?php if ($quote && !empty($quote['text'] ?? $quote['quote'] ?? '')) { ?>
<div class="card mb-3 border-start border-primary border-3">
    <div class="card-body py-2">
        <small class="fst-italic">"<?= h($quote['text'] ?? $quote['quote'] ?? '') ?>"</small>
        <small class="text-muted"> — <?= h($quote['author'] ?? $quote['expertName'] ?? '') ?></small>
    </div>
</div>
<?php } ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="index.php?page=content&tab=all">
                All <span class="badge bg-<?= $tab === 'all' ? 'light text-dark' : 'secondary' ?>"><?= count($visible) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'in_progress' ? 'active' : '' ?>" href="index.php?page=content&tab=in_progress">
                In Progress <span class="badge bg-<?= $tab === 'in_progress' ? 'light text-dark' : 'secondary' ?>"><?= $in_progress_count ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'completed' ? 'active' : '' ?>" href="index.php?page=content&tab=completed">
                Completed <span class="badge bg-<?= $tab === 'completed' ? 'light text-dark' : 'secondary' ?>"><?= $completed_count ?></span>
            </a>
        </li>
    </ul>
    <a href="index.php?page=content-detail" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-compass me-1"></i>DSWA Community
    </a>
</div>

<?php if (empty($filtered)) { ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-book display-4"></i>
    <p class="mt-2">
        <?php if ($tab === 'in_progress') { ?>
            No content in progress yet. Toggle items to start tracking.
        <?php } elseif ($tab === 'completed') { ?>
            No completed content yet.
        <?php } else { ?>
            Complete your assessment to receive personalized content.
        <?php } ?>
    </p>
</div>
<?php } else { ?>
<div class="row g-3">
    <?php foreach ($filtered as $entry) {
        $item = $entry['item'];
        $item_id = $item['id'] ?? '';
        $db_status = $entry['db_status'];
        $is_started = ($db_status === 'started');
        $is_completed = ($db_status === 'completed');
        $thumb_url = get_content_thumbnail_url($item);
        $type = $item['type'] ?? 'content';
        $type_color = ($type === 'activity') ? 'primary' : (($type === 'resource') ? 'info' : (($type === 'short-course') ? 'warning' : 'secondary'));
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 content-card <?= $is_completed ? 'content-card--completed' : '' ?>">
            <?php if ($thumb_url) { ?>
            <a href="index.php?page=content-detail&id=<?= urlencode($item_id) ?>" data-no-spa class="content-card__thumb-link">
                <img src="<?= h($thumb_url) ?>" alt="" class="card-img-top content-card__thumb">
            </a>
            <?php } else { ?>
            <a href="index.php?page=content-detail&id=<?= urlencode($item_id) ?>" data-no-spa class="content-card__thumb-link content-card__thumb-placeholder">
                <i class="bi bi-journal-richtext"></i>
            </a>
            <?php } ?>
            <div class="card-body pb-2">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-<?= $type_color ?>"><?= h($type) ?></span>
                    <?php if (!empty($item['estimatedDuration'])) { ?>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= h($item['estimatedDuration']) ?></small>
                    <?php } ?>
                </div>
                <h6 class="card-title mb-1">
                    <a href="index.php?page=content-detail&id=<?= urlencode($item_id) ?>" data-no-spa class="text-decoration-none content-card__title-link"><?= h($item['title'] ?? '') ?></a>
                </h6>
                <p class="card-text small text-muted mb-2"><?= h(mb_substr($item['description'] ?? '', 0, 100)) ?>...</p>
                <?php if (!empty($item['dimensions'])) { ?>
                <div class="mb-2">
                    <?php foreach ((array)$item['dimensions'] as $dim) {
                        $dim_lower = strtolower($dim);
                        $dim_color = ($dim_lower === 'mindset') ? '#b388d9' : (($dim_lower === 'toolset') ? '#6ca6d9' : (($dim_lower === 'skillset') ? '#6bc96b' : '#e07878'));
                    ?>
                    <span class="badge" style="background:<?= $dim_color ?>20;color:<?= $dim_color ?>;font-size:0.7rem;"><?= h($dim) ?></span>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input content-toggle-progress" type="checkbox"
                           data-item-id="<?= h($item_id) ?>"
                           <?= $is_started ? 'checked' : '' ?>
                           <?= $is_completed ? 'disabled' : '' ?>
                           id="prog-<?= h($item_id) ?>">
                    <label class="form-check-label small" for="prog-<?= h($item_id) ?>">In Progress</label>
                </div>
                <div class="form-check mb-0">
                    <input class="form-check-input content-toggle-complete" type="checkbox"
                           data-item-id="<?= h($item_id) ?>"
                           <?= $is_completed ? 'checked' : '' ?>
                           id="done-<?= h($item_id) ?>">
                    <label class="form-check-label small" for="done-<?= h($item_id) ?>">Done</label>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>

<script>
(function() {
    // AJAX toggle: in-progress
    document.querySelectorAll('.content-toggle-progress').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            var action = this.checked ? 'content_start' : 'content_unstart';
            apiPost(action, { catalog_item_id: itemId }, function(resp) {
                if (resp.error) { toggle.checked = !toggle.checked; }
            });
        });
    });

    // AJAX toggle: completed
    document.querySelectorAll('.content-toggle-complete').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            var card = this.closest('.content-card');
            var progressToggle = card ? card.querySelector('.content-toggle-progress') : null;
            if (this.checked) {
                apiPost('content_complete', { catalog_item_id: itemId }, function(resp) {
                    if (resp.error) { toggle.checked = false; return; }
                    if (progressToggle) { progressToggle.checked = false; progressToggle.disabled = true; }
                    if (card) card.classList.add('content-card--completed');
                });
            } else {
                apiPost('content_uncomplete', { catalog_item_id: itemId }, function(resp) {
                    if (resp.error) { toggle.checked = true; return; }
                    if (progressToggle) { progressToggle.disabled = false; }
                    if (card) card.classList.remove('content-card--completed');
                });
            }
        });
    });
})();
</script>
