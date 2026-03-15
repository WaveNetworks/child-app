<?php
/**
 * modal_example_form.php
 * Example form inside a modal with inline validation.
 * Demonstrates the reloadModalThisForm() pattern:
 *   1. User clicks Submit → JS serializes form + POSTs to modal_controller.php
 *   2. Action file validates → sets $errs / $_SESSION messages
 *   3. This snippet re-renders with errors inline (modal stays open)
 *   4. On success, feedback.php shows success alert in the modal
 */
?>
<form method="post" id="feedbackForm" name="feedbackForm">
    <input type="hidden" name="action" value="sendFeedback">

    <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Send Feedback</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <?php include(__DIR__ . '/../../admin/snippets/feedback.php'); ?>

        <div class="mb-3">
            <label for="feedbackSubject" class="form-label">Subject</label>
            <input type="text" class="form-control <?= isset($errs['subject']) ? 'is-invalid' : '' ?>" id="feedbackSubject" name="subject" placeholder="What's this about?" value="<?= h($_POST['subject'] ?? '') ?>">
            <?php if (isset($errs['subject'])) { ?>
                <div class="invalid-feedback"><?= h($errs['subject']) ?></div>
            <?php } ?>
        </div>
        <div class="mb-3">
            <label for="feedbackMessage" class="form-label">Message</label>
            <textarea class="form-control <?= isset($errs['message']) ? 'is-invalid' : '' ?>" id="feedbackMessage" name="message" rows="4" placeholder="Your feedback..."><?= h($_POST['message'] ?? '') ?></textarea>
            <?php if (isset($errs['message'])) { ?>
                <div class="invalid-feedback"><?= h($errs['message']) ?></div>
            <?php } ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Priority</label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="priority" id="prioLow" value="low" <?= ($_POST['priority'] ?? 'low') === 'low' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="prioLow">Low</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="priority" id="prioMed" value="medium" <?= ($_POST['priority'] ?? '') === 'medium' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="prioMed">Medium</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="priority" id="prioHigh" value="high" <?= ($_POST['priority'] ?? '') === 'high' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="prioHigh">High</label>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-sm" onclick="reloadModalThisForm('feedbackForm', 'modal=example_form&action=sendFeedback')">
            <i class="bi bi-send me-1"></i> Send
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
    </div>
</form>
