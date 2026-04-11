# Template and Action Patterns

## Template pattern
```php
<?php if ($someCondition) { ?>
<div class="something"><?= h($userData['field']) ?></div>
<?php } ?>
```

## Action file pattern
```php
if ($_POST['action'] == 'doThing') {
    $errs = array();
    if (!$_SESSION['user_id']) { $errs['auth'] = 'Login required.'; }
    if (!$_POST['title'])      { $errs['title'] = 'Title required.'; }
    if (count($errs) <= 0) {
        child_db_query_prepared("INSERT INTO items ...", [...]);
        $_SESSION['success'] = 'Done.';
    } else {
        $_SESSION['error'] = implode('<br>', $errs);
    }
}
```

## Action dispatch
- Authenticated: `include/actions/memberActions/yourFeatureActions.php` (auto-included via glob)
- API/public: `include/actions/apiActions/yourFeatureActions.php`
- Form POST: `<form method="post">` on any view page
- AJAX: `apiPost('actionName', {data}, callback)` → `api/index.php`
