<?php
/**
 * exampleActions.php
 * Handles example form submissions (modal validation demo).
 */

if (($_POST['action'] ?? '') == 'sendFeedback') {
    $errs = array();

    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }
    if (!trim($_POST['subject'] ?? '')) { $errs['subject'] = 'Subject is required.'; }
    if (!trim($_POST['message'] ?? '')) { $errs['message'] = 'Message is required.'; }

    if (count($errs) <= 0) {
        // In a real app you'd store this or queue an email.
        $_SESSION['success'] = 'Feedback sent. Thank you!';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}
