<?php
// Set the action BEFORE including common_auth.php: the admin core's loginActions.php
// fires confirmAccount during the include chain, reading the hash from $_GET.
// (Setting $_POST after the include, as this file used to, never confirmed anyone.)
$hash = $_GET['hash'] ?? '';
if ($hash) {
    $_REQUEST['action'] = 'confirmAccount';
}

include(__DIR__ . '/../include/common_auth.php');

header('Location: login.php');
exit;
