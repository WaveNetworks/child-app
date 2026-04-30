<?php
/**
 * Admin Proposals — Review and manage framework feedback/proposals
 */
$page_title = 'Proposals & Feedback';

$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filters = [];
if ($filter_status) $filters['status'] = $filter_status;
if ($filter_type) $filters['target_type'] = $filter_type;
$proposals = get_proposals($filters) ?: [];

// Count by status
$all_proposals = get_proposals([]) ?: [];
$status_counts = ['submitted' => 0, 'approved' => 0, 'applied' => 0, 'rejected' => 0];
foreach ($all_proposals as $p) {
    $s = $p['status'] ?? '';
    if (isset($status_counts[$s])) $status_counts[$s]++;
}
?>

<h4 class="mb-3"><i class="bi bi-chat-left-text me-2"></i>Proposals & Feedback</h4>

<!-- Status summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?page=admin-proposals&status=submitted" class="card text-center text-decoration-none <?= $filter_status === 'submitted' ? 'border-primary' : '' ?>">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold text-warning" id="countSubmitted"><?= $status_counts['submitted'] ?></div>
                <small class="text-muted">Pending</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=admin-proposals&status=approved" class="card text-center text-decoration-none <?= $filter_status === 'approved' ? 'border-primary' : '' ?>">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold text-success" id="countApproved"><?= $status_counts['approved'] ?></div>
                <small class="text-muted">Approved</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=admin-proposals&status=applied" class="card text-center text-decoration-none <?= $filter_status === 'applied' ? 'border-primary' : '' ?>">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold text-info"><?= $status_counts['applied'] ?></div>
                <small class="text-muted">Applied</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=admin-proposals" class="card text-center text-decoration-none <?= !$filter_status ? 'border-primary' : '' ?>">
            <div class="card-body py-2">
                <div class="fs-3 fw-bold"><?= count($all_proposals) ?></div>
                <small class="text-muted">All</small>
            </div>
        </a>
    </div>
</div>

<!-- Filter by target type -->
<div class="d-flex gap-2 mb-3">
    <a href="index.php?page=admin-proposals<?= $filter_status ? '&status=' . h($filter_status) : '' ?>" class="btn btn-sm <?= !$filter_type ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <?php foreach (['stage', 'item', 'kpi', 'content', 'wall', 'general'] as $tt) { ?>
    <a href="index.php?page=admin-proposals&type=<?= $tt ?><?= $filter_status ? '&status=' . h($filter_status) : '' ?>"
       class="btn btn-sm <?= $filter_type === $tt ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= ucfirst($tt) ?></a>
    <?php } ?>
</div>

<?php
$pending_visible = 0;
foreach ($proposals as $p) {
    if (($p['status'] ?? '') === 'submitted') $pending_visible++;
}
?>
<div class="d-flex justify-content-end mb-3" id="approveAllWrap" <?= $pending_visible > 0 ? '' : 'style="display:none"' ?>>
    <form method="post" onsubmit="return confirm('Approve all <?= (int)$pending_visible ?> pending proposal<?= $pending_visible === 1 ? '' : 's' ?><?= $filter_type ? ' of type ' . h($filter_type) : '' ?>?');">
        <input type="hidden" name="action" value="approve_all_proposals">
        <?php if ($filter_type) { ?>
        <input type="hidden" name="target_type" value="<?= h($filter_type) ?>">
        <?php } ?>
        <button type="submit" class="btn btn-sm btn-success">
            <i class="bi bi-check-all me-1"></i>Approve All<?= $filter_type ? ' (' . h($filter_type) . ')' : '' ?>
            <span class="badge bg-light text-success ms-1" id="approveAllBadge"><?= (int)$pending_visible ?></span>
        </button>
    </form>
</div>

<?php if (empty($proposals)) { ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No proposals found<?= $filter_status ? ' with status "' . h($filter_status) . '"' : '' ?>.
    </div>
</div>
<?php } else { ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Target</th>
                    <th>Type</th>
                    <th>Feedback</th>
                    <th>By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proposals as $p) { ?>
                <tr id="proposal-row-<?= (int)$p['id'] ?>">
                    <td>
                        <span class="badge bg-body-tertiary text-body border"><?= h($p['target_type']) ?></span>
                        <?php if ($p['target_id']) { ?>
                        <br><code class="small"><?= h($p['target_id']) ?></code>
                        <?php } ?>
                        <?php if ($p['field_path']) { ?>
                        <br><small class="text-muted"><?= h($p['field_path']) ?></small>
                        <?php } ?>
                    </td>
                    <td>
                        <?php
                        $type_colors = ['comment' => 'info', 'edit' => 'warning', 'add' => 'success', 'remove' => 'danger', 'reorder' => 'secondary'];
                        ?>
                        <span class="badge bg-<?= $type_colors[$p['change_type']] ?? 'secondary' ?>"><?= h($p['change_type']) ?></span>
                    </td>
                    <td>
                        <div class="small" style="max-width: 400px;">
                            <?= nl2br(h(mb_strimwidth($p['rationale'], 0, 200, '...'))) ?>
                        </div>
                        <?php if ($p['new_value']) { ?>
                        <details class="mt-1">
                            <summary class="small text-muted">Proposed value</summary>
                            <pre class="small bg-body-secondary p-2 rounded mt-1 mb-0" style="max-height:100px; overflow:auto;"><?= h($p['new_value']) ?></pre>
                        </details>
                        <?php } ?>
                    </td>
                    <td class="small"><?= h($p['user_email'] ?? 'User #' . $p['user_id']) ?></td>
                    <td class="small text-nowrap"><?= h(date('M j, g:ia', strtotime($p['created']))) ?></td>
                    <td class="proposal-status-cell">
                        <?php
                        $status_colors = ['submitted' => 'warning', 'approved' => 'success', 'applied' => 'info', 'rejected' => 'secondary', 'draft' => 'dark'];
                        ?>
                        <span class="badge bg-<?= $status_colors[$p['status']] ?? 'secondary' ?>"><?= h($p['status']) ?></span>
                    </td>
                    <td class="proposal-actions-cell">
                        <?php if ($p['status'] === 'submitted') { ?>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-success js-review-proposal"
                                    data-proposal-id="<?= (int)$p['id'] ?>" data-new-status="approved" title="Approve">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-review-proposal"
                                    data-proposal-id="<?= (int)$p['id'] ?>" data-new-status="rejected" title="Reject">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <?php } elseif ($p['status'] === 'approved') { ?>
                        <span class="badge bg-success bg-opacity-10 text-success">Ready</span>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<script>
(function () {
    'use strict';

    function adjustCount(id, delta) {
        var el = document.getElementById(id);
        if (!el) return;
        var n = parseInt(el.textContent, 10) || 0;
        n = Math.max(0, n + delta);
        el.textContent = n;
    }

    function updateApproveAllBadge(delta) {
        var badge = document.getElementById('approveAllBadge');
        var wrap  = document.getElementById('approveAllWrap');
        if (!badge || !wrap) return;
        var n = Math.max(0, (parseInt(badge.textContent, 10) || 0) + delta);
        badge.textContent = n;
        wrap.style.display = n > 0 ? '' : 'none';
    }

    function paintRowAsReviewed(row, newStatus) {
        var statusCell = row.querySelector('.proposal-status-cell');
        var actionCell = row.querySelector('.proposal-actions-cell');
        var colors = { approved: 'success', rejected: 'secondary' };
        if (statusCell) {
            statusCell.innerHTML = '<span class="badge bg-' + (colors[newStatus] || 'secondary') + '">' + newStatus + '</span>';
        }
        if (actionCell) {
            actionCell.innerHTML = newStatus === 'approved'
                ? '<span class="badge bg-success bg-opacity-10 text-success">Ready</span>'
                : '';
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-review-proposal');
        if (!btn) return;
        e.preventDefault();
        if (btn.disabled) return;

        var pid       = btn.getAttribute('data-proposal-id');
        var newStatus = btn.getAttribute('data-new-status');
        var row       = document.getElementById('proposal-row-' + pid);
        if (!pid || !newStatus || !row) return;

        // Disable both buttons in the row to prevent double-clicks
        var allBtns = row.querySelectorAll('.js-review-proposal');
        allBtns.forEach(function (b) { b.disabled = true; });
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        apiPost('review_proposal', {
            proposal_id: pid,
            new_status:  newStatus
        }, function (json) {
            if (json && json.error) {
                allBtns.forEach(function (b) { b.disabled = false; });
                btn.innerHTML = newStatus === 'approved'
                    ? '<i class="bi bi-check-lg"></i>'
                    : '<i class="bi bi-x-lg"></i>';
                if (typeof showToast === 'function') showToast('danger', json.error);
                return;
            }
            paintRowAsReviewed(row, newStatus);
            adjustCount('countSubmitted', -1);
            if (newStatus === 'approved') adjustCount('countApproved', +1);
            updateApproveAllBadge(-1);
            if (typeof showToast === 'function') {
                showToast('success', 'Proposal ' + newStatus + '.');
            }
        });
    });
})();
</script>
