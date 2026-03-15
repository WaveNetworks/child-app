<?php
/**
 * modal_item_detail.php
 * Shows item details in a modal. Loaded via modalView().
 */
$item_id = (int) ($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
$item = null;
if ($item_id > 0) {
    $r = child_db_query_prepared(
        "SELECT * FROM items WHERE item_id = ? AND user_id = ?",
        [$item_id, $_SESSION['user_id']]
    );
    $item = db_fetch($r);
}
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $item ? h($item['title']) : 'Item Not Found' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if ($item) { ?>
    <dl class="row mb-0">
        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
            <span class="badge bg-<?= $item['status'] === 'active' ? 'success' : ($item['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                <?= h(ucfirst($item['status'])) ?>
            </span>
        </dd>
        <dt class="col-sm-3">Description</dt>
        <dd class="col-sm-9"><?= $item['description'] ? h($item['description']) : '<span class="text-muted">No description</span>' ?></dd>
        <dt class="col-sm-3">Created</dt>
        <dd class="col-sm-9"><?= h($item['created']) ?></dd>
        <dt class="col-sm-3">Updated</dt>
        <dd class="col-sm-9"><?= h($item['updated']) ?></dd>
    </dl>
    <?php } else { ?>
    <p class="text-danger mb-0">Item not found or access denied.</p>
    <?php } ?>
</div>
<div class="modal-footer">
    <?php if ($item) { ?>
    <a href="index.php?page=items&action=edit&id=<?= (int) $item['item_id'] ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <?php } ?>
    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
</div>
