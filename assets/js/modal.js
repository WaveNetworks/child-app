/**
 * modal.js — AJAX modal loader for child-app
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
