<?php
/**
 * setup_check.php
 * Verifies child app deployment prerequisites.
 * Web-accessible but displays no sensitive information.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Check — Child App</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; color: #333; }
        h1 { color: #2c3e50; }
        h2 { color: #34495e; margin-top: 24px; font-size: 1.1em; border-bottom: 2px solid #27ae60; padding-bottom: 4px; }
        .check { padding: 8px 0; border-bottom: 1px solid #eee; }
        .pass { color: #27ae60; }
        .fail { color: #e74c3c; }
        .warn { color: #f39c12; }
        .pass::before { content: "✓ "; font-weight: bold; }
        .fail::before { content: "✗ "; font-weight: bold; }
        .warn::before { content: "⚠ "; font-weight: bold; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
        .critical { background: #fde8e8; border: 1px solid #e74c3c; padding: 12px 16px; border-radius: 6px; margin: 16px 0; }
    </style>
</head>
<body>

<h1>Child App — Setup Check</h1>

<h2>Admin Core</h2>

<?php
$adminConfigExists = file_exists(__DIR__ . '/admin/config/config.php');
// Adjust path: child-app is sibling of admin
$adminConfigPath = __DIR__ . '/../admin/config/config.php';
// But setup_check.php is in child-app root, so admin is at ../admin
// Actually: child-app/setup_check.php → ../admin/config/config.php
$adminConfigExists = file_exists($adminConfigPath);
?>

<div class="check <?= $adminConfigExists ? 'pass' : 'fail' ?>">
    Admin config/config.php <?= $adminConfigExists ? 'exists' : 'missing — install admin first' ?>
</div>

<div class="check <?= file_exists(__DIR__ . '/../admin/vendor/autoload.php') ? 'pass' : 'fail' ?>">
    Admin vendor/autoload.php <?= file_exists(__DIR__ . '/../admin/vendor/autoload.php') ? 'exists' : 'missing — run composer install in admin/' ?>
</div>

<h2>Child App</h2>

<?php
$configExists = file_exists(__DIR__ . '/config/config.php');

if ($configExists) {
    include(__DIR__ . '/config/config.php');
}
?>

<div class="check <?= $configExists ? 'pass' : 'fail' ?>">
    config/config.php <?= $configExists ? 'exists' : 'missing — run the installer or create manually' ?>
</div>

<?php if ($configExists && !empty($child_db_host) && !empty($child_db_name)) { ?>
<?php
// Test main DB connection
$childMainDbOk = false;
try {
    $pdo = new PDO("mysql:host=$child_db_host;dbname=$child_db_name;charset=utf8mb4", $child_db_user, $child_db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $childMainDbOk = true;
    $pdo = null;
} catch (PDOException $e) {
    $childMainDbOk = false;
}
?>
<div class="check <?= $childMainDbOk ? 'pass' : 'fail' ?>">
    Main DB connection (<code><?= htmlspecialchars($child_db_name) ?></code>) <?= $childMainDbOk ? 'OK' : 'FAILED' ?>
</div>

<?php
// Test shard connections
if (!empty($childShardConfigs)) {
    foreach ($childShardConfigs as $shardKey => $shardCfg) {
        $shardOk = false;
        try {
            $pdo = new PDO(
                "mysql:host={$shardCfg['host']};dbname={$shardCfg['name']};charset=utf8mb4",
                $shardCfg['user'], $shardCfg['pass']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $shardOk = true;
            $pdo = null;
        } catch (PDOException $e) {
            $shardOk = false;
        }
?>
<div class="check <?= $shardOk ? 'pass' : 'fail' ?>">
    <?= htmlspecialchars($shardKey) ?> DB connection (<code><?= htmlspecialchars($shardCfg['name']) ?></code>) <?= $shardOk ? 'OK' : 'FAILED' ?>
</div>
<?php
    }
}
?>
<?php } elseif (!$configExists) { ?>
<div class="check fail">
    Cannot test DB connections — config not loaded
</div>
<?php } ?>

<div class="check <?= extension_loaded('pdo_mysql') ? 'pass' : 'fail' ?>">
    PHP PDO MySQL extension <?= extension_loaded('pdo_mysql') ? 'loaded' : 'NOT loaded' ?>
</div>

<div class="check pass">
    PHP version: <?= htmlspecialchars(PHP_VERSION) ?>
</div>

<?php if (is_dir(__DIR__ . '/install')) { ?>
<div class="critical">
    <strong class="fail">CRITICAL: The install/ directory still exists!</strong><br>
    Delete it immediately: <code><?= htmlspecialchars(realpath(__DIR__ . '/install')) ?></code>
</div>
<?php } else { ?>
<div class="check pass">
    install/ directory does not exist (good)
</div>
<?php } ?>

<p style="margin-top: 20px; color: #999; font-size: 0.85em;">child-app setup check</p>

</body>
</html>
