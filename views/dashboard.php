<?php
/**
 * Dashboard — ElevateHER Learning Suite
 * Shows assessment status, stage placement, quick actions, and contextual quote.
 */
$page_title = 'Dashboard';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$roles = get_user_role($uid);
$instance = get_user_instance($uid, $sid, 'ai-assessment');
$placement = $instance ? ($instance['instance_data']['placement'] ?? null) : null;
$all_scores = $instance ? ($instance['instance_data']['scores'] ?? null) : null;
$current_stage_id = $placement['placedStage'] ?? ($placement['currentStage'] ?? null);
// Aggregate dimension scores across all assessed stages for the dashboard overview
$scores = null;
if ($all_scores && is_array($all_scores)) {
    $total_m = 0; $total_t = 0; $total_s = 0; $count = 0;
    foreach ($all_scores as $sid_key => $stage_scores) {
        if (!is_array($stage_scores)) continue;
        $m = $stage_scores['mindset'] ?? 0;
        $t = $stage_scores['toolset'] ?? 0;
        $s = $stage_scores['skillset'] ?? 0;
        if ($m > 0 || $t > 0 || $s > 0) {
            $total_m = max($total_m, $m);
            $total_t = max($total_t, $t);
            $total_s = max($total_s, $s);
            $count++;
        }
    }
    if ($count > 0) {
        $scores = ['mindset' => $total_m, 'toolset' => $total_t, 'skillset' => $total_s];
    }
}
// Compute advancement blockers for diagnostic panel
$advancement_blockers = null;
if ($instance && $placement && $current_stage_id !== null) {
    $inst_data = $instance['instance_data'];
    $kpi_responses = (array)($inst_data['kpiResponses'] ?? []);
    $responses_all = (array)($inst_data['responses'] ?? []);
    $stage_num = (int)ltrim($current_stage_id, '0');
    $stage_data = get_stage($stage_num);

    if ($stage_data) {
        $diag_items = get_stage_diagnostic_items($stage_data);
        $threshold = (float)($stage_data['advanceThreshold'] ?? 0.80);
        $overall = compute_overall_score($responses_all, $diag_items);
        $stage_complete = check_stage_complete($responses_all, $diag_items, $threshold);

        // Find unresolved items — split into "needs work" vs "not yet assessed"
        $unresolved_items = [];
        $not_yet_assessed = [];
        foreach ($diag_items as $item) {
            if ($item['deprecated'] ?? false) continue;
            $id = $item['id'] ?? '';
            if (!isset($responses_all[$id])) {
                $not_yet_assessed[] = $item;
            } else {
                $val = read_response_value($responses_all[$id]);
                if ($val === false) {
                    if ($item['nonNeg'] ?? false) {
                        $item['_blocking_nonNeg'] = true;
                    }
                    $unresolved_items[] = $item;
                }
            }
        }

        // Find blocking hard walls and their KPIs
        $hard_walls = get_hard_walls();
        $blocking_wall = null;
        $blocking_kpis = [];
        $all_stage_kpis = [];
        foreach ($hard_walls as $wall) {
            if (($wall['from'] ?? '') === $current_stage_id) {
                foreach ($wall['gatingKpiStages'] ?? [] as $gs) {
                    $gs_stage = get_stage((int)$gs);
                    if (!$gs_stage) continue;
                    foreach ($gs_stage['kpis'] ?? [] as $kpi) {
                        $kpi_id = $kpi['id'] ?? '';
                        $current = $kpi_responses[$kpi_id] ?? null;
                        $level = is_array($current) ? ($current['value'] ?? $current['level'] ?? null) : $current;
                        $kpi['_current_level'] = $level ?: 'unanswered';
                        $kpi['_meets'] = $level ? meets_level_v23($level, 'developing') : false;
                        $kpi['_source_stage_id'] = $gs;
                        $kpi['_source_stage_name'] = $gs_stage['name'] ?? ('Stage ' . $gs);
                        $all_stage_kpis[] = $kpi;
                        if (!$kpi['_meets']) {
                            $blocking_kpis[] = $kpi;
                        }
                    }
                }
                if (!empty($blocking_kpis)) {
                    $blocking_wall = $wall;
                }
            }
        }

        // Build content-by-belief lookup: itemId → array of catalog items
        $content_by_belief = [];
        $catalog = get_content_catalog();
        $catalog_items = $catalog['contentCatalog'] ?? ($catalog['items'] ?? $catalog);
        if (is_array($catalog_items)) {
            foreach ($catalog_items as $ci) {
                foreach ((array)($ci['itemRefs'] ?? []) as $ref) {
                    $content_by_belief[$ref][] = $ci;
                }
            }
        }

        $advancement_blockers = [
            'stage_name' => $stage_data['name'] ?? 'Stage ' . $current_stage_id,
            'overall_score' => $overall,
            'threshold' => $threshold,
            'stage_complete' => $stage_complete,
            'unresolved_items' => $unresolved_items,
            'not_yet_assessed' => $not_yet_assessed,
            'blocking_wall' => $blocking_wall,
            'blocking_kpis' => $blocking_kpis,
            'all_stage_kpis' => $all_stage_kpis,
            'content_by_belief' => $content_by_belief,
        ];
    }
}

$quote = get_contextual_quote($current_stage_id);
$coaches = get_user_coaches($uid, $sid);
$coach_count = count($coaches);
$profile = get_user_profile($uid, $sid);
$first_name = $profile['first_name'] ?? $_SESSION['first_name'] ?? '';
$onboarding = get_onboarding_profile($uid, $sid);

// Auto-recompute when framework version has changed since last scoring
if ($instance && $scores) {
    $inst_data_check = $instance['instance_data'];
    $last_fw_version = $inst_data_check['_lastFrameworkVersion'] ?? null;
    $current_fw_version = null;
    $header = function_exists('get_framework_header') ? get_framework_header() : null;
    if ($header) {
        $current_fw_version = ($header['frameworkVersion'] ?? '') . '_' . ($header['dateUpdated'] ?? '');
    }
    if ($current_fw_version && $last_fw_version !== $current_fw_version) {
        // Framework updated — silently re-run inference + scoring
        $resp = (array)($inst_data_check['responses'] ?? []);
        $meta = (array)($inst_data_check['responseMeta'] ?? []);
        apply_inference_pass($resp, $meta);
        $inst_data_check['responses'] = $resp;
        $inst_data_check['responseMeta'] = $meta;
        $inst_data_check['scores'] = compute_all_stage_scores($resp);
        $inst_data_check['placement'] = compute_placement($inst_data_check);
        $inst_data_check['hardWallStatus'] = compute_hard_wall_status($inst_data_check['kpiResponses'] ?? []);
        $inst_data_check['_lastFrameworkVersion'] = $current_fw_version;
        update_instance_json($uid, $sid, 'ai-assessment', $inst_data_check);

        // Refresh local variables so dashboard renders updated data
        $instance = get_user_instance($uid, $sid, 'ai-assessment');
        $placement = $instance['instance_data']['placement'] ?? null;
        $all_scores = $instance['instance_data']['scores'] ?? null;
        $current_stage_id = $placement['placedStage'] ?? ($placement['currentStage'] ?? null);
        $scores = null;
        if ($all_scores && is_array($all_scores)) {
            $total_m = 0; $total_t = 0; $total_s = 0; $count = 0;
            foreach ($all_scores as $sk => $ss) {
                if (!is_array($ss)) continue;
                $m = $ss['mindset'] ?? 0; $t = $ss['toolset'] ?? 0; $s = $ss['skillset'] ?? 0;
                if ($m > 0 || $t > 0 || $s > 0) {
                    $total_m = max($total_m, $m); $total_t = max($total_t, $t); $total_s = max($total_s, $s); $count++;
                }
            }
            if ($count > 0) $scores = ['mindset' => $total_m, 'toolset' => $total_t, 'skillset' => $total_s];
        }
        error_log("ElevateHER: Auto-recomputed scores for user $uid (framework $last_fw_version → $current_fw_version)");
    }
}

// Seed initial progress snapshot if we have scores but no snapshots exist yet
if ($instance && $scores && function_exists('get_user_progress_timeline')) {
    // One-time cleanup: purge stale snapshots where all scores are zero (bug fix)
    $existing_snaps = get_user_progress_timeline($uid, $sid);
    if (!empty($existing_snaps)) {
        $all_zero = true;
        foreach ($existing_snaps as $snap) {
            if (($snap['overall_score'] ?? 0) > 0 || ($snap['mindset_score'] ?? 0) > 0
                || ($snap['toolset_score'] ?? 0) > 0 || ($snap['skillset_score'] ?? 0) > 0) {
                $all_zero = false;
                break;
            }
        }
        if ($all_zero) {
            child_db_query_shard_prepared($sid,
                "DELETE FROM progress_snapshots WHERE user_id = ? AND overall_score = 0 AND mindset_score = 0 AND toolset_score = 0 AND skillset_score = 0",
                [$uid]
            );
            $existing_snaps = []; // force re-seed below
        }
    }
    if (empty($existing_snaps) && function_exists('capture_progress_snapshot')) {
        capture_progress_snapshot($uid, $sid, 'assessment');
    }
}
?>

<?php if (!$instance || ($instance['status'] ?? 'not_started') === 'not_started') { ?>
<div class="alert alert-info">
    <i class="bi bi-flower1 me-2"></i>
    <strong>Welcome to ElevateHER!</strong> Start your assessment — your AI coach will get to know you and guide you through the Training Continuum.
    <a href="index.php?page=assessment" class="alert-link ms-2">Start Assessment <i class="bi bi-arrow-right"></i></a>
</div>
<?php } ?>

<div class="row g-4">
    <!-- Welcome Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Welcome back, <?= h($first_name) ?>!</h4>
                <p class="text-muted mb-0">Your ElevateHER Learning Suite dashboard</p>
            </div>
        </div>
    </div>

    <!-- Assessment Status -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Assessment Status</h6>
                <div class="d-flex gap-2">
                    <?php if ($instance) { ?>
                    <button type="button" class="btn btn-sm btn-outline-info" id="recomputeBtn" onclick="recomputeScores(this)">
                        <i class="bi bi-calculator me-1"></i>Recompute
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetAssessmentModal">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear &amp; Restart
                    </button>
                    <?php } ?>
                    <?php if ($instance && ($instance['status'] ?? '') === 'completed') { ?>
                    <a href="index.php?page=results" class="btn btn-sm btn-outline-primary">View Results</a>
                    <?php } else { ?>
                    <a href="index.php?page=assessment" class="btn btn-sm btn-primary">
                        <?= $instance && ($instance['status'] ?? '') === 'in_progress' ? 'Continue Assessment' : 'Start Assessment' ?>
                    </a>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($placement) { ?>
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <div class="display-4 fw-bold text-primary"><?= h($current_stage_id ?? '') ?></div>
                        <p class="text-muted mb-0"><?= h($placement['stageName'] ?? $placement['placedStage'] ?? '') ?></p>
                        <?php if (!empty($placement['hardWallBlocking'])) { ?>
                        <span class="badge bg-warning text-dark mt-2"><i class="bi bi-exclamation-triangle me-1"></i>At a Growth Wall</span>
                        <?php } ?>
                    </div>
                    <div class="col-md-8">
                        <?php if ($scores) { ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>Mindset</span>
                                <span><?= round(($scores['mindset'] ?? 0) * 100) ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: <?= round(($scores['mindset'] ?? 0) * 100) ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>Toolset</span>
                                <span><?= round(($scores['toolset'] ?? 0) * 100) ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= round(($scores['toolset'] ?? 0) * 100) ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>Skillset</span>
                                <span><?= round(($scores['skillset'] ?? 0) * 100) ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?= round(($scores['skillset'] ?? 0) * 100) ?>%"></div>
                            </div>
                        </div>
                        <?php } else { ?>
                        <p class="text-muted">Complete your assessment to see dimension scores.</p>
                        <?php } ?>
                    </div>
                </div>
                <?php } else { ?>
                <div class="text-center py-4">
                    <i class="bi bi-flower1 display-4 text-muted"></i>
                    <p class="text-muted mt-2">Start your assessment to discover your current stage in the Training Continuum.</p>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Stats</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Coaches</span>
                    <span class="badge bg-primary"><?= $coach_count ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Assessment</span>
                    <span class="badge bg-<?= ($instance && ($instance['status'] ?? '') === 'completed') ? 'success' : (($instance && ($instance['status'] ?? '') === 'in_progress') ? 'warning' : 'secondary') ?>">
                        <?= ucfirst(str_replace('_', ' ', $instance ? ($instance['status'] ?? 'not started') : 'not started')) ?>
                    </span>
                </div>
                <?php if (in_array('coach', $roles)) { ?>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Role</span>
                    <span class="badge bg-info">Coach</span>
                </div>
                <?php } ?>
                <?php if (in_array('org_lead', $roles)) { ?>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Role</span>
                    <span class="badge bg-danger">Org Lead</span>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Stage Advancement Diagnostic -->
    <?php if ($advancement_blockers && (!$advancement_blockers['stage_complete'] || !empty($advancement_blockers['blocking_kpis']) || !empty($advancement_blockers['unresolved_items']) || !empty($advancement_blockers['not_yet_assessed']))) { ?>
    <div class="col-12">
        <div class="card border-start border-warning border-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-signpost-split me-2"></i>Current Assessment</h6>
                <span class="badge bg-warning text-dark">Stage <?= h($current_stage_id) ?> &mdash; <?= h($advancement_blockers['stage_name']) ?></span>
            </div>
            <div class="card-body">
                <?php if ($advancement_blockers['stage_complete']) { ?>
                <!-- Stage is complete but KPIs are blocking -->
                <div class="alert alert-info py-2 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Stage diagnostic items are <strong>100% complete</strong> — answer the Growth Wall KPIs below to unlock the next stage.</span>
                </div>
                <?php } else { ?>
                <!-- Stage completion progress -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Stage Completion</span>
                        <span><?= round($advancement_blockers['overall_score'] * 100) ?>% / <?= round($advancement_blockers['threshold'] * 100) ?>% needed</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?= ($advancement_blockers['overall_score'] >= $advancement_blockers['threshold']) ? 'bg-success' : 'bg-warning' ?>"
                             style="width: <?= min(100, round($advancement_blockers['overall_score'] / $advancement_blockers['threshold'] * 100)) ?>%"></div>
                    </div>
                </div>
                <?php } ?>

                <?php
                    $belief_content = $advancement_blockers['content_by_belief'] ?? [];
                    $needs_work = $advancement_blockers['unresolved_items'] ?? [];
                    $not_assessed = $advancement_blockers['not_yet_assessed'] ?? [];

                    // Group both lists by dimension
                    $dim_order = ['mindset' => ['label' => 'Mindset', 'color' => 'info', 'icon' => 'bi-lightbulb'],
                                  'toolset' => ['label' => 'Toolset', 'color' => 'success', 'icon' => 'bi-tools'],
                                  'skillset' => ['label' => 'Skillset', 'color' => 'warning', 'icon' => 'bi-bullseye']];
                    $needs_by_dim = [];
                    foreach ($needs_work as $item) { $needs_by_dim[$item['dimension'] ?? 'other'][] = $item; }
                    $unassessed_by_dim = [];
                    foreach ($not_assessed as $item) { $unassessed_by_dim[$item['dimension'] ?? 'other'][] = $item; }

                    // Collect unique content already shown so we don't duplicate across items
                    $shown_content_ids = [];
                ?>

                <?php if (!empty($needs_work)) { ?>
                <div class="mb-3">
                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-x-circle me-1 text-danger"></i>Needs Work (<?= count($needs_work) ?>)</h6>
                    <p class="small text-muted mb-2" style="font-size:0.78rem;">You've been assessed on these and they aren't true for you yet. Is this still accurate? You can update below, or explore the recommended content to develop in each area.</p>

                    <?php foreach ($dim_order as $dim_key => $dim_meta) {
                        $dim_items = $needs_by_dim[$dim_key] ?? [];
                        if (empty($dim_items)) continue;

                        // Collect unique content for this dimension group
                        $dim_content = [];
                        foreach ($dim_items as $di) {
                            foreach ($belief_content[$di['id'] ?? ''] ?? [] as $ci) {
                                $cid = $ci['id'] ?? '';
                                if (!isset($dim_content[$cid])) { $dim_content[$cid] = $ci; }
                            }
                        }
                    ?>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                            <span class="badge bg-<?= $dim_meta['color'] ?>"><?= $dim_meta['label'] ?></span>
                            <span class="small text-muted"><?= count($dim_items) ?> item<?= count($dim_items) !== 1 ? 's' : '' ?></span>
                        </div>

                        <div class="list-group list-group-flush small">
                            <?php foreach ($dim_items as $item) {
                                $iid = $item['id'] ?? '';
                            ?>
                            <div class="list-group-item bg-transparent px-2 py-2" id="belief_<?= h($iid) ?>">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="flex-grow-1">
                                        <div>
                                            <?= h($item['label'] ?? $iid) ?>
                                            <?php if (!empty($item['_blocking_nonNeg'])) { ?>
                                            <span class="badge bg-danger ms-1" style="font-size:0.6rem;">must resolve</span>
                                            <?php } ?>
                                        </div>
                                        <?php if (!empty($item['sub'])) { ?>
                                        <div class="text-muted mt-1" style="font-size:0.75rem; line-height:1.3;"><?= h($item['sub']) ?></div>
                                        <?php } ?>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0 align-items-center belief-buttons" data-item="<?= h($iid) ?>">
                                        <span class="text-muted me-1" style="font-size:0.65rem;">True for me?</span>
                                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:0.7rem;line-height:1.6;" onclick="saveBeliefResponse('<?= h($iid) ?>', true, this)">Yes</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.7rem;line-height:1.6;" onclick="saveBeliefResponse('<?= h($iid) ?>', false, this)">No</button>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                        <?php if (!empty($dim_content)) { ?>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <?php foreach ($dim_content as $ci) {
                                $ci_id = $ci['id'] ?? '';
                                $ci_thumb = get_content_thumbnail_url($ci);
                                $ci_type = $ci['type'] ?? '';
                                $ci_duration = $ci['estimatedDuration'] ?? '';
                                $type_icon = 'bi-journal-richtext';
                                if ($ci_type === 'short-course') $type_icon = 'bi-mortarboard';
                                elseif ($ci_type === 'worksheet') $type_icon = 'bi-file-earmark-text';
                                elseif ($ci_type === 'video') $type_icon = 'bi-play-circle';
                                elseif ($ci_type === 'article') $type_icon = 'bi-file-text';
                            ?>
                            <a href="index.php?page=content-detail&id=<?= urlencode($ci_id) ?>" data-no-spa
                               class="text-decoration-none d-flex align-items-center gap-2 rounded px-2 py-1"
                               style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); max-width:300px; transition:background 0.15s;"
                               onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                                <?php if ($ci_thumb) { ?>
                                <img src="<?= h($ci_thumb) ?>" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:3px;flex-shrink:0;">
                                <?php } else { ?>
                                <div class="d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0;background:rgba(255,255,255,0.04);border-radius:3px;">
                                    <i class="bi <?= $type_icon ?>" style="color:rgba(255,255,255,0.3);"></i>
                                </div>
                                <?php } ?>
                                <div style="min-width:0;">
                                    <div class="text-truncate" style="font-size:0.75rem;color:rgba(100,175,220,0.9);font-weight:500;"><?= h($ci['title'] ?? '') ?></div>
                                    <div style="font-size:0.65rem;color:rgba(255,255,255,0.4);"><i class="bi <?= $type_icon ?> me-1"></i><?= h($ci_type) ?><?= $ci_duration ? ' &middot; ' . h($ci_duration) : '' ?></div>
                                </div>
                            </a>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if (!empty($not_assessed)) { ?>
                <div class="mb-3">
                    <h6 class="small fw-bold text-muted mb-2">
                        <a class="text-muted text-decoration-none d-flex align-items-center gap-1" data-bs-toggle="collapse" href="#notYetAssessed" role="button" aria-expanded="false">
                            <i class="bi bi-clock-history me-1"></i>Not Yet Assessed (<?= count($not_assessed) ?>)
                            <i class="bi bi-chevron-down ms-auto" style="font-size:0.7rem;"></i>
                        </a>
                    </h6>
                    <p class="small text-muted mb-2" style="font-size:0.78rem;">These items haven't come up in your assessment yet. You can self-assess below, or continue the conversation to work through them naturally.</p>
                    <div class="collapse" id="notYetAssessed">
                        <?php foreach ($dim_order as $dim_key => $dim_meta) {
                            $dim_items = $unassessed_by_dim[$dim_key] ?? [];
                            if (empty($dim_items)) continue;

                            // Collect content for this dimension's unassessed items
                            $dim_ua_content = [];
                            foreach ($dim_items as $di) {
                                foreach ($belief_content[$di['id'] ?? ''] ?? [] as $ci) {
                                    $cid = $ci['id'] ?? '';
                                    if (!isset($dim_ua_content[$cid])) { $dim_ua_content[$cid] = $ci; }
                                }
                            }
                        ?>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-1 pb-1 border-bottom border-secondary border-opacity-25">
                                <span class="badge bg-<?= $dim_meta['color'] ?> bg-opacity-50" style="font-size:0.6rem;"><?= $dim_meta['label'] ?></span>
                                <span class="small text-muted" style="font-size:0.7rem;"><?= count($dim_items) ?> item<?= count($dim_items) !== 1 ? 's' : '' ?></span>
                            </div>
                            <div class="list-group list-group-flush small">
                                <?php foreach ($dim_items as $item) {
                                    $iid = $item['id'] ?? '';
                                ?>
                                <div class="list-group-item bg-transparent px-2 py-2" id="belief_<?= h($iid) ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="flex-grow-1">
                                            <div style="font-size:0.8rem;"><?= h($item['label'] ?? $iid) ?></div>
                                            <?php if (!empty($item['sub'])) { ?>
                                            <div class="text-muted mt-1" style="font-size:0.72rem; line-height:1.3;"><?= h($item['sub']) ?></div>
                                            <?php } ?>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0 align-items-center belief-buttons" data-item="<?= h($iid) ?>">
                                            <span class="text-muted me-1" style="font-size:0.65rem;">True?</span>
                                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:0.7rem;line-height:1.6;" onclick="saveBeliefResponse('<?= h($iid) ?>', true, this)">Yes</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.7rem;line-height:1.6;" onclick="saveBeliefResponse('<?= h($iid) ?>', false, this)">No</button>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>

                            <?php if (!empty($dim_ua_content)) { ?>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                <?php foreach ($dim_ua_content as $ci) {
                                    $ci_id = $ci['id'] ?? '';
                                    $ci_thumb = get_content_thumbnail_url($ci);
                                    $ci_type = $ci['type'] ?? '';
                                    $ci_duration = $ci['estimatedDuration'] ?? '';
                                    $type_icon = 'bi-journal-richtext';
                                    if ($ci_type === 'short-course') $type_icon = 'bi-mortarboard';
                                    elseif ($ci_type === 'worksheet') $type_icon = 'bi-file-earmark-text';
                                    elseif ($ci_type === 'video') $type_icon = 'bi-play-circle';
                                    elseif ($ci_type === 'article') $type_icon = 'bi-file-text';
                                ?>
                                <a href="index.php?page=content-detail&id=<?= urlencode($ci_id) ?>" data-no-spa
                                   class="text-decoration-none d-flex align-items-center gap-2 rounded px-2 py-1"
                                   style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); max-width:300px; transition:background 0.15s;"
                                   onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                                    <?php if ($ci_thumb) { ?>
                                    <img src="<?= h($ci_thumb) ?>" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:3px;flex-shrink:0;">
                                    <?php } else { ?>
                                    <div class="d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0;background:rgba(255,255,255,0.04);border-radius:3px;">
                                        <i class="bi <?= $type_icon ?>" style="color:rgba(255,255,255,0.3);"></i>
                                    </div>
                                    <?php } ?>
                                    <div style="min-width:0;">
                                        <div class="text-truncate" style="font-size:0.75rem;color:rgba(100,175,220,0.9);font-weight:500;"><?= h($ci['title'] ?? '') ?></div>
                                        <div style="font-size:0.65rem;color:rgba(255,255,255,0.4);"><i class="bi <?= $type_icon ?> me-1"></i><?= h($ci_type) ?><?= $ci_duration ? ' &middot; ' . h($ci_duration) : '' ?></div>
                                    </div>
                                </a>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <?php if (!empty($advancement_blockers['all_stage_kpis'])) {
                    // Group KPIs by source stage
                    $kpis_by_stage = [];
                    foreach ($advancement_blockers['all_stage_kpis'] as $kpi) {
                        $sid = $kpi['_source_stage_id'] ?? 'unknown';
                        if (!isset($kpis_by_stage[$sid])) {
                            $kpis_by_stage[$sid] = [
                                'name' => $kpi['_source_stage_name'] ?? ('Stage ' . $sid),
                                'kpis' => [],
                            ];
                        }
                        $kpis_by_stage[$sid]['kpis'][] = $kpi;
                    }
                ?>
                <div class="mb-0">
                    <h6 class="small fw-bold text-muted mb-2">
                        <i class="bi bi-shield-lock me-1"></i>Growth Wall KPIs
                        <?php if ($advancement_blockers['blocking_wall']) { ?>
                        <span class="text-warning ms-1">&mdash; <?= h($advancement_blockers['blocking_wall']['label'] ?? '') ?></span>
                        <?php } ?>
                    </h6>
                    <?php if ($advancement_blockers['blocking_wall']) { ?>
                    <p class="small text-muted mb-2"><?= h($advancement_blockers['blocking_wall']['prescriptionSummary'] ?? '') ?></p>
                    <?php } ?>

                    <?php foreach ($kpis_by_stage as $stage_id => $stage_group) { ?>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                            <span class="badge bg-primary bg-opacity-75">Stage <?= h($stage_id) ?></span>
                            <span class="small fw-semibold"><?= h($stage_group['name']) ?></span>
                        </div>

                        <?php foreach ($stage_group['kpis'] as $kpi) {
                            $kpi_id = $kpi['id'] ?? '';
                            $cur = $kpi['_current_level'] ?? 'unanswered';
                            $meets = $kpi['_meets'] ?? false;
                        ?>
                        <div class="card bg-transparent border mb-3" id="kpiCard_<?= h($kpi_id) ?>">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold"><?= h($kpi['metric'] ?? $kpi_id) ?></span>
                                    <span class="badge bg-<?= $meets ? 'success' : ($cur === 'unanswered' ? 'secondary' : 'danger') ?>">
                                        <?= h(ucfirst($cur)) ?>
                                    </span>
                                </div>
                                <p class="small text-muted mb-2" style="font-size:0.8rem; line-height:1.5;">
                                    <i class="bi bi-chat-square-quote me-1"></i><em>Be honest with yourself — read each level below and pick the one that most accurately describes where you are right now, not where you want to be.</em>
                                </p>
                                <?php foreach (['beginner', 'developing', 'mastery'] as $lvl) {
                                    $lvl_color = $lvl === 'beginner' ? 'danger' : ($lvl === 'developing' ? 'warning' : 'success');
                                    $is_current = ($cur === $lvl);
                                ?>
                                <div class="rounded mb-2 p-0 <?= $is_current ? 'border border-2 border-' . $lvl_color : 'border border-secondary border-opacity-25' ?>"
                                     style="cursor:pointer; <?= $is_current ? 'background:rgba(var(--bs-' . $lvl_color . '-rgb),0.12);' : 'opacity:0.8;' ?>"
                                     onclick="<?= !$is_current ? "saveKpiLevel('" . h($kpi_id) . "', '" . $lvl . "', this)" : '' ?>">
                                    <div class="d-flex align-items-stretch">
                                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 px-3 py-2 text-white fw-bold"
                                             style="min-width:110px; background:var(--bs-<?= $lvl_color ?>); font-size:0.8rem; letter-spacing:0.02em;<?= !$is_current ? 'opacity:0.7;' : '' ?>">
                                            <?php if ($is_current) { ?><i class="bi bi-check-circle-fill me-1"></i><?php } ?>
                                            <?= ucfirst($lvl) ?>
                                        </div>
                                        <div class="px-3 py-2 small" style="line-height:1.5;">
                                            <?= h($kpi[$lvl] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Progress Over Time -->
    <?php if ($instance && $scores) { ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Progress Over Time</h6>
                <div class="d-flex gap-2 align-items-center">
                    <small class="text-muted" id="progressChartStatus">Loading...</small>
                    <select id="progressChartRange" class="form-select form-select-sm" style="width:auto;">
                        <option value="30">Last 30 days</option>
                        <option value="90" selected>Last 90 days</option>
                        <option value="365">Last year</option>
                        <option value="0">All time</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div id="progressChart" style="width:100%;min-height:300px;position:relative;"></div>
                <div id="progressChartEmpty" class="text-center py-4 d-none">
                    <i class="bi bi-bar-chart-line display-4 text-muted"></i>
                    <p class="text-muted mt-2">Progress data will appear here after your first assessment snapshot.</p>
                </div>
                <!-- Growth summary row -->
                <div id="progressGrowth" class="row g-3 mt-2 d-none">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="small text-muted">30-Day Mindset</div>
                            <div class="fw-bold" id="growthMindset">—</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="small text-muted">30-Day Toolset</div>
                            <div class="fw-bold" id="growthToolset">—</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="small text-muted">30-Day Skillset</div>
                            <div class="fw-bold" id="growthSkillset">—</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="small text-muted">Items Resolved</div>
                            <div class="fw-bold" id="growthItems">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Quote -->
    <?php if ($quote && !empty($quote['text'] ?? $quote['quote'] ?? '')) { ?>
    <div class="col-12">
        <div class="card border-start border-primary border-3">
            <div class="card-body">
                <blockquote class="blockquote mb-0">
                    <p class="mb-1">"<?= h($quote['text'] ?? $quote['quote'] ?? '') ?>"</p>
                    <footer class="blockquote-footer"><?= h($quote['author'] ?? $quote['expertName'] ?? 'Unknown') ?></footer>
                </blockquote>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<?php if ($instance && $scores) { ?>
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
(function() {
    'use strict';

    var chartEl = document.getElementById('progressChart');
    var emptyEl = document.getElementById('progressChartEmpty');
    var statusEl = document.getElementById('progressChartStatus');
    var growthEl = document.getElementById('progressGrowth');
    var rangeSelect = document.getElementById('progressChartRange');
    if (!chartEl) return;

    var DIM_COLORS = {
        mindset:  '#b388d9',
        toolset:  '#6ca6d9',
        skillset: '#6bc96b',
        overall:  '#e0e0e0'
    };

    function loadChart(days) {
        statusEl.textContent = 'Loading...';
        var url = '../api/index.php?action=my_progress_timeline';
        if (days > 0) {
            var from = new Date();
            from.setDate(from.getDate() - days);
            url += '&from=' + from.toISOString().slice(0, 19).replace('T', ' ');
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.results) { showEmpty(); return; }
                var timeline = data.results.timeline || [];
                var growth = data.results.growth || {};

                if (timeline.length === 0) { showEmpty(); return; }

                // If only one data point, duplicate it so we get a visible line
                if (timeline.length === 1) {
                    var dup = Object.assign({}, timeline[0]);
                    var d = new Date(dup.date);
                    d.setDate(d.getDate() - 1);
                    dup.date = d.toISOString().slice(0, 19).replace('T', ' ');
                    timeline.unshift(dup);
                }

                renderChart(timeline);
                renderGrowth(growth);
                statusEl.textContent = timeline.length + ' snapshot' + (timeline.length !== 1 ? 's' : '');
            })
            .catch(function(err) {
                console.error('[dashboard] Progress chart error:', err);
                showEmpty();
            });
    }

    function showEmpty() {
        chartEl.style.display = 'none';
        emptyEl.classList.remove('d-none');
        statusEl.textContent = '';
    }

    function renderGrowth(g) {
        if (!g) return;
        growthEl.classList.remove('d-none');

        function fmt(val) {
            if (!val && val !== 0) return '—';
            var pct = Math.round(val * 100);
            var sign = pct > 0 ? '+' : '';
            var color = pct > 0 ? 'text-success' : (pct < 0 ? 'text-danger' : 'text-muted');
            return '<span class="' + color + '">' + sign + pct + '%</span>';
        }

        document.getElementById('growthMindset').innerHTML = fmt(g.mindset_delta);
        document.getElementById('growthToolset').innerHTML = fmt(g.toolset_delta);
        document.getElementById('growthSkillset').innerHTML = fmt(g.skillset_delta);
        var itemsDelta = g.items_delta || 0;
        var sign = itemsDelta > 0 ? '+' : '';
        var color = itemsDelta > 0 ? 'text-success' : (itemsDelta < 0 ? 'text-danger' : 'text-muted');
        document.getElementById('growthItems').innerHTML = '<span class="' + color + '">' + sign + itemsDelta + '</span>';
    }

    function renderChart(timeline) {
        chartEl.innerHTML = '';
        emptyEl.classList.add('d-none');
        chartEl.style.display = '';

        var rect = chartEl.getBoundingClientRect();
        var margin = { top: 20, right: 30, bottom: 40, left: 50 };
        var width = (rect.width || 700) - margin.left - margin.right;
        var height = 260 - margin.top - margin.bottom;

        var svg = d3.select(chartEl)
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

        // Parse dates
        var parseDate = function(d) { return new Date(d.replace(' ', 'T')); };
        timeline.forEach(function(d) {
            d._date = parseDate(d.date);
        });

        // Scales
        var x = d3.scaleTime()
            .domain(d3.extent(timeline, function(d) { return d._date; }))
            .range([0, width]);

        var y = d3.scaleLinear()
            .domain([0, 1])
            .range([height, 0]);

        // Grid lines
        svg.append('g')
            .attr('class', 'grid')
            .call(d3.axisLeft(y)
                .ticks(5)
                .tickSize(-width)
                .tickFormat('')
            )
            .selectAll('line')
            .attr('stroke', 'var(--bs-border-color, #dee2e6)')
            .attr('stroke-opacity', 0.3);

        svg.selectAll('.grid .domain').remove();

        // Axes
        var xAxis = svg.append('g')
            .attr('transform', 'translate(0,' + height + ')')
            .call(d3.axisBottom(x).ticks(Math.min(timeline.length, 6)).tickFormat(d3.timeFormat('%b %d')));
        xAxis.selectAll('text').attr('fill', 'var(--bs-body-color, #adb5bd)').style('font-size', '11px');
        xAxis.selectAll('line, .domain').attr('stroke', 'var(--bs-border-color, #dee2e6)');

        var yAxis = svg.append('g')
            .call(d3.axisLeft(y).ticks(5).tickFormat(function(d) { return Math.round(d * 100) + '%'; }));
        yAxis.selectAll('text').attr('fill', 'var(--bs-body-color, #adb5bd)').style('font-size', '11px');
        yAxis.selectAll('line, .domain').attr('stroke', 'var(--bs-border-color, #dee2e6)');

        // Line generator
        var line = d3.line()
            .x(function(d) { return x(d._date); })
            .y(function(d) { return y(d.value); })
            .curve(d3.curveMonotoneX);

        // Draw each dimension line
        var dims = ['mindset', 'toolset', 'skillset'];
        dims.forEach(function(dim) {
            var series = timeline.map(function(d) {
                return { _date: d._date, value: d[dim] || 0 };
            });

            // Line
            svg.append('path')
                .datum(series)
                .attr('fill', 'none')
                .attr('stroke', DIM_COLORS[dim])
                .attr('stroke-width', 2.5)
                .attr('d', line);

            // Dots
            svg.selectAll('.dot-' + dim)
                .data(series)
                .enter()
                .append('circle')
                .attr('cx', function(d) { return x(d._date); })
                .attr('cy', function(d) { return y(d.value); })
                .attr('r', 4)
                .attr('fill', DIM_COLORS[dim])
                .attr('stroke', 'var(--bs-body-bg, #1a1a2e)')
                .attr('stroke-width', 2);
        });

        // Stage markers — show stage changes as vertical dashed lines
        var prevStage = null;
        timeline.forEach(function(d) {
            if (d.stage !== prevStage && prevStage !== null) {
                svg.append('line')
                    .attr('x1', x(d._date))
                    .attr('x2', x(d._date))
                    .attr('y1', 0)
                    .attr('y2', height)
                    .attr('stroke', '#e07878')
                    .attr('stroke-width', 1)
                    .attr('stroke-dasharray', '4,4')
                    .attr('opacity', 0.6);

                svg.append('text')
                    .attr('x', x(d._date) + 4)
                    .attr('y', 12)
                    .attr('fill', '#e07878')
                    .style('font-size', '10px')
                    .text('Stage ' + d.stage);
            }
            prevStage = d.stage;
        });

        // Legend
        var legend = svg.append('g')
            .attr('transform', 'translate(' + (width - 200) + ', 0)');

        dims.forEach(function(dim, i) {
            var g = legend.append('g')
                .attr('transform', 'translate(' + (i * 70) + ', 0)');
            g.append('rect')
                .attr('width', 12).attr('height', 12)
                .attr('rx', 2)
                .attr('fill', DIM_COLORS[dim]);
            g.append('text')
                .attr('x', 16).attr('y', 10)
                .attr('fill', 'var(--bs-body-color, #adb5bd)')
                .style('font-size', '11px')
                .text(dim.charAt(0).toUpperCase() + dim.slice(1));
        });

        // Tooltip overlay
        var bisect = d3.bisector(function(d) { return d._date; }).left;

        var focus = svg.append('g').style('display', 'none');
        focus.append('line')
            .attr('class', 'focus-line')
            .attr('y1', 0).attr('y2', height)
            .attr('stroke', 'var(--bs-body-color, #adb5bd)')
            .attr('stroke-width', 1)
            .attr('stroke-dasharray', '3,3')
            .attr('opacity', 0.5);

        var tooltip = d3.select(chartEl)
            .append('div')
            .style('position', 'absolute')
            .style('pointer-events', 'none')
            .style('background', 'var(--bs-body-bg, #1a1a2e)')
            .style('border', '1px solid var(--bs-border-color, #dee2e6)')
            .style('border-radius', '6px')
            .style('padding', '8px 12px')
            .style('font-size', '12px')
            .style('color', 'var(--bs-body-color, #adb5bd)')
            .style('display', 'none')
            .style('z-index', '10');

        svg.append('rect')
            .attr('width', width).attr('height', height)
            .attr('fill', 'none')
            .attr('pointer-events', 'all')
            .on('mousemove', function(event) {
                var mx = d3.pointer(event)[0];
                var date = x.invert(mx);
                var idx = bisect(timeline, date, 1);
                var d0 = timeline[idx - 1];
                var d1 = timeline[idx] || d0;
                var d = (date - d0._date > d1._date - date) ? d1 : d0;

                focus.style('display', null);
                focus.select('.focus-line')
                    .attr('x1', x(d._date)).attr('x2', x(d._date));

                var dateStr = d3.timeFormat('%b %d, %Y')(d._date);
                tooltip.style('display', 'block')
                    .style('left', (x(d._date) + margin.left + 10) + 'px')
                    .style('top', margin.top + 'px')
                    .html(
                        '<strong>' + dateStr + '</strong> · Stage ' + d.stage + '<br>' +
                        '<span style="color:' + DIM_COLORS.mindset + '">● Mindset: ' + Math.round(d.mindset * 100) + '%</span><br>' +
                        '<span style="color:' + DIM_COLORS.toolset + '">● Toolset: ' + Math.round(d.toolset * 100) + '%</span><br>' +
                        '<span style="color:' + DIM_COLORS.skillset + '">● Skillset: ' + Math.round(d.skillset * 100) + '%</span>'
                    );
            })
            .on('mouseleave', function() {
                focus.style('display', 'none');
                tooltip.style('display', 'none');
            });
    }

    // Range selector
    rangeSelect.addEventListener('change', function() {
        loadChart(parseInt(this.value, 10));
    });

    // Initial load
    loadChart(90);

    // Reload on SPA navigation
    document.addEventListener('spa:contentLoaded', function() {
        if (document.getElementById('progressChart')) {
            loadChart(parseInt(rangeSelect.value, 10));
        }
    });
})();
</script>
<?php } ?>

<script>
function recomputeScores(btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Recomputing...';

    apiPost('recompute_scores', {}, function(json) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-calculator me-1"></i>Recompute';
        if (json.error) {
            try { if (typeof showToast === 'function') showToast('danger', json.error); } catch(e) {}
            return;
        }
        try { if (typeof showToast === 'function') showToast('success', json.message || 'Scores recomputed!'); } catch(e) {}
        setTimeout(function() { window.location.reload(); }, 800);
    });
}

function saveKpiLevel(kpiId, level, rowEl) {
    var card = document.getElementById('kpiCard_' + kpiId);
    if (!card) return;

    // Disable all clickable rows in this KPI card
    var rows = card.querySelectorAll('[onclick]');
    rows.forEach(function(r) { r.style.pointerEvents = 'none'; r.style.opacity = '0.5'; });

    // Show spinner in the label block of the clicked row
    var labelBlock = rowEl.querySelector('.flex-shrink-0');
    var originalLabel = labelBlock ? labelBlock.innerHTML : '';
    if (labelBlock) {
        labelBlock.innerHTML = '<span class="spinner-border spinner-border-sm text-white"></span>';
    }

    apiPost('save_kpi_response', {
        kpi_id: kpiId,
        level: level
    }, function(json) {
        if (json.error) {
            try { if (typeof showToast === 'function') showToast('danger', json.error); } catch(e) {}
            // Restore
            rows.forEach(function(r) { r.style.pointerEvents = ''; r.style.opacity = ''; });
            if (labelBlock) labelBlock.innerHTML = originalLabel;
            return;
        }

        try { if (typeof showToast === 'function') showToast('success', json.message || 'KPI updated!'); } catch(e) {}

        // Refresh the page to show updated blockers and placement
        setTimeout(function() { window.location.reload(); }, 800);
    });
}

function saveBeliefResponse(itemId, value, btnEl) {
    var row = document.getElementById('belief_' + itemId);
    if (!row) return;

    // Disable buttons in this row
    var btns = row.querySelectorAll('.belief-buttons button');
    btns.forEach(function(b) { b.disabled = true; b.style.opacity = '0.5'; });

    // Show spinner
    var btnGroup = row.querySelector('.belief-buttons');
    var originalHTML = btnGroup.innerHTML;
    btnGroup.innerHTML = '<span class="spinner-border spinner-border-sm text-muted"></span>';

    apiPost('save_belief_response', {
        item_id: itemId,
        value: value ? 'true' : 'false'
    }, function(json) {
        if (json.error) {
            try { if (typeof showToast === 'function') showToast('danger', json.error); } catch(e) {}
            btnGroup.innerHTML = originalHTML;
            return;
        }

        // Show confirmation briefly
        if (value) {
            btnGroup.innerHTML = '<span style="font-size:0.75rem;color:#6bc96b;"><i class="bi bi-check-circle me-1"></i>Resolved!</span>';
        } else {
            btnGroup.innerHTML = '<span style="font-size:0.75rem;color:rgba(255,255,255,0.5);"><i class="bi bi-check me-1"></i>Saved</span>';
        }

        try { if (typeof showToast === 'function') showToast('success', json.message || 'Updated!'); } catch(e) {}

        // Refresh to show updated scores and lists
        setTimeout(function() { window.location.reload(); }, 900);
    });
}
</script>

<?php if ($instance) { ?>
<div class="modal fade" id="resetAssessmentModal" tabindex="-1" aria-labelledby="resetAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetAssessmentModalLabel"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Clear Assessment Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will permanently delete your current assessment data including:</p>
                <ul>
                    <li>All assessment responses and scores</li>
                    <li>Your stage placement</li>
                    <li>All session history</li>
                </ul>
                <p class="text-danger fw-bold mb-0">This action cannot be undone. You will need to retake the assessment from the beginning.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="assessment_reset">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Clear Data &amp; Restart</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php } ?>
