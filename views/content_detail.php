<?php
/**
 * Content Detail / Landing Page
 * Shows content info, DSWA community promo, and AI chat informed by this content.
 */
$page_title = 'Content';
$uid = $_SESSION['user_id'];
$sid = $_SESSION['shard_id'];
$content_id = $_GET['id'] ?? null;

// Load catalog
$catalog = get_content_catalog();
$items = isset($catalog['contentCatalog']) ? $catalog['contentCatalog'] : (isset($catalog['items']) ? $catalog['items'] : $catalog);

// Find the specific item if ID provided
$item = null;
if ($content_id) {
    foreach ($items as $ci) {
        if (($ci['id'] ?? '') === $content_id) {
            $item = $ci;
            break;
        }
    }
}

$thumb_url = $item ? get_content_thumbnail_url($item) : null;

// Load progress for this item
$progress = null;
if ($item) {
    $progress_list = get_content_progress_list($uid, $sid);
    foreach ($progress_list as $row) {
        if ($row['catalog_item_id'] === $content_id) {
            $progress = $row;
            break;
        }
    }
}

// If no specific content, show the community landing
$is_landing = !$item;
$page_title = $item ? h($item['title'] ?? 'Content') : 'DSWA Learning Community';
?>

<?php if ($is_landing) { ?>
<!-- ── Community Landing ── -->
<div class="text-center py-4">
    <h2 class="fw-light mb-2">DSWA Learning Community</h2>
    <p class="text-muted mb-4" style="max-width:600px;margin:0 auto;">
        Your Elevate<em>HER</em>&reg; journey doesn't end with the assessment.
        The DSWA online community is filled with courses, activities, resources, and live events
        tailored to every stage of your direct selling career.
    </p>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100 text-center border-0 shadow-sm">
            <div class="card-body">
                <i class="bi bi-journal-richtext display-4 text-primary mb-3"></i>
                <h5>Short Courses</h5>
                <p class="small text-muted">Focused modules designed around your current stage and dimension gaps.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center border-0 shadow-sm">
            <div class="card-body">
                <i class="bi bi-people display-4 text-success mb-3"></i>
                <h5>Live Events</h5>
                <p class="small text-muted">Recurring workshops, coaching calls, and community meetups.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center border-0 shadow-sm">
            <div class="card-body">
                <i class="bi bi-chat-dots display-4 text-info mb-3"></i>
                <h5>AI Content Guide</h5>
                <p class="small text-muted">Chat with your AI guide about any content — get deeper insights, ask questions, and explore further.</p>
            </div>
        </div>
    </div>
</div>

<div class="text-center mb-4">
    <a href="https://dswa.org" class="btn btn-primary btn-lg" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-2"></i>Visit DSWA Online Community
    </a>
</div>

<hr class="my-4">

<div class="text-center mb-3">
    <h4 class="fw-light">Your Content Library</h4>
    <p class="text-muted small">Browse all content that's been recommended for your journey.</p>
</div>

<div class="text-center">
    <a href="index.php?page=content" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-1"></i>Back to My Content
    </a>
</div>

<?php } else { ?>
<!-- ── Content Detail ── -->
<nav class="mb-3">
    <a href="index.php?page=content" class="text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Back to My Content
    </a>
</nav>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <?php if ($thumb_url) { ?>
            <img src="<?= h($thumb_url) ?>" alt="" class="card-img-top" style="object-fit:contain;">
            <?php } ?>
            <div class="card-body">
                <div class="d-flex gap-2 mb-2 align-items-center">
                    <?php
                        $type = $item['type'] ?? 'content';
                        $type_color = ($type === 'activity') ? 'primary' : (($type === 'resource') ? 'info' : (($type === 'short-course') ? 'warning' : 'secondary'));
                    ?>
                    <span class="badge bg-<?= $type_color ?>"><?= h($type) ?></span>
                    <?php if (!empty($item['estimatedDuration'])) { ?>
                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= h($item['estimatedDuration']) ?></small>
                    <?php } ?>
                    <?php foreach ((array)($item['dimensions'] ?? []) as $dim) {
                        $dim_lower = strtolower($dim);
                        $dim_color = ($dim_lower === 'mindset') ? '#b388d9' : (($dim_lower === 'toolset') ? '#6ca6d9' : (($dim_lower === 'skillset') ? '#6bc96b' : '#e07878'));
                    ?>
                    <span class="badge" style="background:<?= $dim_color ?>20;color:<?= $dim_color ?>;"><?= h($dim) ?></span>
                    <?php } ?>
                </div>
                <h3 class="mb-2"><?= h($item['title'] ?? '') ?></h3>
                <p class="text-muted"><?= h($item['description'] ?? '') ?></p>

                <?php if (!empty($item['stages'])) { ?>
                <div class="mb-3">
                    <small class="text-muted fw-bold">Stages:</small>
                    <?php foreach ((array)$item['stages'] as $s) { ?>
                    <span class="badge bg-light text-dark"><?= h($s) ?></span>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if (!empty($item['tags'])) { ?>
                <div class="mb-3">
                    <?php foreach ((array)$item['tags'] as $tag) { ?>
                    <span class="badge bg-light text-muted me-1">#<?= h($tag) ?></span>
                    <?php } ?>
                </div>
                <?php } ?>

                <div class="d-flex gap-3 align-items-center border-top pt-3 mt-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input content-toggle-progress" type="checkbox"
                               data-item-id="<?= h($content_id) ?>"
                               <?= ($progress && $progress['status'] === 'started') ? 'checked' : '' ?>
                               <?= ($progress && $progress['status'] === 'completed') ? 'disabled' : '' ?>
                               id="detail-prog">
                        <label class="form-check-label" for="detail-prog">In Progress</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input content-toggle-complete" type="checkbox"
                               data-item-id="<?= h($content_id) ?>"
                               <?= ($progress && $progress['status'] === 'completed') ? 'checked' : '' ?>
                               id="detail-done">
                        <label class="form-check-label" for="detail-done">Completed</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- DSWA Community CTA -->
        <div class="card border-0 bg-primary bg-opacity-10 mb-4">
            <div class="card-body text-center py-4">
                <h5 class="mb-2"><i class="bi bi-compass me-2"></i>Get This Resource in the DSWA Online Community</h5>
                <p class="small text-muted mb-3">
                    Access this resource and share your experience with our online community of DSWA members.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="https://dswa.org" class="btn btn-primary" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Go to <?= h(ucwords(str_replace('-', ' ', $item['type'] ?? 'Resource'))) ?>
                    </a>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Not a DSWA member? Start your 30 day free trial today!</small>
                    <a href="https://dswa.org/register" class="small fw-bold" target="_blank" rel="noopener">Register</a>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Chat Column -->
    <?php
    // Common first-chat choices per content type
    $content_type = $item['type'] ?? 'short-course';
    $content_version = (int)($item['version'] ?? 1);
    $first_chat_choices = [
        'short-course' => [
            ['key' => 'overview',   'label' => 'Give me a quick overview'],
            ['key' => 'takeaways',  'label' => 'What are the key takeaways?'],
            ['key' => 'apply',      'label' => 'How do I apply this to my business?'],
            ['key' => 'time',       'label' => 'How long will this take?'],
        ],
        'module' => [
            ['key' => 'overview',   'label' => 'Give me a quick overview'],
            ['key' => 'takeaways',  'label' => 'What are the key takeaways?'],
            ['key' => 'apply',      'label' => 'How do I apply this to my business?'],
            ['key' => 'prereqs',    'label' => 'What should I know before starting?'],
        ],
        'video' => [
            ['key' => 'overview',   'label' => 'What is this video about?'],
            ['key' => 'takeaways',  'label' => 'What are the main points?'],
            ['key' => 'apply',      'label' => 'How can I use these ideas?'],
            ['key' => 'time',       'label' => 'How long is this?'],
        ],
        'worksheet' => [
            ['key' => 'overview',   'label' => 'What does this worksheet cover?'],
            ['key' => 'apply',      'label' => 'How do I use this worksheet?'],
            ['key' => 'takeaways',  'label' => 'What will I get out of it?'],
        ],
        'exercise' => [
            ['key' => 'overview',   'label' => 'What does this exercise involve?'],
            ['key' => 'apply',      'label' => 'How do I get started?'],
            ['key' => 'takeaways',  'label' => 'What skills will I build?'],
        ],
        'pdf-blueprint' => [
            ['key' => 'overview',   'label' => 'What is this blueprint about?'],
            ['key' => 'takeaways',  'label' => 'What are the key steps?'],
            ['key' => 'apply',      'label' => 'How do I put this into practice?'],
        ],
        'recurring-event' => [
            ['key' => 'overview',   'label' => 'What happens at this event?'],
            ['key' => 'apply',      'label' => 'How should I prepare?'],
            ['key' => 'takeaways',  'label' => 'What will I learn?'],
            ['key' => 'time',       'label' => 'When is it and how long?'],
        ],
    ];
    $choices = $first_chat_choices[$content_type] ?? $first_chat_choices['short-course'];
    ?>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="position:sticky;top:20px;">
            <div class="card-header bg-transparent border-bottom">
                <h6 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Content Guide</h6>
                <small class="text-muted">Ask questions about this content</small>
            </div>
            <div class="card-body p-0">
                <div id="contentChatMessages" style="height:400px;overflow-y:auto;padding:16px;">
                    <div class="content-chat-bubble content-chat-bubble--bot">
                        Hi! I'm your content guide. I can help you explore
                        <strong><?= h($item['title'] ?? 'this content') ?></strong>,
                        answer questions, or connect it back to your assessment results.
                    </div>
                </div>
                <div id="contentChatChoices" class="px-3 pb-2">
                    <?php foreach ($choices as $ch) { ?>
                    <button class="content-chat-choice btn btn-sm btn-outline-primary mb-1 me-1"
                            data-choice-key="<?= h($ch['key']) ?>"
                            data-choice-label="<?= h($ch['label']) ?>"><?= h($ch['label']) ?></button>
                    <?php } ?>
                </div>
                <div class="border-top p-3">
                    <div class="d-flex gap-2">
                        <input type="text" id="contentChatInput" class="form-control form-control-sm"
                               placeholder="Ask about this content..." data-content-id="<?= h($content_id) ?>">
                        <button id="contentChatSend" class="btn btn-sm btn-primary">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var contentId = <?= json_encode($content_id) ?>;
    var contentVersion = <?= json_encode($content_version) ?>;
    var choicesArea = document.getElementById('contentChatChoices');

    // Toggle handlers (same as content.php)
    document.querySelectorAll('.content-toggle-progress').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            var action = this.checked ? 'content_start' : 'content_unstart';
            apiPost(action, { catalog_item_id: itemId }, function(resp) {
                if (resp.error) { toggle.checked = !toggle.checked; }
            });
        });
    });

    document.querySelectorAll('.content-toggle-complete').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var itemId = this.getAttribute('data-item-id');
            var progressToggle = document.querySelector('.content-toggle-progress');
            if (this.checked) {
                apiPost('content_complete', { catalog_item_id: itemId }, function(resp) {
                    if (resp.error) { toggle.checked = false; return; }
                    if (progressToggle) { progressToggle.checked = false; progressToggle.disabled = true; }
                });
            } else {
                apiPost('content_uncomplete', { catalog_item_id: itemId }, function(resp) {
                    if (resp.error) { toggle.checked = true; return; }
                    if (progressToggle) { progressToggle.disabled = false; }
                });
            }
        });
    });

    // Content chat
    var messagesDiv = document.getElementById('contentChatMessages');
    var chatInput = document.getElementById('contentChatInput');
    var chatSend = document.getElementById('contentChatSend');

    if (!chatInput || !chatSend) return;

    function hideChoices() {
        if (choicesArea) choicesArea.style.display = 'none';
    }

    function addBubble(text, isBot) {
        var div = document.createElement('div');
        div.className = 'content-chat-bubble content-chat-bubble--' + (isBot ? 'bot' : 'user');
        if (isBot) {
            var html = text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
            div.innerHTML = html;
        } else {
            div.textContent = text;
        }
        messagesDiv.appendChild(div);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function showTyping() {
        var div = document.createElement('div');
        div.className = 'content-chat-bubble content-chat-bubble--bot content-chat-typing';
        div.innerHTML = '<div class="chat-typing"><div class="chat-typing__dot"></div><div class="chat-typing__dot"></div><div class="chat-typing__dot"></div></div>';
        div.id = 'contentChatTyping';
        messagesDiv.appendChild(div);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function hideTyping() {
        var t = document.getElementById('contentChatTyping');
        if (t) t.remove();
    }

    function sendMessage() {
        var text = chatInput.value.trim();
        if (!text) return;
        hideChoices();
        addBubble(text, false);
        chatInput.value = '';
        chatSend.disabled = true;
        chatInput.disabled = true;
        showTyping();

        apiPost('content_chat', { content_id: contentId, message: text }, function(resp) {
            hideTyping();
            chatSend.disabled = false;
            chatInput.disabled = false;
            chatInput.focus();
            if (resp.results && resp.results.reply) {
                addBubble(resp.results.reply, true);
            } else if (resp.error) {
                addBubble('Sorry: ' + resp.error, true);
            } else {
                addBubble('Sorry, I couldn\'t process that. Please try again.', true);
            }
        });
    }

    // Choice button handler — uses cache-aware endpoint
    function sendChoice(choiceKey, choiceLabel) {
        hideChoices();
        addBubble(choiceLabel, false);
        chatSend.disabled = true;
        chatInput.disabled = true;
        showTyping();

        apiPost('content_chat_choice', {
            content_id: contentId,
            choice_key: choiceKey,
            choice_label: choiceLabel,
            content_version: contentVersion
        }, function(resp) {
            hideTyping();
            chatSend.disabled = false;
            chatInput.disabled = false;
            chatInput.focus();
            if (resp.results && resp.results.reply) {
                addBubble(resp.results.reply, true);
            } else if (resp.error) {
                addBubble('Sorry: ' + resp.error, true);
            } else {
                addBubble('Sorry, I couldn\'t process that. Please try again.', true);
            }
        });
    }

    // Wire up choice buttons
    if (choicesArea) {
        choicesArea.querySelectorAll('.content-chat-choice').forEach(function(btn) {
            btn.addEventListener('click', function() {
                sendChoice(this.getAttribute('data-choice-key'), this.getAttribute('data-choice-label'));
            });
        });
    }

    chatSend.addEventListener('click', sendMessage);
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Load persisted chat history — hide choices if history exists
    (function loadHistory() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '../api/index.php?action=content_chat_history&content_id=' + encodeURIComponent(contentId));
        xhr.onload = function() {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.results && resp.results.messages && resp.results.messages.length > 0) {
                    hideChoices();
                    resp.results.messages.forEach(function(msg) {
                        addBubble(msg.message, msg.role === 'assistant');
                    });
                }
            } catch(e) { /* ignore */ }
        };
        xhr.send();
    })();
})();
</script>
<?php } ?>
