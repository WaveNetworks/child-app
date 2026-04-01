<?php
/**
 * modal_kpi_detail.php
 * KPI detail modal — shows full KPI info, stage context, and level selection.
 * Loaded via modalView('modal=kpi_detail&kpi_id=s01-kpi-01&source_stage=01').
 */
$kpi_id       = sanitize($_POST['kpi_id'] ?? '', SQL);
$source_stage = sanitize($_POST['source_stage'] ?? '', SQL);

// Load KPI metadata from framework
$kpi = find_kpi_meta($kpi_id);

// Load stage context for kpiType / kpiSignal
$stage = $source_stage !== '' ? get_stage((int)$source_stage) : null;

// Load user's current KPI response
$uid = $_SESSION['user_id'] ?? 0;
$sid = $_SESSION['shard_id'] ?? '';
$current_level = 'unanswered';
if ($uid && $sid) {
    $instance = get_user_instance($uid, $sid, 'ai-assessment');
    if ($instance) {
        $kpi_responses = (array)($instance['instance_data']['kpiResponses'] ?? []);
        $resp = $kpi_responses[$kpi_id] ?? null;
        $current_level = is_array($resp) ? ($resp['value'] ?? $resp['level'] ?? 'unanswered') : ($resp ?: 'unanswered');
    }
}

$is_non_neg = !empty($kpi['nonNeg']);
$levels = ['beginner', 'developing', 'mastery'];
$level_colors = ['beginner' => 'danger', 'developing' => 'warning', 'mastery' => 'success'];
$level_icons  = ['beginner' => 'bi-1-circle', 'developing' => 'bi-2-circle', 'mastery' => 'bi-3-circle'];
?>
<div class="modal-header border-bottom border-secondary border-opacity-25">
    <div>
        <h5 class="modal-title mb-1"><?= h($kpi['metric'] ?? $kpi_id) ?></h5>
        <div class="d-flex align-items-center gap-2">
            <?php if ($stage) { ?>
            <span class="badge bg-primary bg-opacity-75 small">Stage <?= h($source_stage) ?></span>
            <span class="text-muted small"><?= h($stage['name'] ?? '') ?></span>
            <?php } ?>
            <?php if ($is_non_neg) { ?>
            <span class="badge bg-danger bg-opacity-75 small"><i class="bi bi-exclamation-triangle-fill me-1"></i>Non-Negotiable</span>
            <?php } ?>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if ($stage && !empty($stage['kpiType'])) { ?>
    <div class="alert alert-info bg-opacity-10 border-info border-opacity-25 py-2 px-3 mb-3">
        <div class="small fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>What This Measures</div>
        <p class="small mb-0"><?= h($stage['kpiType']) ?></p>
    </div>
    <?php } ?>

    <?php if ($stage && !empty($stage['kpiSignal'])) { ?>
    <div class="alert alert-warning bg-opacity-10 border-warning border-opacity-25 py-2 px-3 mb-3">
        <div class="small fw-semibold mb-1"><i class="bi bi-graph-up me-1"></i>What It Signals</div>
        <p class="small mb-0"><?= h($stage['kpiSignal']) ?></p>
    </div>
    <?php } ?>

    <p class="small text-muted mb-3">
        <i class="bi bi-chat-square-quote me-1"></i><em>Be honest with yourself — read each level below and pick the one that most accurately describes where you are right now, not where you want to be.</em>
    </p>

    <div id="kpiModalLevels">
        <?php foreach ($levels as $lvl) {
            $color = $level_colors[$lvl];
            $icon  = $level_icons[$lvl];
            $is_current = ($current_level === $lvl);
        ?>
        <div class="kpi-modal-level rounded mb-3 p-0 border <?= $is_current ? 'border-2 border-' . $color : 'border-secondary border-opacity-25' ?>"
             style="cursor:pointer; transition: all 0.2s ease; <?= $is_current ? 'background:rgba(var(--bs-' . $color . '-rgb),0.12);' : '' ?>"
             data-level="<?= $lvl ?>"
             data-kpi-id="<?= h($kpi_id) ?>"
             data-source-stage="<?= h($source_stage) ?>"
             onclick="saveKpiFromModal(this, '<?= h($kpi_id) ?>', '<?= $lvl ?>', '<?= h($source_stage) ?>')">
            <div class="d-flex align-items-stretch">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0 px-3 py-3 text-white fw-bold"
                     style="min-width:120px; background:var(--bs-<?= $color ?>); font-size:0.85rem; letter-spacing:0.02em;<?= !$is_current ? 'opacity:0.7;' : '' ?>">
                    <?php if ($is_current) { ?><i class="bi bi-check-circle-fill me-1"></i><?php } else { ?><i class="bi <?= $icon ?> me-1"></i><?php } ?>
                    <?= ucfirst($lvl) ?>
                </div>
                <div class="px-3 py-3 small d-flex align-items-center" style="line-height:1.6;">
                    <?= h($kpi[$lvl] ?? '') ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <div id="kpiModalSaveStatus" class="text-center" style="display:none;">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span class="small text-muted ms-2">Saving...</span>
    </div>

    <?php if ($current_level !== 'unanswered') { ?>
    <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center gap-2 small text-muted">
            <i class="bi bi-clock-history"></i>
            <span>Your current response: <strong class="text-<?= $level_colors[$current_level] ?? 'secondary' ?>"><?= ucfirst($current_level) ?></strong></span>
            <?php if ($current_level === 'developing' || $current_level === 'mastery') { ?>
            <span class="badge bg-success bg-opacity-75 ms-auto"><i class="bi bi-check2 me-1"></i>Meets threshold</span>
            <?php } else { ?>
            <span class="badge bg-danger bg-opacity-75 ms-auto"><i class="bi bi-x-lg me-1"></i>Below threshold</span>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
<div class="modal-footer border-top border-secondary border-opacity-25">
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
</div>
