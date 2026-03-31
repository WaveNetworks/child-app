<?php
/**
 * Results — Assessment results with scores and placement
 */
$page_title = 'Assessment Results';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$instance = get_user_instance($uid, $sid, 'ai-assessment');

$data_inst = $instance['instance_data'] ?? [];
$placement = $data_inst['placement'] ?? [];
$scores = $data_inst['scores'] ?? [];
$hard_wall = $placement['hardWallBlocking'] ?? null;
$session_notes = $data_inst['sessionNotes'] ?? [];
$stage_num = $placement['currentStage'] ?? '00';
$stage_data = get_stage((int)$stage_num);
$quote = get_contextual_quote($stage_num);
?>

<div class="row g-4">
    <!-- Stage Placement -->
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-body text-center py-4">
                <div class="display-3 fw-bold text-primary mb-2">Stage <?= h($stage_num) ?></div>
                <h3><?= h($placement['stageName'] ?? '') ?></h3>
                <?php if ($stage_data) { ?>
                <p class="text-muted mb-2"><?= h($stage_data['tagline'] ?? $stage_data['description'] ?? '') ?></p>
                <?php } ?>
                <?php if ($quote) { ?>
                <blockquote class="blockquote mt-3 mb-0">
                    <p class="small fst-italic">"<?= h($quote['text'] ?? $quote['quote'] ?? '') ?>"</p>
                    <footer class="blockquote-footer small"><?= h($quote['author'] ?? $quote['expertName'] ?? '') ?></footer>
                </blockquote>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Dimension Breakdown -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-info"><i class="bi bi-lightbulb me-1"></i>Mindset</h6>
                <div class="display-5 fw-bold text-info"><?= round(($scores['mindset_score'] ?? 0) * 100) ?>%</div>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar bg-info" style="width: <?= round(($scores['mindset_score'] ?? 0) * 100) ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-success"><i class="bi bi-tools me-1"></i>Toolset</h6>
                <div class="display-5 fw-bold text-success"><?= round(($scores['toolset_score'] ?? 0) * 100) ?>%</div>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?= round(($scores['toolset_score'] ?? 0) * 100) ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-warning"><i class="bi bi-gear me-1"></i>Skillset</h6>
                <div class="display-5 fw-bold text-warning"><?= round(($scores['skillset_score'] ?? 0) * 100) ?>%</div>
                <div class="progress mt-2" style="height: 10px;">
                    <div class="progress-bar bg-warning" style="width: <?= round(($scores['skillset_score'] ?? 0) * 100) ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hard Wall -->
    <?php if ($hard_wall) { ?>
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-10">
                <h6 class="mb-0 text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Growth Opportunity: <?= h($hard_wall['label'] ?? '') ?></h6>
            </div>
            <div class="card-body">
                <p><?= h($hard_wall['description'] ?? '') ?></p>
                <?php if (!empty($hard_wall['expertQuote'])) { ?>
                <blockquote class="blockquote">
                    <p class="small fst-italic">"<?= h($hard_wall['expertQuote']) ?>"</p>
                    <footer class="blockquote-footer"><?= h($hard_wall['expertName'] ?? '') ?></footer>
                </blockquote>
                <?php } ?>
                <?php if (!empty($hard_wall['prescriptionSummary'])) { ?>
                <p class="fw-bold">Next Step: <?= h($hard_wall['prescriptionSummary']) ?></p>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Session Notes -->
    <?php if (!empty($session_notes)) { ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>AI Coaching Insights</h6></div>
            <div class="card-body">
                <?php foreach (array_slice($session_notes, -5) as $note) { ?>
                <div class="border-start border-primary border-3 ps-3 mb-3">
                    <p class="mb-1"><?= h($note['observation'] ?? '') ?></p>
                    <small class="text-muted">
                        <?= h($note['dimension'] ?? '') ?> <?= $note['stage'] ? '| Stage ' . h($note['stage']) : '' ?>
                    </small>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Actions -->
    <div class="col-12 text-center">
        <a href="index.php?page=content" class="btn btn-primary btn-lg me-2">
            <i class="bi bi-book me-2"></i>View Recommended Content
        </a>
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-house me-2"></i>Back to Dashboard
        </a>
    </div>
</div>
