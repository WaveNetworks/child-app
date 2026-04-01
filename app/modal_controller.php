<?php
/**
 * modal_controller.php
 * AJAX modal content loader for ElevateHER Learning Suite.
 */
include(__DIR__ . '/../include/common.php');

$modal = $_POST['modal'] ?? $_GET['modal'] ?? '';

$modals = [
    'item_detail'    => __DIR__ . '/../snippets/modal_item_detail.php',
    'confirm_delete' => __DIR__ . '/../snippets/modal_confirm_delete.php',
    'example_form'   => __DIR__ . '/../snippets/modal_example_form.php',
    'seller_detail'  => __DIR__ . '/../snippets/modal_seller_detail.php',
    'coach_note'     => __DIR__ . '/../snippets/modal_coach_note.php',
    'kpi_detail'     => __DIR__ . '/../snippets/modal_kpi_detail.php',
];

if (isset($modals[$modal]) && file_exists($modals[$modal])) {
    include($modals[$modal]);
} else {
    http_response_code(404);
    echo '<div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    echo '<div class="modal-body"><p class="text-danger">Modal not found.</p></div>';
}
