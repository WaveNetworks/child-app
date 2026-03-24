<?php
/**
 * views/roadmap.php
 * Public roadmap showing change requests grouped by status.
 */
$page_title = 'Roadmap';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-kanban me-2"></i>Roadmap</h4>
    <a href="index.php?page=feedback" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-chat-left-text me-1"></i>Send Feedback
    </a>
</div>

<p class="text-muted mb-4">See what we're working on, what's coming next, and what's been completed.</p>

<!-- Roadmap sections (populated by JS) -->
<div id="roadmapContainer">
    <div class="text-center text-muted py-5">
        <span class="spinner-border spinner-border-sm me-1"></span> Loading roadmap...
    </div>
</div>

<script>
(function () {
    'use strict';

    function loadRoadmap() {
        apiPost('getRoadmapData', {}, function (json) {
            if (json.error) {
                document.getElementById('roadmapContainer').innerHTML =
                    '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load roadmap.</div>';
                return;
            }

            var d = json.results;
            var roadmap = d.roadmap || {};
            var userCRIds = d.user_cr_ids || [];
            var html = '';

            // In Progress
            html += renderSection('In Progress', 'bi-play-circle text-primary', 'primary', roadmap.in_progress || [], userCRIds);

            // Approved (up next)
            html += renderSection('Up Next', 'bi-check2-circle text-success', 'success', roadmap.approved || [], userCRIds);

            // Proposed (under consideration)
            html += renderSection('Under Consideration', 'bi-chat-square-dots text-info', 'info', roadmap.proposed || [], userCRIds);

            // Completed
            var completed = roadmap.completed || [];
            if (completed.length > 0) {
                html += '<div class="mb-4">';
                html += '<h6 class="text-muted mb-3 d-flex align-items-center">';
                html += '<i class="bi bi-check-circle-fill text-success me-2"></i>Completed';
                html += '<span class="badge bg-secondary ms-2">' + completed.length + '</span>';
                html += '<button class="btn btn-sm btn-link text-muted ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#completedSection">';
                html += '<i class="bi bi-chevron-down"></i></button>';
                html += '</h6>';
                html += '<div class="collapse" id="completedSection">';
                html += '<div class="row g-3">';
                completed.forEach(function (cr) {
                    html += renderCard(cr, userCRIds, true);
                });
                html += '</div></div></div>';
            }

            // Paused
            var paused = roadmap.paused || [];
            if (paused.length > 0) {
                html += '<div class="mb-4">';
                html += '<h6 class="text-muted mb-3"><i class="bi bi-pause-circle me-2"></i>Paused <span class="badge bg-secondary ms-1">' + paused.length + '</span></h6>';
                html += '<div class="row g-3">';
                paused.forEach(function (cr) {
                    html += renderCard(cr, userCRIds, true);
                });
                html += '</div></div>';
            }

            if (d.total === 0) {
                html = '<div class="text-center text-muted py-5">' +
                    '<i class="bi bi-kanban fs-1 d-block mb-2"></i>' +
                    '<p>No roadmap items yet. Check back soon!</p></div>';
            }

            document.getElementById('roadmapContainer').innerHTML = html;
        });
    }

    function renderSection(title, icon, color, items, userCRIds) {
        if (items.length === 0) return '';

        var html = '<div class="mb-4">';
        html += '<h6 class="mb-3 d-flex align-items-center">';
        html += '<i class="bi ' + icon + ' me-2"></i>' + title;
        html += '<span class="badge bg-' + color + ' ms-2">' + items.length + '</span>';
        html += '</h6>';
        html += '<div class="row g-3">';
        items.forEach(function (cr) {
            html += renderCard(cr, userCRIds, false);
        });
        html += '</div></div>';
        return html;
    }

    function renderCard(cr, userCRIds, muted) {
        var typeBadge = cr.request_type === 'addition'
            ? '<span class="badge bg-success"><i class="bi bi-plus-lg me-1"></i>New Feature</span>'
            : '<span class="badge bg-info"><i class="bi bi-pencil me-1"></i>Improvement</span>';

        var priBadges = {
            critical: '<span class="badge bg-danger">Critical</span>',
            high:     '<span class="badge bg-warning text-dark">High</span>',
            medium:   '',
            low:      '<span class="badge bg-light text-muted">Low</span>'
        };

        var statusLabels = {
            in_progress: '<span class="badge bg-primary">In Progress</span>',
            approved:    '<span class="badge bg-success">Approved</span>',
            proposed:    '<span class="badge bg-info">Proposed</span>',
            completed:   '<span class="badge bg-secondary">Completed</span>',
            paused:      '<span class="badge bg-warning text-dark">Paused</span>',
            rejected:    '<span class="badge bg-dark">Rejected</span>'
        };

        var isUserLinked = userCRIds.indexOf(parseInt(cr.change_request_id)) !== -1;
        var borderColor = muted ? 'secondary' : (cr.status === 'in_progress' ? 'primary' : (cr.status === 'approved' ? 'success' : 'info'));
        var opacity = muted ? ' opacity-75' : '';

        var html = '<div class="col-md-6 col-xl-4">';
        html += '<div class="card border-start border-' + borderColor + ' border-3' + opacity + '">';
        html += '<div class="card-body">';

        // Title row
        html += '<div class="d-flex justify-content-between align-items-start mb-2">';
        html += '<h6 class="card-title mb-0">' + esc(cr.title) + '</h6>';
        if (isUserLinked) {
            html += '<span class="badge bg-primary-subtle text-primary ms-2" title="Your feedback is linked to this"><i class="bi bi-person-check me-1"></i>Yours</span>';
        }
        html += '</div>';

        // Description
        if (cr.description) {
            var desc = esc(cr.description);
            if (desc.length > 120) desc = desc.substring(0, 120) + '...';
            html += '<p class="card-text small text-muted mb-2">' + desc + '</p>';
        }

        // Badges
        html += '<div class="d-flex flex-wrap gap-1 align-items-center">';
        html += typeBadge;
        if (priBadges[cr.priority]) html += ' ' + priBadges[cr.priority];
        if (statusLabels[cr.status]) html += ' ' + statusLabels[cr.status];
        if (cr.feedback_count > 0) {
            html += ' <span class="badge bg-light text-dark"><i class="bi bi-chat-left me-1"></i>' + cr.feedback_count + '</span>';
        }
        html += '</div>';

        html += '</div></div></div>';
        return html;
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    loadRoadmap();

})();
</script>
