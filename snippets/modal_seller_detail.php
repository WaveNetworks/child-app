<?php
/**
 * Modal: Seller Detail — Coach view of a linked seller's assessment data.
 */
$seller_id = (int)($_POST['seller_id'] ?? $_GET['seller_id'] ?? 0);
$seller_shard_id = trim($_POST['seller_shard_id'] ?? $_GET['seller_shard_id'] ?? '');
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

$seller = null;
$inst_data = null;
$coach_notes = [];

if ($seller_id && $seller_shard_id) {
    // Verify relationship
    $stmt = child_db_query_shard_prepared($sid,
        "SELECT id FROM coach_relationships WHERE coach_user_id = ? AND seller_user_id = ? AND status = 'active'",
        [$uid, $seller_id]
    );
    if ($stmt && $stmt->fetch()) {
        $profile = get_user_profile($seller_id, $seller_shard_id);
        $user = get_user($seller_id);
        $instance = get_user_instance($seller_id, $seller_shard_id, 'ai-assessment');
        $inst_data = $instance ? $instance['instance_data'] : null;
        $seller = [
            'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
        ];
        $coach_notes = $inst_data ? ($inst_data['coachNotes'] ?? []) : [];
    }
}

$placement = $inst_data ? ($inst_data['placement'] ?? []) : [];
$scores = $inst_data ? ($inst_data['scores'] ?? []) : [];
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $seller ? h($seller['name']) : 'Seller Detail' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if (!$seller) { ?>
    <p class="text-danger">Seller not found or no active relationship.</p>
    <?php } else { ?>

    <!-- Stage Placement -->
    <div class="text-center mb-3">
        <div class="fs-2 fw-bold text-primary">Stage <?= h($placement['currentStage'] ?? '?') ?></div>
        <p class="text-muted"><?= h($placement['stageName'] ?? 'Not assessed') ?></p>
        <?php if (!empty($placement['hardWallBlocking'])) { ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i><?= h($placement['hardWallBlocking']['label'] ?? 'At Wall') ?></span>
        <?php } ?>
    </div>

    <!-- Dimension Scores -->
    <?php if (!empty($scores)) { ?>
    <div class="row g-2 mb-3">
        <div class="col-4 text-center">
            <small class="text-info fw-bold">Mindset</small>
            <div class="fs-5"><?= round(($scores['mindset_score'] ?? 0) * 100) ?>%</div>
        </div>
        <div class="col-4 text-center">
            <small class="text-success fw-bold">Toolset</small>
            <div class="fs-5"><?= round(($scores['toolset_score'] ?? 0) * 100) ?>%</div>
        </div>
        <div class="col-4 text-center">
            <small class="text-warning fw-bold">Skillset</small>
            <div class="fs-5"><?= round(($scores['skillset_score'] ?? 0) * 100) ?>%</div>
        </div>
    </div>
    <?php } ?>

    <!-- Content Progress -->
    <?php
    $progress = get_content_progress_list($seller_id, $seller_shard_id);
    $started = array_filter($progress, fn($p) => $p['status'] === 'started');
    $completed = array_filter($progress, fn($p) => $p['status'] === 'completed');
    ?>
    <h6 class="small text-muted text-uppercase mb-2">Content Progress</h6>
    <div class="d-flex gap-3 mb-3">
        <span class="badge bg-warning"><?= count($started) ?> In Progress</span>
        <span class="badge bg-success"><?= count($completed) ?> Completed</span>
    </div>

    <!-- Coach Notes -->
    <?php if (!empty($coach_notes)) { ?>
    <h6 class="small text-muted text-uppercase mb-2">Coach Notes</h6>
    <?php foreach (array_slice($coach_notes, -5) as $note) { ?>
    <div class="border-start border-primary border-2 ps-2 mb-2">
        <small><?= h($note['note'] ?? '') ?></small>
        <br><small class="text-muted"><?= h($note['coachName'] ?? '') ?> — <?= date('M j', strtotime($note['date'] ?? '')) ?></small>
    </div>
    <?php } ?>
    <?php } ?>

    <!-- Session Notes -->
    <?php $session_notes = $inst_data ? ($inst_data['sessionNotes'] ?? []) : []; ?>
    <?php if (!empty($session_notes)) { ?>
    <h6 class="small text-muted text-uppercase mb-2">AI Insights</h6>
    <?php foreach (array_slice($session_notes, -3) as $note) { ?>
    <div class="border-start border-info border-2 ps-2 mb-2">
        <small><?= h($note['observation'] ?? '') ?></small>
    </div>
    <?php } ?>
    <?php } ?>

    <?php } ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <?php if ($seller) { ?>
    <button type="button" class="btn btn-primary" onclick="modalView('modal=coach_note&seller_user_id=<?= $seller_id ?>&seller_shard_id=<?= h($seller_shard_id) ?>')">Add Note</button>
    <?php } ?>
</div>
