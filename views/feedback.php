<?php
/**
 * views/feedback.php
 * User feedback submission + view own feedback history.
 */
$page_title = 'Feedback';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Feedback</h4>
    <a href="index.php?page=roadmap" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-kanban me-1"></i>View Roadmap
    </a>
</div>

<div class="row g-4">

    <!-- Submit Feedback -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-send me-1"></i> Submit Feedback
            </div>
            <div class="card-body">
                <div id="fbFormContainer">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">What type of feedback?</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="fbType" id="fbTypeBug" value="bug">
                            <label class="btn btn-outline-danger" for="fbTypeBug">
                                <i class="bi bi-bug me-1"></i>Bug
                            </label>
                            <input type="radio" class="btn-check" name="fbType" id="fbTypeSuggestion" value="suggestion">
                            <label class="btn btn-outline-primary" for="fbTypeSuggestion">
                                <i class="bi bi-lightbulb me-1"></i>Suggestion
                            </label>
                            <input type="radio" class="btn-check" name="fbType" id="fbTypeGeneral" value="general" checked>
                            <label class="btn btn-outline-secondary" for="fbTypeGeneral">
                                <i class="bi bi-chat me-1"></i>General
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Your message</label>
                        <textarea class="form-control" id="fbMessage" rows="5" placeholder="Describe the bug, share an idea, or tell us what you think..."></textarea>
                        <div class="form-text">Your feedback helps us improve. Be as specific as you can.</div>
                    </div>
                    <button class="btn btn-primary w-100" id="fbSubmitBtn" onclick="submitFb()">
                        <i class="bi bi-send me-1"></i>Send Feedback
                    </button>
                </div>
                <div id="fbSuccessContainer" style="display:none;" class="text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-3">Thank you!</h5>
                    <p class="text-muted mb-3">Your feedback has been received and will be reviewed by our team.</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="resetFbForm()">
                        <i class="bi bi-plus-lg me-1"></i>Send More Feedback
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- My Feedback History -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i> My Feedback</span>
                <div class="d-flex gap-1">
                    <select class="form-select form-select-sm" id="myFbFilterType" onchange="loadMyFeedback(1)" style="width:auto">
                        <option value="">All Types</option>
                        <option value="bug">Bugs</option>
                        <option value="suggestion">Suggestions</option>
                        <option value="general">General</option>
                    </select>
                    <select class="form-select form-select-sm" id="myFbFilterStatus" onchange="loadMyFeedback(1)" style="width:auto">
                        <option value="">All Status</option>
                        <option value="new">New</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="grouped">In Progress</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px">Type</th>
                                <th>Message</th>
                                <th style="width:90px">Status</th>
                                <th style="width:60px" class="text-center">Votes</th>
                                <th style="width:100px">Date</th>
                            </tr>
                        </thead>
                        <tbody id="myFbBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted" id="myFbInfo"></small>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" id="myFbPrev" onclick="loadMyFeedback(myFbPage - 1)" disabled>&laquo;</button>
                    <button class="btn btn-sm btn-outline-secondary" id="myFbNext" onclick="loadMyFeedback(myFbPage + 1)">&raquo;</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    var myFbPage = 1;
    window.myFbPage = myFbPage;

    // ── Submit feedback ─────────────────────────────────────

    window.submitFb = function () {
        var message = document.getElementById('fbMessage').value.trim();
        if (!message) {
            document.getElementById('fbMessage').classList.add('is-invalid');
            return;
        }
        document.getElementById('fbMessage').classList.remove('is-invalid');

        var typeEl = document.querySelector('input[name="fbType"]:checked');
        var type = typeEl ? typeEl.value : 'general';

        var btn = document.getElementById('fbSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

        apiPost('submitUserFeedback', {
            feedback_type: type,
            message: message,
            page_url: window.location.href
        }, function (json) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>Send Feedback';
            if (json.error) {
                showToast('danger', json.error);
                return;
            }
            document.getElementById('fbFormContainer').style.display = 'none';
            document.getElementById('fbSuccessContainer').style.display = '';
            loadMyFeedback(1);
        });
    };

    window.resetFbForm = function () {
        document.getElementById('fbMessage').value = '';
        document.getElementById('fbTypeGeneral').checked = true;
        document.getElementById('fbFormContainer').style.display = '';
        document.getElementById('fbSuccessContainer').style.display = 'none';
    };

    // ── My Feedback list ────────────────────────────────────

    window.loadMyFeedback = function (page) {
        myFbPage = Math.max(1, page);
        window.myFbPage = myFbPage;

        var params = { page: myFbPage };
        var typeVal = document.getElementById('myFbFilterType').value;
        var statusVal = document.getElementById('myFbFilterStatus').value;
        if (typeVal)   params.feedback_type = typeVal;
        if (statusVal) params.status = statusVal;

        apiPost('getUserFeedback', params, function (json) {
            if (json.error) { return; }
            var d = json.results;
            var items = d.items || [];
            var tbody = document.getElementById('myFbBody');

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">' +
                    '<i class="bi bi-inbox fs-4 d-block mb-2"></i>No feedback yet. Share your thoughts!</td></tr>';
            } else {
                var typeBadges = {
                    bug:        '<span class="badge bg-danger">Bug</span>',
                    suggestion: '<span class="badge bg-primary">Suggestion</span>',
                    general:    '<span class="badge bg-secondary">General</span>'
                };
                var statusBadges = {
                    'new':     '<span class="badge bg-info">New</span>',
                    reviewed:  '<span class="badge bg-secondary">Reviewed</span>',
                    grouped:   '<span class="badge bg-warning text-dark">In Progress</span>',
                    dismissed: '<span class="badge bg-dark">Closed</span>'
                };

                tbody.innerHTML = items.map(function (row) {
                    var msg = esc(row.message);
                    if (msg.length > 100) msg = msg.substring(0, 100) + '...';
                    var date = row.created ? row.created.substring(0, 10) : '';

                    return '<tr>' +
                        '<td>' + (typeBadges[row.feedback_type] || '') + '</td>' +
                        '<td class="small">' + msg + '</td>' +
                        '<td>' + (statusBadges[row.status] || '') + '</td>' +
                        '<td class="text-center"><span class="badge bg-light text-dark"><i class="bi bi-hand-thumbs-up me-1"></i>' + (row.upvotes || 0) + '</span></td>' +
                        '<td class="small text-muted">' + date + '</td>' +
                        '</tr>';
                }).join('');
            }

            // Pagination
            var total = d.total || 0;
            var perPage = d.per_page || 20;
            var start = ((d.page - 1) * perPage) + 1;
            var end = Math.min(d.page * perPage, total);
            document.getElementById('myFbInfo').textContent = total > 0 ? start + '-' + end + ' of ' + total : '';
            document.getElementById('myFbPrev').disabled = myFbPage <= 1;
            document.getElementById('myFbNext').disabled = end >= total;
        });
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // Initial load
    loadMyFeedback(1);

})();
</script>
