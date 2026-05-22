<?php
/**
 * Invite Sellers — Create and manage invite links with QR codes
 */
$page_title = 'Invite Sellers';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$user_tier = get_user_tier($uid, $sid);
$invites = get_coach_invites($uid);
$active_invites = array_filter($invites, fn($i) => $i['is_active'] && (!$i['expires_at'] || strtotime($i['expires_at']) > time()));
$inactive_invites = array_filter($invites, fn($i) => !$i['is_active'] || ($i['expires_at'] && strtotime($i['expires_at']) <= time()));
$td = get_tier_display($user_tier);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Invite Sellers</h4>
    <span class="badge bg-<?= h($td['color']) ?> fs-6"><i class="<?= h($td['icon']) ?> me-1"></i><?= h($td['label']) ?></span>
</div>

<!-- Create Invite -->
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Create New Invite</h6></div>
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="generate_invite">
            <div class="col-md-3">
                <label class="form-label">Max Uses</label>
                <select class="form-select" name="max_uses">
                    <option value="">Unlimited</option>
                    <option value="1">1 use</option>
                    <option value="5">5 uses</option>
                    <option value="10">10 uses</option>
                    <option value="25">25 uses</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Expires In</label>
                <select class="form-select" name="expires_hours">
                    <option value="">Never</option>
                    <option value="24">24 hours</option>
                    <option value="72">3 days</option>
                    <option value="168">1 week</option>
                    <option value="720">30 days</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Generate Link</button>
            </div>
        </form>
    </div>
</div>

<!-- Active Links -->
<?php if (!empty($active_invites)) { ?>
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Active Invite Links <span class="badge bg-primary ms-1"><?= count($active_invites) ?></span></h6></div>
    <div class="list-group list-group-flush">
        <?php foreach ($active_invites as $inv) {
            $invite_url = get_invite_link_url($inv['token']);
        ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <code class="small"><?= h(substr($inv['token'], 0, 12)) ?>...</code>
                    <span class="badge bg-secondary ms-2"><?= $inv['use_count'] ?><?= $inv['max_uses'] ? '/' . $inv['max_uses'] : '' ?> used</span>
                    <?php if ($inv['expires_at']) { ?>
                    <small class="text-muted ms-2">Expires: <?= date('M j, Y', strtotime($inv['expires_at'])) ?></small>
                    <?php } ?>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-info" onclick="showQR('<?= h($invite_url) ?>', '<?= h(substr($inv['token'], 0, 8)) ?>')" title="Show QR Code">
                        <i class="bi bi-qr-code"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="copyLink('<?= h($invite_url) ?>')" title="Copy Link">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Disable this invite link?')">
                        <input type="hidden" name="action" value="disable_invite">
                        <input type="hidden" name="invite_id" value="<?= $inv['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Disable"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php } else { ?>
<div class="card mb-4">
    <div class="card-body text-center py-4">
        <i class="bi bi-link-45deg display-4 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No active invite links yet. Generate one above to start adding sellers to your team.</p>
    </div>
</div>
<?php } ?>

<!-- Expired/Disabled -->
<?php if (!empty($inactive_invites)) { ?>
<div class="card">
    <div class="card-header"><h6 class="mb-0 text-muted">Expired / Disabled</h6></div>
    <div class="list-group list-group-flush">
        <?php foreach ($inactive_invites as $inv) { ?>
        <div class="list-group-item text-muted">
            <code class="small"><?= h(substr($inv['token'], 0, 12)) ?>...</code>
            <span class="badge bg-light text-muted ms-2"><?= $inv['use_count'] ?> used</span>
            <small class="ms-2"><?= !$inv['is_active'] ? '(disabled)' : '(expired)' ?></small>
        </div>
        <?php } ?>
    </div>
</div>
<?php } ?>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-qr-code me-2"></i>Invite QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="qrCodeContainer" class="mb-3"></div>
                <p class="small text-muted mb-2">Sellers scan this to accept your invite</p>
                <a id="qrDownloadLink" href="#" download="<?= h(CHILD_APP_SLUG) ?>-invite.png" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i>Download PNG
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
function showQR(url, tokenPrefix) {
    var container = document.getElementById('qrCodeContainer');
    container.innerHTML = '';

    var qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();

    var img = qr.createImgTag(6, 12);
    container.innerHTML = img;

    var imgEl = container.querySelector('img');
    if (imgEl) {
        imgEl.style.maxWidth = '100%';
        imgEl.style.borderRadius = '8px';
        imgEl.onload = function() {
            var canvas = document.createElement('canvas');
            canvas.width = imgEl.naturalWidth;
            canvas.height = imgEl.naturalHeight;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(imgEl, 0, 0);
            document.getElementById('qrDownloadLink').href = canvas.toDataURL('image/png');
            document.getElementById('qrDownloadLink').download = '<?= h(CHILD_APP_SLUG) ?>-invite-' + tokenPrefix + '.png';
        };
    }

    var modal = new bootstrap.Modal(document.getElementById('qrModal'));
    modal.show();
}

function copyLink(text) {
    navigator.clipboard.writeText(text).then(function() {
        if (typeof showToast === 'function') showToast('success', 'Link copied to clipboard!');
    }).catch(function() {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        if (typeof showToast === 'function') showToast('success', 'Link copied!');
    });
}
</script>
