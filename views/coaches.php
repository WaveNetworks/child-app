<?php
/**
 * My Coaches — View linked coaches, shared notes, and scan QR to add a coach
 */
$page_title = 'My Coaches';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$coaches = get_user_coaches($uid, $sid);
$instance = get_user_instance($uid, $sid, 'ai-assessment');
$coach_notes = ($instance && $instance['instance_data']) ? ($instance['instance_data']['coachNotes'] ?? []) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>My Coaches</h4>
    <button class="btn btn-primary btn-sm" onclick="toggleScanner()">
        <i class="bi bi-qr-code-scan me-1"></i>Scan Coach QR
    </button>
</div>

<!-- QR Scanner Section (hidden by default) -->
<div id="scannerSection" class="card mb-4" style="display:none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-camera me-2"></i>Scan Coach's QR Code</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleScanner()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="card-body text-center">
        <div id="qr-reader" style="max-width:400px;margin:0 auto;"></div>
        <p class="small text-muted mt-2 mb-0">Point your camera at your coach's QR code</p>
        <hr>
        <p class="small text-muted mb-2">Or paste an invite link or token:</p>
        <form method="get" class="d-flex gap-2 justify-content-center" id="manualInviteForm">
            <input type="hidden" name="page" value="invite-landing">
            <input type="text" class="form-control form-control-sm" style="max-width:350px" placeholder="Paste invite URL or token..." id="manualTokenInput" name="token">
            <button type="submit" class="btn btn-sm btn-primary">Go</button>
        </form>
    </div>
</div>

<?php if (empty($coaches)) { ?>
<div class="text-center py-5">
    <i class="bi bi-person-plus display-4 text-muted"></i>
    <p class="text-muted mt-2">You don't have any coaches yet.</p>
    <p class="text-muted">Ask your coach for an invite link, or tap <strong>Scan Coach QR</strong> above to scan their code.</p>
</div>
<?php } else { ?>
<div class="row g-3">
    <?php foreach ($coaches as $rel) { ?>
    <?php
        $coach_profile = get_user_profile($rel['coach_user_id'], $rel['coach_shard_id']);
        $coach_name = trim(($coach_profile['first_name'] ?? '') . ' ' . ($coach_profile['last_name'] ?? ''));
        $my_notes = array_filter($coach_notes, fn($n) => $n['coachId'] === 'user-' . $rel['coach_user_id'] && $n['visibility'] === 'shared');
    ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                        <i class="bi bi-person-fill text-primary fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="mb-0"><?= h($coach_name) ?></h6>
                        <small class="text-muted">Linked since <?= date('M j, Y', strtotime($rel['linked_at'])) ?></small>
                    </div>
                </div>

                <?php if (!empty($my_notes)) { ?>
                <h6 class="small text-muted mb-2">Shared Notes</h6>
                <?php foreach (array_slice(array_values($my_notes), -3) as $note) { ?>
                <div class="border-start border-info border-2 ps-2 mb-2">
                    <small><?= h($note['note'] ?? '') ?></small>
                    <br><small class="text-muted"><?= date('M j', strtotime($note['date'] ?? '')) ?></small>
                </div>
                <?php } ?>
                <?php } ?>
            </div>
            <div class="card-footer bg-transparent">
                <form method="post" class="d-inline" onsubmit="return confirm('End this coaching relationship?')">
                    <input type="hidden" name="action" value="end_relationship">
                    <input type="hidden" name="coach_user_id" value="<?= (int)$rel['coach_user_id'] ?>">
                    <input type="hidden" name="coach_shard_id" value="<?= h($rel['coach_shard_id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">End Relationship</button>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>

<!-- QR Scanner Library -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
var scannerInstance = null;
var scannerVisible = false;

function toggleScanner() {
    var section = document.getElementById('scannerSection');
    scannerVisible = !scannerVisible;
    if (scannerVisible) {
        section.style.display = '';
        startScanner();
    } else {
        stopScanner();
        section.style.display = 'none';
    }
}

function startScanner() {
    if (scannerInstance) return;
    scannerInstance = new Html5Qrcode("qr-reader");
    scannerInstance.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function onScanSuccess(decodedText) {
            var token = decodedText;
            var match = decodedText.match(/[?&]token=([^&#]+)/);
            if (match) token = match[1];
            stopScanner();
            window.location.href = 'index.php?page=invite-landing&token=' + encodeURIComponent(token);
        },
        function onScanFailure() {}
    ).catch(function() {
        document.getElementById('qr-reader').innerHTML =
            '<div class="alert alert-warning small mb-0"><i class="bi bi-camera-video-off me-2"></i>Camera not available. Use the manual entry below.</div>';
    });
}

function stopScanner() {
    if (scannerInstance) {
        scannerInstance.stop().catch(function(){});
        scannerInstance = null;
    }
}

// Extract token from pasted URL
document.getElementById('manualInviteForm').addEventListener('submit', function(e) {
    var input = document.getElementById('manualTokenInput');
    var val = input.value.trim();
    var match = val.match(/[?&]token=([^&#]+)/);
    if (match) input.value = match[1];
});
</script>
