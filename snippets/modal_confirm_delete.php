<?php
/**
 * modal_confirm_delete.php
 * Confirmation dialog for deleting an item. Loaded via modalView().
 */
$item_id = (int) ($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
$item = null;
if ($item_id > 0) {
    $r = child_db_query_prepared(
        "SELECT item_id, title FROM items WHERE item_id = ? AND user_id = ?",
        [$item_id, $_SESSION['user_id']]
    );
    $item = db_fetch($r);
}
?>
<div class="modal-header bg-danger text-white">
    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if ($item) { ?>
    <p>Are you sure you want to delete <strong><?= h($item['title']) ?></strong>?</p>
    <p class="text-muted small mb-0">This action cannot be undone.</p>
    <?php } else { ?>
    <p class="text-danger mb-0">Item not found.</p>
    <?php } ?>
</div>
<div class="modal-footer">
    <?php if ($item) { ?>
    <form method="post" action="index.php?page=items">
        <input type="hidden" name="action" value="deleteItem">
        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i> Delete</button>
    </form>
    <?php } ?>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
</div>
