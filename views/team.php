<?php
/**
 * My Team — Coach's view of linked sellers
 */
$page_title = 'My Team';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];

$relationships = get_coach_sellers($uid, $sid);
$team = [];
foreach ($relationships as $rel) {
    $seller_id = $rel['seller_user_id'];
    $seller_shard = $rel['seller_shard_id'];
    $user = get_user($seller_id);
    $profile = get_user_profile($seller_id, $seller_shard);
    $inst = get_user_instance($seller_id, $seller_shard, 'ai-assessment');
    $d = $inst ? $inst['instance_data'] : null;

    $team[] = [
        'user_id' => $seller_id,
        'shard_id' => $seller_shard,
        'name' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
        'stage' => $d ? ($d['placement']['currentStage'] ?? '?') : '?',
        'stage_name' => $d ? ($d['placement']['stageName'] ?? 'Not started') : 'Not started',
        'mindset' => $d ? round(($d['scores']['mindset_score'] ?? 0) * 100) : 0,
        'toolset' => $d ? round(($d['scores']['toolset_score'] ?? 0) * 100) : 0,
        'skillset' => $d ? round(($d['scores']['skillset_score'] ?? 0) * 100) : 0,
        'hard_wall' => $d && !empty($d['placement']['hardWallBlocking']) ? ($d['placement']['hardWallBlocking']['label'] ?? 'Yes') : null,
        'last_active' => $inst ? $inst['updated_at'] : null,
        'status' => $inst ? $inst['status'] : 'not_started',
    ];
}
$team_json = json_encode($team);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>My Team (<?= count($team) ?> sellers)</h4>
    <a href="index.php?page=invite" class="btn btn-primary"><i class="bi bi-link-45deg me-1"></i>Invite</a>
</div>

<?php if (empty($team)) { ?>
<div class="text-center py-5">
    <i class="bi bi-person-plus display-4 text-muted"></i>
    <p class="text-muted mt-2">No team members yet. Create an invite link to start building your team.</p>
</div>
<?php } else { ?>

<!-- Summary Bar -->
<div class="row g-3 mb-4">
    <?php
    $stage_groups = ['0-2' => 0, '3-5' => 0, '6-8' => 0, '9-10' => 0];
    foreach ($team as $m) {
        $s = (int)$m['stage'];
        if ($s <= 2) $stage_groups['0-2']++;
        elseif ($s <= 5) $stage_groups['3-5']++;
        elseif ($s <= 8) $stage_groups['6-8']++;
        else $stage_groups['9-10']++;
    }
    ?>
    <?php foreach ($stage_groups as $range => $count) { ?>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold"><?= $count ?></div>
                <small class="text-muted">Stage <?= $range ?></small>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card"><div class="card-body"><div id="stageDonut" style="height:280px;"></div></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body"><div id="dimBars" style="height:280px;"></div></div></div></div>
</div>

<!-- Team Members -->
<div class="row g-3">
    <?php foreach ($team as $m) { ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title"><?= h($m['name']) ?></h6>
                <p class="text-muted small mb-2">Stage <?= h($m['stage']) ?>: <?= h($m['stage_name']) ?></p>
                <div class="mb-2">
                    <small>M: <?= $m['mindset'] ?>% | T: <?= $m['toolset'] ?>% | S: <?= $m['skillset'] ?>%</small>
                </div>
                <?php if ($m['hard_wall']) { ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i><?= h($m['hard_wall']) ?></span>
                <?php } ?>
                <?php if ($m['last_active']) { ?>
                <small class="text-muted d-block mt-1">Last active: <?= date('M j', strtotime($m['last_active'])) ?></small>
                <?php } ?>
            </div>
            <div class="card-footer bg-transparent">
                <button class="btn btn-sm btn-outline-primary" onclick="modalView('modal=seller_detail&seller_id=<?= $m['user_id'] ?>&seller_shard_id=<?= h($m['shard_id']) ?>')">Details</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="modalView('modal=coach_note&seller_user_id=<?= $m['user_id'] ?>&seller_shard_id=<?= h($m['shard_id']) ?>')">Add Note</button>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
(function() {
    const team = <?= $team_json ?>;

    // Stage Donut
    const stageCounts = d3.rollup(team, v => v.length, d => d.stage);
    const donutData = Array.from(stageCounts, ([stage, count]) => ({stage, count}));

    if (donutData.length > 0) {
        const w = document.getElementById('stageDonut').clientWidth;
        const h = 260;
        const radius = Math.min(w, h) / 2 - 20;

        const svg = d3.select('#stageDonut').append('svg').attr('width', w).attr('height', h)
            .append('g').attr('transform', `translate(${w/2},${h/2})`);

        const color = d3.scaleOrdinal().domain(donutData.map(d => d.stage))
            .range(d3.quantize(t => d3.interpolateGreens(0.3 + t * 0.6), donutData.length));

        const arc = d3.arc().innerRadius(radius * 0.5).outerRadius(radius);
        const pie = d3.pie().value(d => d.count).sort(null);

        svg.selectAll('path').data(pie(donutData)).join('path')
            .attr('d', arc).attr('fill', d => color(d.data.stage)).attr('stroke', 'var(--bs-body-bg)').attr('stroke-width', 2);

        svg.append('text').attr('text-anchor', 'middle').attr('dy', '0.35em')
            .attr('fill', 'var(--bs-body-color)').attr('font-size', '2em').attr('font-weight', 'bold')
            .text(team.length);
    }

    // Dimension Bars
    const avgM = team.reduce((s, t) => s + t.mindset, 0) / (team.length || 1);
    const avgT = team.reduce((s, t) => s + t.toolset, 0) / (team.length || 1);
    const avgS = team.reduce((s, t) => s + t.skillset, 0) / (team.length || 1);

    const barData = [{dim: 'Mindset', val: avgM, color: '#0dcaf0'}, {dim: 'Toolset', val: avgT, color: '#198754'}, {dim: 'Skillset', val: avgS, color: '#ffc107'}];
    const bw = document.getElementById('dimBars').clientWidth;
    const bh = 260;
    const margin = {top: 20, right: 20, bottom: 40, left: 50};

    const bsvg = d3.select('#dimBars').append('svg').attr('width', bw).attr('height', bh);
    const x = d3.scaleBand().domain(barData.map(d => d.dim)).range([margin.left, bw - margin.right]).padding(0.3);
    const y = d3.scaleLinear().domain([0, 100]).range([bh - margin.bottom, margin.top]);

    bsvg.append('g').attr('transform', `translate(0,${bh - margin.bottom})`).call(d3.axisBottom(x)).selectAll('text').attr('fill', 'var(--bs-body-color)');
    bsvg.append('g').attr('transform', `translate(${margin.left},0)`).call(d3.axisLeft(y).ticks(5)).selectAll('text').attr('fill', 'var(--bs-body-color)');

    bsvg.selectAll('rect').data(barData).join('rect')
        .attr('x', d => x(d.dim)).attr('y', d => y(d.val)).attr('width', x.bandwidth())
        .attr('height', d => y(0) - y(d.val)).attr('fill', d => d.color).attr('rx', 4);

    // 80% threshold line
    bsvg.append('line').attr('x1', margin.left).attr('x2', bw - margin.right)
        .attr('y1', y(80)).attr('y2', y(80)).attr('stroke', 'var(--bs-danger)').attr('stroke-dasharray', '4,4');
})();
</script>
<?php } ?>
