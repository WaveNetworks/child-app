<?php
/**
 * Admin Stages — Browse the DSWA Training Continuum framework stages
 */
$page_title = 'Framework Stages';

$stages = get_all_stages();
$tiers = get_tiers();
$hard_walls = get_hard_walls();
$header = get_framework_header();

// Build wall lookup: from_stage => wall data
$wall_after = [];
if (is_array($hard_walls)) {
    foreach ($hard_walls as $wall) {
        $wall_after[$wall['from'] ?? ''] = $wall;
    }
}

// Build tier lookup: stage_num => tier
$tier_for_stage = [];
$tier_list = $tiers['tiers'] ?? $tiers ?? [];
if (is_array($tier_list)) {
    foreach ($tier_list as $tier) {
        $range = $tier['stageRange'] ?? [];
        if (count($range) === 2) {
            for ($i = (int)$range[0]; $i <= (int)$range[1]; $i++) {
                $tier_for_stage[sprintf('%02d', $i)] = $tier;
            }
        }
    }
}
?>

<h4 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>Framework Stages</h4>

<?php
$proposal_counts = get_proposal_counts_by_target('stage') + get_proposal_counts_by_target('item') + get_proposal_counts_by_target('kpi') + get_proposal_counts_by_target('wall');
?>

<?php if (empty($stages)) { ?>
<div class="alert alert-warning">No framework data loaded. <a href="index.php?page=admin-framework">Sync framework first</a>.</div>
<?php } else { ?>

<?php
$current_tier_id = null;
foreach ($stages as $key => $stage) {
    $num = $stage['num'] ?? '';
    $tier = $tier_for_stage[$num] ?? null;

    // Render tier header when tier changes
    if ($tier && ($tier['id'] ?? '') !== $current_tier_id) {
        $current_tier_id = $tier['id'] ?? '';
        if ($num !== '00') { ?>
        <div class="mb-2"></div>
        <?php } ?>
        <div class="d-flex align-items-center mb-3 mt-4">
            <span class="badge fs-6 me-2" style="background-color: #<?= h($tier['color'] ?? '6c757d') ?>;">
                <?= h($tier['name'] ?? $current_tier_id) ?>
            </span>
            <small class="text-muted"><?= h($tier['description'] ?? '') ?></small>
        </div>
    <?php } ?>

    <div class="card mb-2">
        <div class="card-header p-0">
            <button class="btn w-100 text-start d-flex align-items-center p-3" type="button"
                    data-bs-toggle="collapse" data-bs-target="#stage-<?= h($num) ?>">
                <span class="badge rounded-pill fs-6 me-3" style="background-color: #<?= h($stage['tileColor'] ?? '6c757d') ?>; color: #<?= h($stage['numColor'] ?? 'fff') ?>; min-width: 2.5rem;">
                    <?= h($num) ?>
                </span>
                <div>
                    <strong><?= h($stage['name'] ?? '') ?></strong>
                    <br><small class="text-muted"><?= h($stage['tagline'] ?? '') ?></small>
                </div>
                <?php
                $stage_key = 'stage:stage_' . $num;
                $sc = $proposal_counts[$stage_key] ?? null;
                if ($sc && $sc['pending'] > 0) { ?>
                <span class="badge bg-warning ms-2" title="<?= $sc['pending'] ?> pending proposals"><?= $sc['pending'] ?></span>
                <?php } ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" title="Feedback on this stage"
                        onclick="event.stopPropagation(); openProposalModal('stage', 'stage_<?= h($num) ?>', 'Stage <?= h($num) ?> — <?= h($stage['name'] ?? '') ?>')">
                    <i class="bi bi-chat-left-text"></i>
                </button>
                <i class="bi bi-chevron-down ms-2"></i>
            </button>
        </div>
        <div class="collapse" id="stage-<?= h($num) ?>">
            <div class="card-body">
                <?php if (!empty($stage['description'])) { ?>
                <p class="mb-3"><?= h($stage['description']) ?></p>
                <?php } ?>

                <div class="row mb-3">
                    <?php if (!empty($stage['behavior'])) { ?>
                    <div class="col-md-4">
                        <div class="card bg-body-secondary h-100">
                            <div class="card-body py-2 px-3">
                                <small class="text-muted fw-bold">Behavior</small>
                                <p class="mb-0 small"><?= h($stage['behavior']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <?php if (!empty($stage['belief'])) { ?>
                    <div class="col-md-4">
                        <div class="card bg-body-secondary h-100">
                            <div class="card-body py-2 px-3">
                                <small class="text-muted fw-bold">Belief</small>
                                <p class="mb-0 small"><?= h($stage['belief']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <?php if (!empty($stage['stuck'])) { ?>
                    <div class="col-md-4">
                        <div class="card bg-body-secondary h-100">
                            <div class="card-body py-2 px-3">
                                <small class="text-muted fw-bold">When Stuck</small>
                                <p class="mb-0 small"><?= h($stage['stuck']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>

                <!-- Diagnostic Items -->
                <ul class="nav nav-tabs" role="tablist">
                    <?php foreach (['mindset', 'toolset', 'skillset'] as $di => $dim) {
                        $items = $stage[$dim] ?? [];
                        $active_items = array_filter($items, fn($i) => empty($i['deprecated']));
                    ?>
                    <li class="nav-item">
                        <button class="nav-link <?= $di === 0 ? 'active' : '' ?> small" data-bs-toggle="tab"
                                data-bs-target="#s<?= h($num) ?>-<?= $dim ?>" type="button">
                            <?= ucfirst($dim) ?> <span class="badge bg-secondary"><?= count($active_items) ?></span>
                        </button>
                    </li>
                    <?php } ?>
                </ul>
                <div class="tab-content border border-top-0 rounded-bottom p-3 mb-3">
                    <?php foreach (['mindset', 'toolset', 'skillset'] as $di => $dim) {
                        $items = $stage[$dim] ?? [];
                    ?>
                    <div class="tab-pane <?= $di === 0 ? 'show active' : '' ?>" id="s<?= h($num) ?>-<?= $dim ?>">
                        <?php if (empty($items)) { ?>
                        <p class="text-muted small mb-0">No items defined.</p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0">
                                <thead><tr><th>ID</th><th>Label</th><th>Weight</th><th>Flags</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($items as $item) { ?>
                                <tr class="<?= !empty($item['deprecated']) ? 'text-decoration-line-through text-muted' : '' ?>">
                                    <td><code><?= h($item['id'] ?? '') ?></code></td>
                                    <td>
                                        <?= h($item['label'] ?? '') ?>
                                        <?php if (!empty($item['sub'])) { ?>
                                        <br><small class="text-muted"><?= h($item['sub']) ?></small>
                                        <?php } ?>
                                    </td>
                                    <td><?= h($item['weight'] ?? 1) ?></td>
                                    <td>
                                        <?php if (!empty($item['nonNeg'])) { ?>
                                        <span class="badge bg-danger">Non-Neg</span>
                                        <?php } ?>
                                        <?php if (!empty($item['deprecated'])) { ?>
                                        <span class="badge bg-secondary">Deprecated</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-muted" title="Feedback"
                                                onclick="openProposalModal('item', '<?= h($item['id'] ?? '') ?>', '<?= h(($item['id'] ?? '') . ' — ' . ($item['label'] ?? '')) ?>')">
                                            <i class="bi bi-chat-left-text"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <!-- KPIs -->
                <?php $kpis = $stage['kpis'] ?? []; ?>
                <?php if (!empty($kpis)) { ?>
                <h6 class="mt-3"><i class="bi bi-speedometer2 me-1"></i>KPIs</h6>
                <div class="table-responsive">
                    <table class="table table-sm small mb-3">
                        <thead><tr><th>ID</th><th>Metric</th><th>Beginner</th><th>Developing</th><th>Mastery</th></tr></thead>
                        <tbody>
                        <?php foreach ($kpis as $kpi) { ?>
                        <tr>
                            <td><code><?= h($kpi['id'] ?? '') ?></code></td>
                            <td><?= h($kpi['metric'] ?? $kpi['label'] ?? '') ?></td>
                            <td><span class="text-danger"><?= h($kpi['beginner'] ?? '') ?></span></td>
                            <td><span class="text-warning"><?= h($kpi['developing'] ?? '') ?></span></td>
                            <td><span class="text-success"><?= h($kpi['mastery'] ?? '') ?></span></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>

                <!-- Content Library -->
                <?php
                $content_lib = $stage['contentLibrary'] ?? [];
                $existing = $content_lib['existing'] ?? [];
                $gaps = $content_lib['gaps'] ?? [];
                ?>
                <?php if (!empty($existing) || !empty($gaps)) { ?>
                <h6 class="mt-3"><i class="bi bi-book me-1"></i>Content Library</h6>
                <?php if (!empty($gaps)) { ?>
                <div class="mb-2">
                    <?php foreach ($gaps as $gap) { ?>
                    <div class="d-flex align-items-start mb-2 small">
                        <span class="badge bg-<?= ($gap['priority'] ?? '') === 'critical' ? 'danger' : (($gap['priority'] ?? '') === 'high' ? 'warning' : 'info') ?> me-2 mt-1">
                            <?= h($gap['priority'] ?? 'normal') ?>
                        </span>
                        <div>
                            <strong><?= h($gap['title'] ?? '') ?></strong>
                            <span class="text-muted">(<?= h($gap['suggestedFormat'] ?? '') ?>, ~<?= h($gap['estimatedLength'] ?? '') ?>)</span>
                            <?php if (!empty($gap['reason'])) { ?>
                            <br><small class="text-muted"><?= h($gap['reason']) ?></small>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php } ?>

                <!-- Field Wisdom -->
                <?php $wisdom = $stage['fieldWisdom'] ?? []; ?>
                <?php if (!empty($wisdom)) { ?>
                <h6 class="mt-3"><i class="bi bi-chat-quote me-1"></i>Field Wisdom</h6>
                <?php foreach ($wisdom as $fw) { ?>
                <div class="card bg-body-secondary mb-2">
                    <div class="card-body py-2 px-3 small">
                        <strong><?= h($fw['title'] ?? '') ?></strong>
                        <?php if (!empty($fw['speaker'])) { ?>
                        <span class="text-muted">— <?= h($fw['speaker']) ?></span>
                        <?php } ?>
                        <?php if (!empty($fw['coreMessage'])) { ?>
                        <p class="mb-0 mt-1"><?= h($fw['coreMessage']) ?></p>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <?php } ?>

            </div>
        </div>
    </div>

    <!-- Hard Wall -->
    <?php if (isset($wall_after[$num])) {
        $wall = $wall_after[$num];
    ?>
    <div class="alert bg-warning bg-opacity-10 border-warning d-flex align-items-start my-3" role="alert">
        <i class="bi bi-shield-exclamation fs-4 text-warning me-3 mt-1"></i>
        <div>
            <strong>Hard Wall: <?= h($wall['label'] ?? '') ?></strong>
            <p class="mb-1 small"><?= h($wall['description'] ?? '') ?></p>
            <?php if (!empty($wall['prescriptionSummary'])) { ?>
            <small class="text-muted"><strong>Prescription:</strong> <?= h($wall['prescriptionSummary']) ?></small>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

<?php } // end foreach stages ?>

<?php include(__DIR__ . '/../snippets/proposal_modal.php'); ?>
<?php } // end else ?>
