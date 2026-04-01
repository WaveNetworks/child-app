/**
 * @module modal
 * @description AJAX modal loader for child-app.
 * Vanilla JS equivalent of the jQuery modalView() pattern.
 *
 * Usage:
 *   modalView('modal=item_detail&item_id=5')
 *   modalView('modal=confirm_delete&item_id=5')
 *   modalView('modal=example_form')
 *
 *   // Submit a form inside the modal and reload it with validation:
 *   reloadModalThisForm('feedbackForm', 'modal=example_form&action=sendFeedback')
 *
 * Requires #largeModal and #modal-content in template.php.
 */

/**
 * Load content into the large modal via AJAX POST.
 * Shows a loading spinner, fetches from modal_controller.php,
 * and replaces modal-content with the response HTML.
 *
 * @param {string} params - URL-encoded key=value pairs separated by '&'
 */
function modalView(params) {
    var content = document.getElementById('modal-content');
    var modalEl = document.getElementById('largeModal');
    if (!content || !modalEl) return;

    // Show loading state
    content.innerHTML =
        '<div class="modal-body text-center py-5">' +
        '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>' +
        '<p class="mt-3 text-muted small">Loading...</p>' +
        '</div>';

    // Show the modal
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Build FormData from params string
    var formData = new FormData();
    var pairs = params.split('&');
    for (var i = 0; i < pairs.length; i++) {
        var kv = pairs[i].split('=');
        if (kv.length === 2) {
            formData.append(decodeURIComponent(kv[0]), decodeURIComponent(kv[1]));
        }
    }

    fetch('modal_controller.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.text(); })
    .then(function(html) {
        content.innerHTML = html;
    })
    .catch(function(err) {
        content.innerHTML =
            '<div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body"><p class="text-danger">Failed to load: ' + err.message + '</p></div>';
    });
}

/**
 * Serialize a form and POST it to modal_controller.php, then reload the
 * modal content with the response. This keeps the modal open and shows
 * validation errors inline.
 *
 * @param {string} formName - The id of the <form> element inside the modal
 * @param {string} gvars    - Extra query params, e.g. 'modal=example_form&action=sendFeedback'
 */
/**
 * Open the KPI detail modal for a given KPI.
 * @param {string} kpiId - e.g. "s01-kpi-01"
 * @param {string} sourceStage - e.g. "01"
 */
function openKpiDetail(kpiId, sourceStage) {
    modalView('modal=kpi_detail&kpi_id=' + encodeURIComponent(kpiId) + '&source_stage=' + encodeURIComponent(sourceStage));
}

/**
 * Save a KPI level selection from within the KPI detail modal.
 * Disables rows, shows spinner, saves via API, then reloads the modal.
 */
function saveKpiFromModal(rowEl, kpiId, level, sourceStage) {
    var container = document.getElementById('kpiModalLevels');
    var statusEl = document.getElementById('kpiModalSaveStatus');
    if (!container) return;

    var rows = container.querySelectorAll('.kpi-modal-level');
    rows.forEach(function(r) { r.style.pointerEvents = 'none'; r.style.opacity = '0.5'; });
    rowEl.style.opacity = '1';
    if (statusEl) statusEl.style.display = 'block';

    apiPost('save_kpi_response', {
        kpi_id: kpiId,
        level: level
    }, function(json) {
        if (statusEl) statusEl.style.display = 'none';

        if (json.error) {
            rows.forEach(function(r) { r.style.pointerEvents = ''; r.style.opacity = ''; });
            try { if (typeof showToast === 'function') showToast('danger', json.error); } catch(e) {}
            return;
        }

        // Check for stage advancement (defined in dashboard.php)
        if (typeof checkStageAdvancement === 'function' && checkStageAdvancement(json)) return;

        // Reload the modal to show updated state
        modalView('modal=kpi_detail&kpi_id=' + encodeURIComponent(kpiId) + '&source_stage=' + encodeURIComponent(sourceStage));

        // Update the dashboard card badge if it exists
        var card = document.getElementById('kpiCard_' + kpiId);
        if (card) {
            var badge = card.querySelector('.badge:not(.bg-danger.bg-opacity-25)');
            var icon = card.querySelector('.bi-circle, .bi-check-circle-fill, .bi-exclamation-circle-fill');
            var meets = (level === 'developing' || level === 'mastery');
            if (badge) {
                var badgeColor = level === 'beginner' ? 'danger' : (level === 'developing' ? 'warning' : 'success');
                badge.className = 'badge bg-' + (meets ? 'success' : badgeColor);
                badge.style.fontSize = '0.72rem';
                badge.textContent = level.charAt(0).toUpperCase() + level.slice(1);
            }
            if (icon) {
                icon.className = meets
                    ? 'bi bi-check-circle-fill text-success'
                    : 'bi bi-exclamation-circle-fill text-danger';
            }
        }

        try { if (typeof showToast === 'function') showToast('success', json.message || 'KPI updated!'); } catch(e) {}
    });
}

function reloadModalThisForm(formName, gvars) {
    var content = document.getElementById('modal-content');
    var form = document.getElementById(formName);
    if (!content || !form) return;

    // Serialize the form
    var formData = new URLSearchParams(new FormData(form));

    // Append extra params
    var pairs = gvars.split('&');
    for (var i = 0; i < pairs.length; i++) {
        var kv = pairs[i].split('=');
        if (kv.length === 2) {
            formData.append(decodeURIComponent(kv[0]), decodeURIComponent(kv[1]));
        }
    }

    fetch('modal_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(function(r) { return r.text(); })
    .then(function(html) {
        content.innerHTML = html;
    })
    .catch(function(err) {
        content.innerHTML =
            '<div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body"><p class="text-danger">Failed to load: ' + err.message + '</p></div>';
    });
}
