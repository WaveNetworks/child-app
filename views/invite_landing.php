<?php
/**
 * Invite Landing — Accept or decline a coach invite
 */
$page_title = 'Coach Invitation';
$token = $_GET['token'] ?? '';
$validation = $token ? validate_invite_token($token) : ['valid' => false, 'reason' => 'No token provided'];
$coach_name = '';

if ($validation['valid']) {
    $link = $validation['link'];
    $coach_profile = get_user_profile($link['creator_user_id'], $link['creator_shard_id']);
    $coach_name = trim(($coach_profile['first_name'] ?? '') . ' ' . ($coach_profile['last_name'] ?? ''));
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body text-center py-5">
                <?php if (!$validation['valid']) { ?>
                <i class="bi bi-x-circle display-4 text-danger"></i>
                <h5 class="mt-3">Invalid Invitation</h5>
                <p class="text-muted"><?= h($validation['reason'] ?? 'This invite link is no longer valid.') ?></p>
                <a href="index.php?page=dashboard" class="btn btn-primary">Go to Dashboard</a>
                <?php } else { ?>
                <i class="bi bi-person-plus display-4 text-primary"></i>
                <h4 class="mt-3">Coach <?= h($coach_name) ?> has invited you!</h4>
                <p class="text-muted">By accepting, <?= h($coach_name) ?> will be able to view your assessment progress and provide coaching guidance.</p>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="accept_invite">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <button type="submit" class="btn btn-primary btn-lg me-2"><i class="bi bi-check-lg me-1"></i>Accept Invitation</button>
                </form>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="decline_invite">
                    <button type="submit" class="btn btn-outline-secondary btn-lg">Decline</button>
                </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
