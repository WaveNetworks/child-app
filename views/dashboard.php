<?php
/**
 * views/dashboard.php
 * User-facing dashboard: hero, stats, D3 charts, quick actions, recent activity.
 */
$page_title = 'Dashboard';

// Item stats from child app's main DB
$stats = child_db_query_prepared("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
    FROM items WHERE user_id = ?", [$_SESSION['user_id']]);
$counts = db_fetch($stats) ?: ['total' => 0, 'active' => 0, 'draft' => 0, 'archived' => 0];

// Recent activity from child shard
$history = get_user_history($_SESSION['user_id'], $_SESSION['shard_id'], 10);

// Monthly item creation counts (last 6 months) for line chart
$monthly = [];
$m_stmt = child_db_query_prepared("SELECT
    DATE_FORMAT(created, '%Y-%m') AS month,
    COUNT(*) AS cnt
    FROM items
    WHERE user_id = ? AND created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month", [$_SESSION['user_id']]);
if ($m_stmt) {
    while ($row = $m_stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthly[] = $row;
    }
}
// Fill missing months with zero
$filled = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $label = date('M', strtotime("-{$i} months"));
    $val = 0;
    foreach ($monthly as $m) {
        if ($m['month'] === $key) { $val = (int) $m['cnt']; break; }
    }
    $filled[] = ['month' => $label, 'count' => $val];
}
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-3">
        <li class="breadcrumb-item"><a href="index.php?page=dashboard"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
</nav>

<!-- Hero box -->
<div class="dashboard-hero mb-4 p-4 rounded-3">
    <div class="d-flex align-items-center">
        <div class="flex-grow-1">
            <h3 class="mb-1">Welcome back, <?= h($_SESSION['first_name'] ?? 'User') ?></h3>
            <p class="mb-0 opacity-75">Here's what's happening with your items today.</p>
        </div>
        <div class="d-none d-md-block">
            <i class="bi bi-speedometer2" style="font-size: 3rem; opacity: 0.3;"></i>
        </div>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Items</div>
                <div class="fs-3 fw-bold"><?= (int) $counts['total'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Active</div>
                <div class="fs-3 fw-bold text-success"><?= (int) $counts['active'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Drafts</div>
                <div class="fs-3 fw-bold text-warning"><?= (int) $counts['draft'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-start border-secondary border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Archived</div>
                <div class="fs-3 fw-bold text-secondary"><?= (int) $counts['archived'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4">
    <!-- Line chart: items created per month -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up me-1"></i> Items Created (Last 6 Months)</div>
            <div class="card-body">
                <div id="lineChart"></div>
            </div>
        </div>
    </div>
    <!-- Donut chart: item status breakdown -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Status Breakdown</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div id="donutChart"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick actions -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lightning me-1"></i> Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="index.php?page=items&action=new" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> New Item
                </a>
                <a href="index.php?page=items" class="btn btn-outline-secondary">
                    <i class="bi bi-collection me-1"></i> View All Items
                </a>
                <a href="index.php?page=profile" class="btn btn-outline-secondary">
                    <i class="bi bi-person me-1"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Recent Activity</div>
            <div class="card-body p-0">
                <?php if (!empty($history)) { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($history as $row) {
                        $event_data = json_decode($row['event_data'] ?? '{}', true) ?: [];
                        $icon = match($row['event_type']) {
                            'item_created'  => 'bi-plus-circle text-success',
                            'item_updated'  => 'bi-pencil text-primary',
                            'item_deleted'  => 'bi-trash text-danger',
                            'login'         => 'bi-box-arrow-in-right text-info',
                            default         => 'bi-activity text-muted',
                        };
                        $label = match($row['event_type']) {
                            'item_created'  => 'Created item: ' . h($event_data['title'] ?? ''),
                            'item_updated'  => 'Updated item: ' . h($event_data['title'] ?? ''),
                            'item_deleted'  => 'Deleted item: ' . h($event_data['title'] ?? ''),
                            'login'         => 'Logged in',
                            default         => h($row['event_type']),
                        };
                    ?>
                    <div class="list-group-item d-flex align-items-center">
                        <i class="bi <?= $icon ?> fs-5 me-3"></i>
                        <div class="flex-grow-1">
                            <div><?= $label ?></div>
                            <small class="text-muted"><?= h($row['created']) ?></small>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No activity yet. Create your first item to get started!
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- D3.js -->
<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
(function() {
    'use strict';

    var isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
    var textColor = isLight ? 'rgba(0,0,0,0.6)' : 'rgba(255,255,255,0.5)';
    var lineColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#6366f1';
    var gridColor = isLight ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)';

    // ── Line chart ──────────────────────────────────────────────────────────
    var monthlyData = <?= json_encode($filled) ?>;

    var container = document.getElementById('lineChart');
    if (container && monthlyData.length) {
        var margin = {top: 20, right: 20, bottom: 30, left: 40};
        var width = container.clientWidth - margin.left - margin.right;
        var height = 220 - margin.top - margin.bottom;

        var svg = d3.select('#lineChart').append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

        var x = d3.scalePoint()
            .domain(monthlyData.map(function(d) { return d.month; }))
            .range([0, width]);

        var maxY = d3.max(monthlyData, function(d) { return d.count; }) || 1;
        var y = d3.scaleLinear()
            .domain([0, maxY + 1])
            .range([height, 0]);

        // Grid lines
        svg.append('g')
            .attr('class', 'grid')
            .call(d3.axisLeft(y).ticks(5).tickSize(-width).tickFormat(''))
            .selectAll('line').attr('stroke', gridColor);
        svg.selectAll('.grid .domain').remove();

        // X axis
        svg.append('g')
            .attr('transform', 'translate(0,' + height + ')')
            .call(d3.axisBottom(x).tickSize(0))
            .selectAll('text').attr('fill', textColor).style('font-size', '11px');
        svg.selectAll('.domain').attr('stroke', gridColor);

        // Y axis
        svg.append('g')
            .call(d3.axisLeft(y).ticks(5).tickSize(0))
            .selectAll('text').attr('fill', textColor).style('font-size', '11px');

        // Area fill
        var area = d3.area()
            .x(function(d) { return x(d.month); })
            .y0(height)
            .y1(function(d) { return y(d.count); })
            .curve(d3.curveMonotoneX);

        svg.append('path')
            .datum(monthlyData)
            .attr('fill', lineColor)
            .attr('fill-opacity', 0.1)
            .attr('d', area);

        // Line
        var line = d3.line()
            .x(function(d) { return x(d.month); })
            .y(function(d) { return y(d.count); })
            .curve(d3.curveMonotoneX);

        svg.append('path')
            .datum(monthlyData)
            .attr('fill', 'none')
            .attr('stroke', lineColor)
            .attr('stroke-width', 2.5)
            .attr('d', line);

        // Dots
        svg.selectAll('.dot')
            .data(monthlyData).enter()
            .append('circle')
            .attr('cx', function(d) { return x(d.month); })
            .attr('cy', function(d) { return y(d.count); })
            .attr('r', 4)
            .attr('fill', lineColor)
            .attr('stroke', isLight ? '#fff' : '#1a1a2e')
            .attr('stroke-width', 2);
    }

    // ── Donut chart ─────────────────────────────────────────────────────────
    var statusData = [
        {label: 'Active',   value: <?= (int) $counts['active'] ?>,   color: getComputedStyle(document.documentElement).getPropertyValue('--bs-success').trim() || '#22c55e'},
        {label: 'Draft',    value: <?= (int) $counts['draft'] ?>,    color: getComputedStyle(document.documentElement).getPropertyValue('--bs-warning').trim() || '#eab308'},
        {label: 'Archived', value: <?= (int) $counts['archived'] ?>, color: getComputedStyle(document.documentElement).getPropertyValue('--bs-secondary').trim() || '#94a3b8'}
    ];

    var donutContainer = document.getElementById('donutChart');
    if (donutContainer) {
        var total = statusData.reduce(function(s, d) { return s + d.value; }, 0);
        var size = 180;
        var radius = size / 2;
        var innerRadius = radius * 0.6;

        var dSvg = d3.select('#donutChart').append('svg')
            .attr('width', size)
            .attr('height', size)
            .append('g')
            .attr('transform', 'translate(' + radius + ',' + radius + ')');

        if (total === 0) {
            // Empty state ring
            dSvg.append('circle')
                .attr('r', (radius + innerRadius) / 2)
                .attr('fill', 'none')
                .attr('stroke', gridColor)
                .attr('stroke-width', radius - innerRadius);

            dSvg.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '0.35em')
                .attr('fill', textColor)
                .style('font-size', '13px')
                .text('No items');
        } else {
            var pie = d3.pie().value(function(d) { return d.value; }).sort(null);
            var arc = d3.arc().innerRadius(innerRadius).outerRadius(radius);

            dSvg.selectAll('path')
                .data(pie(statusData)).enter()
                .append('path')
                .attr('d', arc)
                .attr('fill', function(d) { return d.data.color; })
                .attr('stroke', isLight ? 'rgba(255,255,255,0.8)' : 'rgba(0,0,0,0.3)')
                .attr('stroke-width', 2);

            // Center total
            dSvg.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '-0.2em')
                .attr('fill', textColor)
                .style('font-size', '24px')
                .style('font-weight', '700')
                .text(total);

            dSvg.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '1.2em')
                .attr('fill', textColor)
                .style('font-size', '11px')
                .text('total');
        }

        // Legend
        var legend = d3.select('#donutChart').append('div')
            .style('display', 'flex')
            .style('justify-content', 'center')
            .style('gap', '12px')
            .style('margin-top', '12px')
            .style('font-size', '12px');

        statusData.forEach(function(d) {
            var item = legend.append('span').style('color', textColor);
            item.append('span')
                .style('display', 'inline-block')
                .style('width', '10px')
                .style('height', '10px')
                .style('border-radius', '2px')
                .style('background', d.color)
                .style('margin-right', '4px');
            item.append('span').text(d.label);
        });
    }
})();
</script>
