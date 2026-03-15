<?php
/**
 * views/items.php
 * Item list with create/edit forms.
 * Demonstrates: form POST actions, validation, flash messages, pagination.
 */
$page_title = 'Items';

$action = $_GET['action'] ?? 'list';
$item_id = (int) ($_GET['id'] ?? 0);

// ─── EDIT / NEW FORM ────────────────────────────────────────────────────────
if ($action === 'new' || $action === 'edit') {
    $item = null;
    if ($action === 'edit' && $item_id > 0) {
        $r = child_db_query_prepared(
            "SELECT * FROM items WHERE item_id = ? AND user_id = ?",
            [$item_id, $_SESSION['user_id']]
        );
        $item = db_fetch($r);
        if (!$item) {
            $_SESSION['error'] = 'Item not found.';
            header('Location: index.php?page=items');
            exit;
        }
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><?= $item ? 'Edit Item' : 'New Item' ?></h4>
    <a href="index.php?page=items" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Items
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="saveItem">
            <?php if ($item) { ?>
            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
            <?php } ?>

            <div class="mb-3">
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?= h($item['title'] ?? $_POST['title'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"
                ><?= h($item['description'] ?? $_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php
                    $current_status = $item['status'] ?? $_POST['status'] ?? 'draft';
                    foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $val => $label) { ?>
                    <option value="<?= $val ?>" <?= $current_status === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> <?= $item ? 'Update Item' : 'Create Item' ?>
                </button>
                <a href="index.php?page=items" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
    return; // Stop here — don't render the list below
}

// ─── LIST VIEW ──────────────────────────────────────────────────────────────

$page_num   = max(1, (int) ($_GET['pg'] ?? 1));
$per_page   = 15;
$offset     = ($page_num - 1) * $per_page;
$filter     = $_GET['filter'] ?? '';

$where = "WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if (in_array($filter, ['active', 'draft', 'archived'])) {
    $where .= " AND status = ?";
    $params[] = $filter;
}

// Total count
$count_r = child_db_query_prepared("SELECT COUNT(*) as cnt FROM items $where", $params);
$total   = (int) db_fetch($count_r)['cnt'];
$pages   = max(1, ceil($total / $per_page));

// Fetch page
$per_page = (int) $per_page;
$offset   = (int) $offset;
$rows = child_db_query_prepared(
    "SELECT * FROM items $where ORDER BY updated DESC, created DESC LIMIT $per_page OFFSET $offset",
    $params
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Items</h4>
    <a href="index.php?page=items&action=new" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Item
    </a>
</div>

<!-- Filters -->
<div class="btn-group btn-group-sm mb-3">
    <a href="index.php?page=items" class="btn btn-outline-secondary <?= !$filter ? 'active' : '' ?>">All</a>
    <a href="index.php?page=items&filter=active" class="btn btn-outline-secondary <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
    <a href="index.php?page=items&filter=draft" class="btn btn-outline-secondary <?= $filter === 'draft' ? 'active' : '' ?>">Drafts</a>
    <a href="index.php?page=items&filter=archived" class="btn btn-outline-secondary <?= $filter === 'archived' ? 'active' : '' ?>">Archived</a>
</div>

<?php if ($total === 0) { ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-collection fs-1 d-block mb-2"></i>
    <?php if ($filter) { ?>
        No <?= h($filter) ?> items found.
    <?php } else { ?>
        You don't have any items yet.
        <a href="index.php?page=items&action=new">Create your first item</a>.
    <?php } ?>
</div>
<?php } else { ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th style="width:100px">Status</th>
                    <th style="width:160px">Updated</th>
                    <th style="width:80px" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = db_fetch($rows)) {
                    $badge = match($item['status']) {
                        'active'   => 'bg-success',
                        'draft'    => 'bg-warning text-dark',
                        'archived' => 'bg-secondary',
                        default    => 'bg-light text-dark',
                    };
                ?>
                <tr>
                    <td>
                        <a href="index.php?page=items&action=edit&id=<?= (int) $item['item_id'] ?>" class="text-decoration-none fw-medium">
                            <?= h($item['title']) ?>
                        </a>
                    </td>
                    <td class="text-muted small text-truncate" style="max-width:300px"><?= h($item['description'] ?? '') ?></td>
                    <td><span class="badge <?= $badge ?>"><?= h(ucfirst($item['status'])) ?></span></td>
                    <td class="text-muted small"><?= h($item['updated'] ?? $item['created']) ?></td>
                    <td class="text-end">
                        <a href="index.php?page=items&action=edit&id=<?= (int) $item['item_id'] ?>" class="text-primary text-decoration-none me-2" title="Edit" style="font-size: 1rem;">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="action" value="deleteItem">
                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                            <button type="submit" class="btn btn-link text-danger p-0" title="Delete" style="font-size: 1rem;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1) { ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++) {
            $qs = 'page=items' . ($filter ? "&filter=$filter" : '') . "&pg=$i";
        ?>
        <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
            <a class="page-link" href="index.php?<?= $qs ?>"><?= $i ?></a>
        </li>
        <?php } ?>
    </ul>
</nav>
<?php } ?>

<?php } ?>
