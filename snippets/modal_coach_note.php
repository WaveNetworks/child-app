<?php
/**
 * Modal: Coach Note — Add a coaching note for a seller.
 */
$seller_user_id = (int)($_POST['seller_user_id'] ?? $_GET['seller_user_id'] ?? 0);
$seller_shard_id = trim($_POST['seller_shard_id'] ?? $_GET['seller_shard_id'] ?? '');
$errs = $errs ?? [];

// Get seller name for display
$seller_name = '';
if ($seller_user_id && $seller_shard_id) {
    $profile = get_user_profile($seller_user_id, $seller_shard_id);
    $seller_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
}
?>
<div class="modal-header">
    <h5 class="modal-title">Add Coach Note<?= $seller_name ? ' for ' . h($seller_name) : '' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="post" id="coachNoteForm">
    <input type="hidden" name="action" value="add_coach_note">
    <input type="hidden" name="seller_user_id" value="<?= $seller_user_id ?>">
    <input type="hidden" name="seller_shard_id" value="<?= h($seller_shard_id) ?>">
    <div class="modal-body">
        <?php if (!empty($_SESSION['success'])) { ?>
        <div class="alert alert-success"><?= h($_SESSION['success']) ?></div>
        <?php $_SESSION['success'] = null; ?>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">Dimension</label>
            <select class="form-select" name="dimension">
                <option value="">General</option>
                <option value="mindset" <?= ($_POST['dimension'] ?? '') === 'mindset' ? 'selected' : '' ?>>Mindset</option>
                <option value="toolset" <?= ($_POST['dimension'] ?? '') === 'toolset' ? 'selected' : '' ?>>Toolset</option>
                <option value="skillset" <?= ($_POST['dimension'] ?? '') === 'skillset' ? 'selected' : '' ?>>Skillset</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Note <span class="text-danger">*</span></label>
            <textarea class="form-control <?= isset($errs['note']) ? 'is-invalid' : '' ?>" name="note_text" rows="4" placeholder="Your coaching observation..."><?= h($_POST['note_text'] ?? '') ?></textarea>
            <?php if (isset($errs['note'])) { ?>
            <div class="invalid-feedback"><?= h($errs['note']) ?></div>
            <?php } ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Visibility</label>
            <select class="form-select" name="visibility">
                <option value="shared">Shared (seller can see)</option>
                <option value="coach-only">Coach Only</option>
                <option value="private">Private (only you)</option>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="reloadModalThisForm('coachNoteForm', 'modal=coach_note&action=add_coach_note')">Save Note</button>
    </div>
</form>
