<?php
/**
 * Cohort Dashboard — Organization-wide analytics
 */
$page_title = 'Cohort Dashboard';
$uid = $_SESSION['user_id'];

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Cohort Dashboard</h4>
    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
</div>

<!-- Key Metrics -->
<div class="row g-3 mb-4" id="keyMetrics">
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" id="metricSellers">-</div><small class="text-muted">Active Sellers</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" id="metricAvgStage">-</div><small class="text-muted">Avg Stage</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" id="metricAtWall">-</div><small class="text-muted">At a Wall</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" id="metricEngagement">-</div><small class="text-muted">Content Engaged</small></div></div></div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card"><div class="card-header"><h6 class="mb-0">Stage Distribution</h6></div><div class="card-body"><div id="cohortStageChart" style="height:300px;"></div></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header"><h6 class="mb-0">Hard Wall Analysis</h6></div><div class="card-body"><div id="cohortWallChart" style="height:300px;"></div></div></div></div>
</div>

<!-- By Coach DataTable -->
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">By Coach</h6></div>
    <div class="card-body">
        <table id="coachTable" class="table table-sm table-hover" style="width:100%">
            <thead><tr><th>Coach</th><th>Team Size</th><th>Avg Stage</th><th>Avg Score</th><th>At Wall</th><th>Active (7d)</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Dimension Health -->
<div class="card">
    <div class="card-header"><h6 class="mb-0">Dimension Health</h6></div>
    <div class="card-body"><div id="cohortDimChart" style="height:300px;"></div></div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load cohort summary
    apiPost('cohort_summary', {}, function(resp) {
        if (resp.error) return;
        const r = resp.results || {};
        document.getElementById('metricSellers').textContent = r.total_sellers || 0;
        document.getElementById('metricAvgStage').textContent = r.avg_stage || 0;
        document.getElementById('metricAtWall').textContent = r.at_wall_count || 0;
        document.getElementById('metricEngagement').textContent = (r.content_engagement_rate || 0) + '%';

        // Stage distribution chart
        const dist = r.stage_distribution || {};
        const stageData = Object.entries(dist).map(([k, v]) => ({range: k, count: v}));
        renderHorizontalBars('cohortStageChart', stageData, 'range', 'count', '#198754');

        // Dimension chart
        const dimData = [
            {dim: 'Mindset', val: Math.round((r.avg_mindset || 0) * 100)},
            {dim: 'Toolset', val: Math.round((r.avg_toolset || 0) * 100)},
            {dim: 'Skillset', val: Math.round((r.avg_skillset || 0) * 100)},
        ];
        renderHorizontalBars('cohortDimChart', dimData, 'dim', 'val', '#0dcaf0');
    });

    // Wall distribution
    apiPost('wall_distribution', {}, function(resp) {
        if (resp.error) return;
        const walls = resp.results.walls || {};
        const wallData = Object.entries(walls).map(([k, v]) => ({label: k, count: v}));
        renderHorizontalBars('cohortWallChart', wallData, 'label', 'count', '#ffc107');
    });

    // Coach DataTable
    $('#coachTable').DataTable({
        ajax: {
            url: '../api/index.php',
            type: 'POST',
            data: {action: 'cohort_by_coach'},
            dataSrc: 'results.results'
        },
        columns: [
            {data: 'coach_name'},
            {data: 'team_size'},
            {data: 'avg_stage'},
            {data: 'avg_score', render: function(d) { return Math.round(d * 100) + '%'; }},
            {data: 'at_wall'},
            {data: 'active_7d'},
        ],
        order: [[2, 'desc']],
        pageLength: 25,
    });

    function renderHorizontalBars(containerId, data, labelKey, valKey, color) {
        if (!data.length) return;
        const el = document.getElementById(containerId);
        const w = el.clientWidth, h = el.clientHeight || 280;
        const margin = {top: 10, right: 30, bottom: 30, left: 120};

        const svg = d3.select('#' + containerId).append('svg').attr('width', w).attr('height', h);
        const y = d3.scaleBand().domain(data.map(d => d[labelKey])).range([margin.top, h - margin.bottom]).padding(0.2);
        const x = d3.scaleLinear().domain([0, d3.max(data, d => d[valKey]) || 1]).range([margin.left, w - margin.right]);

        svg.append('g').attr('transform', `translate(${margin.left},0)`).call(d3.axisLeft(y)).selectAll('text').attr('fill', 'var(--bs-body-color)');
        svg.selectAll('rect').data(data).join('rect')
            .attr('x', margin.left).attr('y', d => y(d[labelKey])).attr('width', d => x(d[valKey]) - margin.left)
            .attr('height', y.bandwidth()).attr('fill', color).attr('rx', 3);
        svg.selectAll('.label').data(data).join('text')
            .attr('x', d => x(d[valKey]) + 5).attr('y', d => y(d[labelKey]) + y.bandwidth() / 2)
            .attr('dy', '0.35em').attr('fill', 'var(--bs-body-color)').attr('font-size', '12px')
            .text(d => d[valKey]);
    }
});
</script>
