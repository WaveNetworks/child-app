<?php
/**
 * Assessment — Minimal mount point for chat-overlay.js
 * The actual UI is created by chat-overlay.js as a floating overlay on the lotus canvas.
 */
$page_title = 'My Assessment';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$instance = get_user_instance($uid, $sid, 'ai-assessment');
$active_session = get_active_session($uid, $sid);
$has_profile = !empty($instance) && !empty($instance['instance_data']['subjectProfile']['completedAt'] ?? null);

$phase_label = 'ready';
if ($active_session) {
    $phase_label = $active_session['phase'] ?? 'profile';
}

// Build logo URL from admin branding
$_b = get_branding();
$logo_url = '';
if (!empty($_b['logo_dark_path'])) {
    $logo_url = '../../admin/branding/' . $_b['logo_dark_path'];
} elseif (!empty($_b['logo_path'])) {
    $logo_url = '../../admin/branding/' . $_b['logo_path'];
}

// Auto-start: if no instance exists yet, flag JS to auto-start
$auto_start = empty($instance) && empty($active_session) ? '1' : '0';
?>
<div id="chat-overlay-mount"
     data-session-id="<?= h($active_session ? $active_session['session_id'] : '') ?>"
     data-has-profile="<?= $has_profile ? '1' : '0' ?>"
     data-phase="<?= h($phase_label) ?>"
     data-auto-start="<?= $auto_start ?>"
     data-logo-url="<?= h($logo_url) ?>"
     data-site-name="<?= h($_b['site_name'] ?? CHILD_APP_NAME) ?>">

    <!-- Splash overlay: logo + fake thinking — hidden once first bot message arrives -->
    <div id="chatSplash" class="chat-splash">
        <?php if ($logo_url) { ?>
        <img src="<?= h($logo_url) ?>" alt="<?= h($_b['site_name'] ?? CHILD_APP_NAME) ?>" class="chat-splash__logo">
        <?php } else { ?>
        <div class="chat-splash__logo-text"><?= h($_b['site_name'] ?? CHILD_APP_NAME) ?></div>
        <?php } ?>
        <div class="chat-splash__tagline">Your AI-Powered Growth Companion</div>
        <div class="chat-splash__typing">
            <div class="chat-typing__dot"></div><div class="chat-typing__dot"></div><div class="chat-typing__dot"></div>
        </div>
        <div class="chat-splash__status">Preparing your session&hellip;</div>
    </div>

    <div class="chat-messages" id="chatMessages"></div>
    <div class="chat-bubble chat-bubble--bot" id="chatTyping" style="display:none">
        <div class="chat-typing"><div class="chat-typing__dot"></div><div class="chat-typing__dot"></div><div class="chat-typing__dot"></div></div>
    </div>
    <div class="chat-input-bar" id="chatInputBar">
        <input type="text" class="chat-input" id="chatInput" placeholder="Type your response..." autocomplete="off">
        <button class="chat-send-btn" id="chatSendBtn">&#10148;</button>
    </div>
    <div class="chat-options-area" id="chatOptionsArea"></div>
    <div id="chatPhaseBadge" style="display:none"><?= h($phase_label) ?></div>
</div>

<?php if (function_exists('has_role') && has_role('admin')) { ?>
<!-- Admin AI Debug Modal -->
<div class="modal fade" id="aiDebugModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,20,35,0.97);border:1px solid rgba(100,160,220,0.2);backdrop-filter:blur(20px);">
            <div class="modal-header" style="border-bottom:1px solid rgba(100,160,220,0.15);">
                <h5 class="modal-title" style="color:#a3cef0;">
                    <i class="bi bi-bug me-2"></i>AI Debug — <span id="aiDebugVendor"></span> / <span id="aiDebugModel"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="color:#cdd8e6;">
                <!-- KPI bar -->
                <div class="row g-2 mb-3" id="aiDebugKpis">
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Vendor</div>
                            <div id="aiDbgVendor" style="font-weight:600;color:#6ca6d9;">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Tokens In</div>
                            <div id="aiDbgTokensIn" style="font-weight:600;color:#6bc96b;">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Tokens Out</div>
                            <div id="aiDbgTokensOut" style="font-weight:600;color:#6bc96b;">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Cost</div>
                            <div id="aiDbgCost" style="font-weight:600;color:#c89a40;">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Phase</div>
                            <div id="aiDbgPhase" style="font-weight:600;color:#b388d9;">—</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background:rgba(100,160,220,0.1);border:1px solid rgba(100,160,220,0.15);">
                            <div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Signal</div>
                            <div id="aiDbgSignal" style="font-weight:600;color:#e07878;">—</div>
                        </div>
                    </div>
                </div>

                <!-- Detail badges row -->
                <div class="mb-3" id="aiDebugBadges"></div>

                <!-- Tabs: Structured JSON / System Prompt -->
                <ul class="nav nav-tabs" role="tablist" style="border-color:rgba(100,160,220,0.15);">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aiDbgTabResponse" style="color:#a3cef0;">AI JSON Response</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aiDbgTabPrompt" style="color:#a3cef0;">System Prompt</button>
                    </li>
                </ul>
                <div class="tab-content mt-2">
                    <div class="tab-pane fade show active" id="aiDbgTabResponse">
                        <pre id="aiDbgResponse" style="background:rgba(0,0,0,0.4);border:1px solid rgba(100,160,220,0.1);border-radius:6px;padding:12px;color:#d4b8f0;font-size:0.78rem;max-height:50vh;overflow:auto;white-space:pre-wrap;word-wrap:break-word;"></pre>
                    </div>
                    <div class="tab-pane fade" id="aiDbgTabPrompt">
                        <pre id="aiDbgPrompt" style="background:rgba(0,0,0,0.4);border:1px solid rgba(100,160,220,0.1);border-radius:6px;padding:12px;color:#a8c8a8;font-size:0.78rem;max-height:50vh;overflow:auto;white-space:pre-wrap;word-wrap:break-word;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<script src="../assets/js/chat-overlay.js?v=16"></script>
