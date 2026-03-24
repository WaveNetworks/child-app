<?php
/**
 * snippets/feedback_tab.php
 * Floating feedback tab for child-app — own copy, consistent with child-app styling.
 * Posts to child-app's own API endpoint.
 */
if (empty($_SESSION['user_id'])) return;
?>

<!-- Floating feedback trigger tab -->
<button type="button" id="feedbackTabTrigger" class="btn btn-primary shadow"
        data-bs-toggle="offcanvas" data-bs-target="#feedbackOffcanvas"
        style="position:fixed; right:0; top:50%; transform:translateX(calc(50% - 0.75em)) translateY(-50%) rotate(-90deg);
               z-index:1040; border-radius:0.375rem 0.375rem 0 0; padding:0.35rem 1rem; font-size:0.85rem; letter-spacing:0.03em;">
    <i class="bi bi-chat-left-text me-1"></i>Feedback
</button>

<!-- Feedback offcanvas panel -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="feedbackOffcanvas" style="width:360px;">
    <div class="offcanvas-header border-bottom">
        <h6 class="offcanvas-title"><i class="bi bi-chat-left-text me-2"></i>Send Feedback</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="fbTabFormContainer">
            <div class="mb-3">
                <label class="form-label fw-semibold">Type</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="fbTab_type" id="fbTabTypeBug" value="bug">
                    <label class="btn btn-outline-danger btn-sm" for="fbTabTypeBug"><i class="bi bi-bug me-1"></i>Bug</label>

                    <input type="radio" class="btn-check" name="fbTab_type" id="fbTabTypeSuggestion" value="suggestion">
                    <label class="btn btn-outline-primary btn-sm" for="fbTabTypeSuggestion"><i class="bi bi-lightbulb me-1"></i>Suggestion</label>

                    <input type="radio" class="btn-check" name="fbTab_type" id="fbTabTypeGeneral" value="general" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="fbTabTypeGeneral"><i class="bi bi-chat me-1"></i>General</label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Your Feedback</label>
                <textarea class="form-control" id="fbTabMessage" rows="5" placeholder="Tell us what you think..."></textarea>
            </div>
            <div class="mb-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>Page and user info captured automatically.
                </small>
            </div>
            <button class="btn btn-primary w-100" id="fbTabSubmitBtn" onclick="submitFeedbackTab()">
                <i class="bi bi-send me-1"></i>Submit Feedback
            </button>
        </div>
        <div id="fbTabSuccessContainer" style="display:none;" class="text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
            <h5 class="mt-3">Thank you!</h5>
            <p class="text-muted">Your feedback has been received.</p>
            <button class="btn btn-outline-primary btn-sm" onclick="resetFeedbackTab()">Send More</button>
        </div>
    </div>
</div>

<script>
function submitFeedbackTab() {
    var message = document.getElementById('fbTabMessage').value.trim();
    if (!message) {
        document.getElementById('fbTabMessage').classList.add('is-invalid');
        return;
    }
    document.getElementById('fbTabMessage').classList.remove('is-invalid');

    var typeEl = document.querySelector('input[name="fbTab_type"]:checked');
    var type = typeEl ? typeEl.value : 'general';

    var btn = document.getElementById('fbTabSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

    apiPost('submitUserFeedback', {
        feedback_type: type,
        message: message,
        page_url: window.location.href
    }, function (json) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Feedback';
        if (json.error) {
            if (typeof showToast === 'function') showToast('danger', json.error);
            else alert(json.error);
            return;
        }
        document.getElementById('fbTabFormContainer').style.display = 'none';
        document.getElementById('fbTabSuccessContainer').style.display = '';
        if (typeof showToast === 'function') showToast('success', 'Thank you for your feedback!');
    });
}

function resetFeedbackTab() {
    document.getElementById('fbTabMessage').value = '';
    document.getElementById('fbTabTypeGeneral').checked = true;
    document.getElementById('fbTabFormContainer').style.display = '';
    document.getElementById('fbTabSuccessContainer').style.display = 'none';
}
</script>
