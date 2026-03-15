<?php
/**
 * modal_controller.php
 * AJAX modal content loader.
 * Returns HTML snippets for modals loaded via modalView() / reloadModalThisForm().
 *
 * When a form is submitted via reloadModalThisForm(), the action runs first
 * (via the normal action files included by common.php), then the modal
 * re-renders with validation errors or success messages.
 */
include(__DIR__ . '/../include/common.php');

$modal = $_POST['modal'] ?? $_GET['modal'] ?? '';

// Map modal names to snippet files
$modals = [
    'item_detail'    => __DIR__ . '/../snippets/modal_item_detail.php',
    'confirm_delete' => __DIR__ . '/../snippets/modal_confirm_delete.php',
    'example_form'   => __DIR__ . '/../snippets/modal_example_form.php',
];

if (isset($modals[$modal]) && file_exists($modals[$modal])) {
    include($modals[$modal]);
} else {
    http_response_code(404);
    echo '<div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    echo '<div class="modal-body"><p class="text-danger">Modal not found.</p></div>';
}
