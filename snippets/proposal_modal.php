<?php
/**
 * Reusable proposal/feedback modal.
 * Include once in any admin view, then trigger via JS:
 *   openProposalModal('stage', 'stage_03', 'Stage 03 — The Producer')
 */
?>
<div class="modal fade" id="proposalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="submit_proposal">
                <input type="hidden" name="target_type" id="pm_target_type">
                <input type="hidden" name="target_id" id="pm_target_id">
                <input type="hidden" name="field_path" id="pm_field_path">
                <input type="hidden" name="old_value" id="pm_old_value">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="pm_context"></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Type</label>
                        <select class="form-select form-select-sm" name="change_type" id="pm_change_type">
                            <option value="comment">Comment / Discussion</option>
                            <option value="edit">Suggest Edit</option>
                            <option value="add">Suggest Addition</option>
                            <option value="remove">Suggest Removal</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="pm_new_value_group">
                        <label class="form-label fw-bold">Proposed Value</label>
                        <textarea class="form-control form-control-sm" name="new_value" rows="3" placeholder="Enter the proposed new value..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Details</label>
                        <textarea class="form-control" name="rationale" rows="4" required placeholder="Describe your feedback, suggestion, or the issue you've found..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openProposalModal(targetType, targetId, contextLabel, fieldPath, oldValue) {
    document.getElementById('pm_target_type').value = targetType || '';
    document.getElementById('pm_target_id').value = targetId || '';
    document.getElementById('pm_field_path').value = fieldPath || '';
    document.getElementById('pm_old_value').value = oldValue || '';
    document.getElementById('pm_context').textContent = contextLabel || (targetType + ': ' + targetId);
    new bootstrap.Modal(document.getElementById('proposalModal')).show();
}

document.getElementById('pm_change_type').addEventListener('change', function() {
    var show = this.value === 'edit' || this.value === 'add';
    document.getElementById('pm_new_value_group').classList.toggle('d-none', !show);
});
</script>
