<?php
/**
 * views/dashboard.scaffold.php
 *
 * The dashboard a NEWLY PROVISIONED app starts with.
 *
 * The reference's own dashboard.php is a demo: stat cards, D3 charts and quick
 * actions all driven by the example `items` table. Shipping that to a customer
 * shows them our sample data, and it breaks the moment the Items page is
 * stripped. So the strip swaps this in instead — a real landing page with
 * nothing to unpick, meant to be rewritten as the app's own home screen.
 *
 * Kept deliberately plain: it should look unfinished, because it is. Anything
 * decorative here would only have to be deleted later.
 */
$page_title = 'Dashboard';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Welcome back<?= !empty($_SESSION['first_name']) ? ', ' . h($_SESSION['first_name']) : '' ?></h4>
        <p class="text-body-secondary mb-0">This is your dashboard. It is waiting to be built.</p>
    </div>
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-layout-wtf fs-1 text-body-secondary d-block mb-3" aria-hidden="true"></i>
        <h5>Nothing here yet</h5>
        <p class="text-body-secondary mb-0">
            Replace this page with whatever your users should see first —
            their work in progress, what needs attention, or the next thing to do.
        </p>
    </div>
</div>
