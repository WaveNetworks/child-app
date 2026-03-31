/**
 * chat-overlay.js
 * ElevateHER Assessment Chat Overlay
 * Connects to the assessment_chat API action.
 * Renders MC choices, cascading petal drops, and content banners with thumbnails.
 */
(function() {
'use strict';

var mount = document.getElementById('chat-overlay-mount');
if (!mount) { console.error('[chat] #chat-overlay-mount not found — chat-overlay.js aborting'); return; }

console.group('[chat] ── Chat Overlay Init ──');
console.log('[chat] mount element found:', mount);

var sessionId = mount.getAttribute('data-session-id') || '';
var phase = mount.getAttribute('data-phase') || 'profile';
var hasSession = !!sessionId;
var logoUrl = mount.getAttribute('data-logo-url') || '';
var siteName = mount.getAttribute('data-site-name') || 'ElevateHER';

console.log('[chat] data-session-id:', sessionId || '(empty — will auto-start)');
console.log('[chat] data-phase:', phase);
console.log('[chat] data-has-profile:', mount.getAttribute('data-has-profile'));
console.log('[chat] data-auto-start:', mount.getAttribute('data-auto-start'));
console.log('[chat] hasSession:', hasSession);

// ─── Dimension Colors (matching DSWADemo) ────────────────────────────────

var isAdmin = document.body.getAttribute('data-is-admin') === '1';
var _debugStore = []; // Stores debug data per bot message index

var dimColors = {
    mindset:  { accent: '#b388d9', bg: 'rgba(179,136,217,0.15)', text: '#d4b8f0' },
    toolset:  { accent: '#6ca6d9', bg: 'rgba(108,166,217,0.15)', text: '#a3cef0' },
    skillset: { accent: '#6bc96b', bg: 'rgba(107,201,107,0.15)', text: '#a8e6a8' },
    Mindset:  { accent: '#b388d9', bg: 'rgba(179,136,217,0.15)', text: '#d4b8f0' },
    Toolset:  { accent: '#6ca6d9', bg: 'rgba(108,166,217,0.15)', text: '#a3cef0' },
    Skillset: { accent: '#6bc96b', bg: 'rgba(107,201,107,0.15)', text: '#a8e6a8' },
};

// ─── DOM References (elements defined in assessment.php) ─────────────────

var messagesEl = document.getElementById('chatMessages');
var typingEl = document.getElementById('chatTyping');
var inputBar = document.getElementById('chatInputBar');
var optionsArea = document.getElementById('chatOptionsArea');
var phaseBadge = document.getElementById('chatPhaseBadge');

var chatInput = document.getElementById('chatInput');
var chatSendBtn = document.getElementById('chatSendBtn');

// Verify all DOM elements found
var _domCheck = {chatMessages: !!messagesEl, chatTyping: !!typingEl, chatInputBar: !!inputBar,
    chatOptionsArea: !!optionsArea, chatPhaseBadge: !!phaseBadge, chatInput: !!chatInput, chatSendBtn: !!chatSendBtn};
var _domMissing = Object.keys(_domCheck).filter(function(k) { return !_domCheck[k]; });
if (_domMissing.length) {
    console.error('[chat] MISSING DOM elements:', _domMissing.join(', '));
} else {
    console.log('[chat] All DOM elements found ✓');
}
console.groupEnd();

// ─── Auto-start or Resume ────────────────────────────────────────────────

// Clear stale content from previous session on load
messagesEl.innerHTML = '';
optionsArea.innerHTML = '';

if (!hasSession) {
    console.group('[chat] ── Auto-Start Flow (no existing session) ──');
    console.log('[chat] Disabling input, will auto-start in 1500ms...');
    chatInput.disabled = true;
    chatSendBtn.disabled = true;

    function doAutoStart() {
        console.log('[chat] Calling assessment_start...');
        var _t0 = performance.now();
        chatApiPost('assessment_start', {}, function(resp) {
            var _elapsed = Math.round(performance.now() - _t0);
            console.log('[chat] assessment_start response (' + _elapsed + 'ms):', JSON.stringify(resp).substring(0, 500));
            if (resp.error) {
                console.error('[chat] assessment_start FAILED:', resp.error);
                addBotMessage('<span style="color:#e07878;">' + resp.error + '</span>');
                chatInput.disabled = false;
                chatSendBtn.disabled = false;
                console.groupEnd();
                return;
            }
            sessionId = resp.results.session_id;
            hasSession = true;
            console.log('[chat] Session created:', sessionId, '| phase:', resp.results.phase, '| resuming:', resp.results.resuming);
            chatInput.disabled = false;
            chatSendBtn.disabled = false;
            console.log('[chat] Sending [start] message to kick off assessment_chat...');
            console.groupEnd();
            sendMessage('[start]');
        });
    }

    setTimeout(doAutoStart, 100);
} else {
    // Existing session — load chat history from DB (no AI call needed)
    console.group('[chat] ── Resume Flow (session: ' + sessionId + ') ──');
    console.log('[chat] Loading chat history...');
    (function() {
        var _t0 = performance.now();
        chatApiPost('get_chat_history', {session_id: sessionId}, function(resp) {
            var _elapsed = Math.round(performance.now() - _t0);
            console.log('[chat] get_chat_history response (' + _elapsed + 'ms):', resp.error ? 'ERROR: ' + resp.error : 'OK');
            dismissSplash();
            var history = (resp.results && resp.results.history) || [];
            console.log('[chat] History messages:', history.length, '| phase:', (resp.results && resp.results.phase) || '(none)');
            if (history.length > 0) {
                // Show only last N messages; collapse older ones behind a button
                var VISIBLE_TAIL = 6;
                var hiddenCount = Math.max(0, history.length - VISIBLE_TAIL);

                if (hiddenCount > 0) {
                    var showMoreBar = document.createElement('div');
                    showMoreBar.className = 'chat-history-collapsed';
                    showMoreBar.innerHTML =
                        '<button class="chat-show-earlier-btn" id="chatShowEarlierBtn">' +
                        '<i class="bi bi-clock-history me-1"></i>Show ' + hiddenCount + ' earlier messages' +
                        '</button>';
                    messagesEl.appendChild(showMoreBar);

                    // Pre-build hidden messages (not yet in DOM)
                    var hiddenFragment = document.createDocumentFragment();
                    for (var h = 0; h < hiddenCount; h++) {
                        var hMsg = history[h];
                        var hDiv = document.createElement('div');
                        if (hMsg.role === 'user') {
                            hDiv.className = 'chat-bubble chat-bubble--user';
                            hDiv.textContent = hMsg.display;
                        } else {
                            hDiv.className = 'chat-bubble chat-bubble--bot';
                            hDiv.innerHTML = hMsg.display;
                        }
                        hiddenFragment.appendChild(hDiv);
                    }

                    showMoreBar.querySelector('#chatShowEarlierBtn').addEventListener('click', function() {
                        messagesEl.insertBefore(hiddenFragment, showMoreBar);
                        showMoreBar.remove();
                        scrollToBottom();
                    });
                }

                // Render only the tail (visible) messages
                for (var v = hiddenCount; v < history.length; v++) {
                    var msg = history[v];
                    var div = document.createElement('div');
                    if (msg.role === 'user') {
                        div.className = 'chat-bubble chat-bubble--user';
                        div.textContent = msg.display;
                    } else {
                        div.className = 'chat-bubble chat-bubble--bot';
                        div.innerHTML = msg.display;
                    }
                    messagesEl.appendChild(div);
                }

                // ── Show next question's choices from the matrix ──
                // Priority: server-provided next_choices (from question matrix) > last bot message choices
                var nextChoices = resp.results.next_choices || null;
                var lastBot = null;
                for (var i = history.length - 1; i >= 0; i--) {
                    if (history[i].role === 'assistant') { lastBot = history[i]; break; }
                }

                if (nextChoices && nextChoices.length) {
                    // Question matrix has a next MC question — show question prompt + choices
                    console.log('[chat] Showing', nextChoices.length, 'choices from next matrix question:',
                        resp.results.next_question_id || '(unknown)');
                    if (resp.results.next_question_prompt) {
                        addBotMessage(resp.results.next_question_prompt, null, {question: true});
                    }
                    showOptions(nextChoices, 'matrix');
                } else if (lastBot && lastBot.choices && lastBot.choices.length) {
                    // Fall back to whatever the last bot message had
                    var lastSource = lastBot.choices_source || 'ai';
                    console.log('[chat] Restoring', lastBot.choices.length, 'choices from last bot message (source: ' + lastSource + ')');
                    showOptions(lastBot.choices, lastSource);
                } else if (resp.results.next_question_prompt) {
                    // Free-form question — show the question prompt directly, no AI call needed
                    console.log('[chat] Free-form question available:',
                        resp.results.next_question_id || '(unknown)', '— showing prompt directly');
                    var promptDiv = document.createElement('div');
                    promptDiv.className = 'chat-bubble chat-bubble--bot';
                    promptDiv.innerHTML = resp.results.next_question_prompt;
                    messagesEl.appendChild(promptDiv);
                } else {
                    // No question available at all — re-engage AI as last resort
                    console.log('[chat] No question available — sending [resume] to re-engage AI');
                    console.groupEnd();
                    sendMessage('[resume]');
                    return; // sendMessage handles scrolling
                }

                scrollToBottom();
                // Update phase badge
                if (resp.results.phase) {
                    phase = resp.results.phase;
                    phaseBadge.textContent = phase;
                    var cz = document.getElementById('compassClickZone');
                    if (cz) cz.style.pointerEvents = (phase === 'profile') ? 'none' : 'auto';
                }
                console.log('[chat] History replay complete');
                console.groupEnd();
            } else {
                // No history yet — fall back to resume
                console.log('[chat] No history found — sending [resume]');
                console.groupEnd();
                sendMessage('[resume]');
            }
        });
    })();
}

// ─── API wrapper ─────────────────────────────────────────────────────────

function chatApiPost(action, data, callback) {
    var formData = new FormData();
    formData.append('action', action);
    for (var key in data) { formData.append(key, data[key]); }

    console.log('[chat] API >> POST', action, Object.keys(data).length ? data : '(no params)');
    var _t0 = performance.now();

    fetch('../api/index.php', { method: 'POST', body: formData })
        .then(function(r) {
            console.log('[chat] API << HTTP', r.status, r.statusText, 'for', action, '(' + Math.round(performance.now() - _t0) + 'ms)');
            if (!r.ok) {
                return r.text().then(function(body) {
                    console.error('[chat] API << Non-OK response body (first 500 chars):', body.substring(0, 500));
                    throw new Error('HTTP ' + r.status + ': ' + body.substring(0, 200));
                });
            }
            return r.text().then(function(body) {
                try {
                    return JSON.parse(body);
                } catch(e) {
                    console.error('[chat] API << JSON parse failed for', action, '— raw body (first 500 chars):', body.substring(0, 500));
                    throw new Error('Invalid JSON: ' + body.substring(0, 200));
                }
            });
        })
        .then(function(json) {
            if (json.error) {
                console.warn('[chat] API << ERROR from', action + ':', json.error);
            } else {
                var keys = json.results ? Object.keys(json.results) : [];
                console.log('[chat] API << OK for', action, '| result keys:', keys.join(', ') || '(empty)');
            }
            callback(json);
        })
        .catch(function(err) {
            console.error('[chat] API << FETCH FAILED for', action + ':', err.message || err);
            callback({ error: 'Connection error — please try again.' });
        });
}

// ─── Splash Overlay ─────────────────────────────────────────────────────

var splashEl = document.getElementById('chatSplash');

function dismissSplash() {
    if (!splashEl) return;
    splashEl.classList.add('chat-splash--hidden');
    setTimeout(function() {
        if (splashEl && splashEl.parentNode) splashEl.parentNode.removeChild(splashEl);
        splashEl = null;
    }, 700);
}

// ─── Chat Functions ──────────────────────────────────────────────────────

function addBotMessage(html, debugData, opts) {
    dismissSplash();
    typingEl.style.display = 'none';
    opts = opts || {};

    var div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--bot' + (opts.question ? ' chat-bubble--question' : '');
    div.innerHTML = html;

    // Admin debug icon
    if (isAdmin && debugData) {
        var idx = _debugStore.length;
        _debugStore.push(debugData);
        var dbgBtn = document.createElement('button');
        dbgBtn.className = 'chat-debug-btn';
        dbgBtn.title = debugData.vendor + ' / ' + debugData.model +
            ' — ' + (debugData.tokens ? (debugData.tokens.input + debugData.tokens.output) : 0) + ' tokens' +
            ' — $' + (debugData.cost_usd || 0).toFixed(6);
        dbgBtn.innerHTML = '<i class="bi bi-bug"></i>';
        dbgBtn.setAttribute('data-debug-idx', idx);
        dbgBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            showDebugModal(_debugStore[this.getAttribute('data-debug-idx')]);
        });
        div.style.position = 'relative';
        div.appendChild(dbgBtn);
    }

    messagesEl.appendChild(div);
    scrollToBottom();

    if (window.lps) {
        try { window.lps.pulse(); } catch(e) {}
        if (window.pulseRipple) try { window.pulseRipple(); } catch(e) {}
    }
}

function addUserMessage(text) {
    var div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--user';
    div.textContent = text;
    messagesEl.appendChild(div);
    scrollToBottom();
}

function showOptions(choices, source) {
    optionsArea.innerHTML = '';
    if (!choices || !choices.length) return;

    choices.forEach(function(c, i) {
        var btn = document.createElement('button');
        btn.className = 'chat-option-btn' + (source === 'ai' ? ' chat-option-btn--ai' : '');
        btn.textContent = c.label || c;
        btn.setAttribute('data-value', c.value || c.label || '');
        btn.style.animationDelay = (i * 0.08) + 's';
        btn.addEventListener('click', function() {
            var label = c.label || c;
            var val = c.value || label;
            optionsArea.querySelectorAll('.chat-option-btn').forEach(function(b) {
                b.style.opacity = '0';
                b.style.transform = 'scale(0.95)';
                b.style.pointerEvents = 'none';
            });
            setTimeout(function() {
                optionsArea.innerHTML = '';
                addUserMessage(label);
                sendMessageRaw(val, label);
            }, 50);
        });
        optionsArea.appendChild(btn);
    });
    // Force layout recalc — MutationObserver may miss the final state
    // if innerHTML='' and appendChild happen in the same microtask
    updateZoneLayout();
}

/**
 * Show a content recommendation banner with thumbnail — matching DSWADemo style.
 */
function showContentBanner(item) {
    if (!item) return;

    var dim = (item.dimensions && item.dimensions[0]) || 'toolset';
    var dimKey = dim.toLowerCase();
    var colors = dimColors[dimKey] || dimColors.toolset;

    var banner = document.createElement('div');
    banner.className = 'content-banner';

    // Build thumbnail HTML if available
    var thumbHtml = '';
    if (item.thumbnail) {
        thumbHtml =
            '<div class="content-banner__thumb">' +
                '<img src="' + item.thumbnail + '" alt="' + (item.title || '') + '" />' +
            '</div>';
    }

    banner.innerHTML =
        '<div class="content-banner__accent" style="background:' + colors.accent + ';"></div>' +
        thumbHtml +
        '<div class="content-banner__info">' +
            '<div class="content-banner__title">' + (item.title || '') + '</div>' +
            '<div class="content-banner__subtitle">' + (item.description || '').substring(0, 80) + '</div>' +
            '<div class="content-banner__meta">' +
                '<span class="content-banner__badge" style="background:' + colors.bg + ';color:' + (colors.text || colors.accent) + ';">' + dim + '</span>' +
                '<span class="content-banner__duration">' + (item.type || '') + (item.estimatedDuration ? ' · ' + item.estimatedDuration : '') + '</span>' +
            '</div>' +
            (item.reason ? '<div class="content-banner__reason" style="font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:4px;">' + item.reason + '</div>' : '') +
        '</div>' +
        '<div class="content-banner__cta">' +
            '<div class="content-banner__cta-label">View Training</div>' +
            '<div class="content-banner__cta-sub">DSWA Community</div>' +
            '<div class="content-banner__cta-arrow">\u203A</div>' +
        '</div>';

    banner.style.cursor = 'pointer';
    banner.addEventListener('click', function() {
        if (item.id) {
            window.location.href = 'index.php?page=content-detail&id=' + encodeURIComponent(item.id);
        } else if (item.url) {
            window.open(item.url, '_blank');
        }
    });

    optionsArea.appendChild(banner);
}

function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
    // Also ensure the last element in the options area is visible
    var lastOpt = optionsArea.lastElementChild;
    if (lastOpt) {
        lastOpt.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// ─── Admin Debug Modal ──────────────────────────────────────────────────

function showDebugModal(d) {
    if (!d) return;
    var modal = document.getElementById('aiDebugModal');
    if (!modal) return;

    // Header
    document.getElementById('aiDebugVendor').textContent = d.vendor || '—';
    document.getElementById('aiDebugModel').textContent = d.model || '—';

    // KPI bar
    document.getElementById('aiDbgVendor').textContent = d.vendor || '—';
    document.getElementById('aiDbgTokensIn').textContent = (d.tokens ? d.tokens.input : 0).toLocaleString();
    document.getElementById('aiDbgTokensOut').textContent = (d.tokens ? d.tokens.output : 0).toLocaleString();
    document.getElementById('aiDbgCost').textContent = '$' + (d.cost_usd || 0).toFixed(6);
    document.getElementById('aiDbgPhase').textContent = (d.phase_before || '?') + ' → ' + (d.phase_after || '?');
    document.getElementById('aiDbgSignal').textContent = d.phase_signal || 'continue';

    // Badges row
    var badges = document.getElementById('aiDebugBadges');
    var badgeHtml = '';
    if (d.mc_resolved) badgeHtml += '<span class="badge bg-info me-1">MC Resolved</span>';
    if (d.question_id) badgeHtml += '<span class="badge bg-secondary me-1">Q: ' + escHtml(d.question_id) + '</span>';
    if (d.inferred_count > 0) badgeHtml += '<span class="badge bg-success me-1">' + d.inferred_count + ' inferred</span>';
    if (d.kpi_count > 0) badgeHtml += '<span class="badge bg-warning text-dark me-1">' + d.kpi_count + ' KPIs</span>';
    if (d.session_notes_count > 0) badgeHtml += '<span class="badge bg-primary me-1">' + d.session_notes_count + ' notes</span>';
    if (d.profile_hints && Object.keys(d.profile_hints).length > 0) {
        badgeHtml += '<span class="badge bg-purple me-1" style="background:#b388d9!important;">profile: ' +
            escHtml(Object.keys(d.profile_hints).join(', ')) + '</span>';
    }
    badges.innerHTML = badgeHtml;

    // System prompt
    document.getElementById('aiDbgPrompt').textContent = d.system_prompt || '(no system prompt captured)';

    // Raw response — try to pretty-print if JSON
    var rawText = d.raw_ai_response || '(no response captured)';
    try {
        var parsed = JSON.parse(rawText);
        rawText = JSON.stringify(parsed, null, 2);
    } catch(e) { /* not JSON, show as-is */ }
    document.getElementById('aiDbgResponse').textContent = rawText;

    // Move modal to body to avoid stacking context issues with chat overlay
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    var bsModal = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
    bsModal.show();
}

function escHtml(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ─── Cascading Petal Drops ───────────────────────────────────────────────

function cascadeDrops(items) {
    if (!items || !items.length || !window.lps) return;
    var delay = 0;
    items.forEach(function(item) {
        if (item.dimension && item.stage) {
            setTimeout(function() {
                window.lps.animateProgressDrop(item.stage, item.dimension, item.progress || 0.5, item.id);
            }, delay);
            delay += 300;
        }
    });
}

// ─── Profile Field Modal (Getting to Know You card) ─────────────────────

var _profileChoices = null; // Cached choices by field

function loadProfileChoices(cb) {
    if (_profileChoices) { if (cb) cb(_profileChoices); return; }
    chatApiPost('get_profile_choices', {}, function(resp) {
        if (!resp.error && resp.results && resp.results.profile_choices) {
            _profileChoices = resp.results.profile_choices;
        }
        if (cb) cb(_profileChoices);
    });
}

function openProfileFieldModal(fieldKey, fieldLabel, currentValue) {
    loadProfileChoices(function(choices) {
        var fieldData = choices ? choices[fieldKey] : null;
        if (!fieldData) { console.warn('[chat] No choices for field:', fieldKey); return; }

        // Remove existing modal
        var old = document.getElementById('profileFieldModal');
        if (old) old.remove();

        var optionsHtml = '';
        (fieldData.choices || []).forEach(function(c) {
            // Extract display value from "field:value" format
            var val = (c.value || '').split(':')[1] || c.value || '';
            var isSelected = (currentValue && val === currentValue);
            optionsHtml +=
                '<label class="profile-modal__option' + (isSelected ? ' selected' : '') + '">' +
                '<input type="radio" name="profileFieldValue" value="' + (c.value || '').replace(/"/g, '&quot;') + '"' +
                (isSelected ? ' checked' : '') + '>' +
                '<span>' + (c.label || '') + '</span>' +
                '</label>';
        });

        var modal = document.createElement('div');
        modal.id = 'profileFieldModal';
        modal.className = 'profile-modal-overlay';
        modal.innerHTML =
            '<div class="profile-modal">' +
            '<div class="profile-modal__header">' +
            '<h6>' + fieldLabel + '</h6>' +
            '<button class="profile-modal__close" onclick="document.getElementById(\'profileFieldModal\').remove()">&times;</button>' +
            '</div>' +
            '<div class="profile-modal__prompt">' + (fieldData.prompt || '') + '</div>' +
            '<div class="profile-modal__options">' + optionsHtml + '</div>' +
            '<div class="profile-modal__message">' +
            '<textarea id="profileFieldMessage" placeholder="Add a note (optional)..." rows="2"></textarea>' +
            '</div>' +
            '<div class="profile-modal__footer">' +
            '<button class="profile-modal__cancel" onclick="document.getElementById(\'profileFieldModal\').remove()">Cancel</button>' +
            '<button class="profile-modal__submit" id="profileFieldSubmit">Save</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(modal);

        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });

        // Highlight selected option
        modal.querySelectorAll('.profile-modal__option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                modal.querySelectorAll('.profile-modal__option').forEach(function(o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
            });
        });

        // Submit handler
        document.getElementById('profileFieldSubmit').addEventListener('click', function() {
            var selected = modal.querySelector('input[name="profileFieldValue"]:checked');
            if (!selected) return;
            var fullValue = selected.value; // e.g. "primaryMotivation:financial_goals"
            var cleanValue = fullValue.split(':')[1] || fullValue;
            var message = (document.getElementById('profileFieldMessage').value || '').trim();
            // Get the selected choice label for display
            var selectedLabel = selected.closest('.profile-modal__option').querySelector('span').textContent;

            // Disable button
            this.disabled = true;
            this.textContent = 'Saving...';

            // Check if current chat options match this field (user answered the active question via modal)
            var currentOptionsMatchField = false;
            optionsArea.querySelectorAll('.chat-option-btn').forEach(function(btn) {
                if ((btn.getAttribute('data-value') || '').indexOf(fieldKey + ':') === 0) {
                    currentOptionsMatchField = true;
                }
            });

            chatApiPost('profile_field_update', {
                field: fieldKey,
                value: cleanValue,
                message: message || selectedLabel
            }, function(resp) {
                modal.remove();
                if (!resp.error && resp.results) {
                    if (currentOptionsMatchField) {
                        // This was the active question — advance by sending through normal chat flow
                        // Clear stale options first, then let sendMessageRaw handle the rest
                        optionsArea.innerHTML = '';
                        addUserMessage(message || selectedLabel);
                        // Send through assessment_chat to get the next question
                        _lastMessage = fullValue;
                        _retryCount = 0;
                        typingEl.style.display = 'block';
                        messagesEl.appendChild(typingEl);
                        scrollToBottom();
                        chatApiPost('assessment_chat', {session_id: sessionId, message: fullValue, display_label: message || selectedLabel}, handleApiResponse);
                    } else {
                        // Answered a different field — just update the card and confirm
                        var r = resp.results;
                        if (r.profile_progress) {
                            updateProfileCard(r.profile_progress);
                        }
                        addUserMessage(message || selectedLabel);
                        addBotMessage('Got it — <strong>' + fieldLabel + '</strong> updated! ' +
                            (r.profile_progress && r.profile_progress.collected >= r.profile_progress.needed
                                ? 'I have enough to personalize your assessment now.'
                                : 'Keep going — every detail helps me understand you better.'));
                        scrollToBottom();
                    }
                }
            });
        });
    });
}

function updateProfileCard(pp) {
    var collected = pp.collected || 0;
    var total = pp.total || 5;
    var needed = pp.needed || 3;
    var pct = Math.round((collected / total) * 100);
    var ready = collected >= needed;

    var itemsHtml = '';
    (pp.items || []).forEach(function(item) {
        var statusIcon = item.collected
            ? '<i class="bi bi-check-circle-fill" style="color:#6bc96b;"></i>'
            : '<i class="bi bi-circle" style="color:rgba(255,255,255,0.3);"></i>';
        var valueText = item.collected
            ? '<span style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-left:auto;">' + item.value + '</span>'
            : '';
        itemsHtml +=
            '<div class="profile-card-item" style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;border-radius:6px;transition:background 0.15s;" ' +
            'data-field="' + item.key + '" data-label="' + (item.label || '').replace(/"/g, '&quot;') + '" data-value="' + (item.value || '').replace(/"/g, '&quot;') + '">' +
            statusIcon +
            '<i class="bi ' + item.icon + '" style="color:rgba(255,255,255,0.5);font-size:0.8rem;"></i>' +
            '<span style="font-size:0.85rem;">' + item.label + '</span>' +
            valueText +
            '<i class="bi bi-pencil-square" style="margin-left:auto;color:rgba(255,255,255,0.25);font-size:0.75rem;"></i>' +
            '</div>';
    });

    var cardHtml =
        '<div class="chat-kpi-card">' +
        '<div class="chat-kpi-card__header" style="cursor:pointer;" onclick="var g=this.closest(\'.chat-kpi-card\').querySelector(\'.chat-kpi-group\');g.classList.toggle(\'open\');var b=g.querySelector(\'.chat-kpi-group__body\');b.style.display=g.classList.contains(\'open\')?\'block\':\'none\';this.querySelector(\'.chat-kpi-group__arrow\').style.transform=g.classList.contains(\'open\')?\'rotate(180deg)\':\'\';">' +
        '<i class="bi bi-person-lines-fill"></i> ' +
        '<span>Getting to Know You</span>' +
        '<span class="chat-kpi-card__count">' + collected + '/' + total + (ready ? ' — Ready!' : '') + '</span>' +
        '<i class="bi bi-chevron-down chat-kpi-group__arrow" style="margin-left:auto;transition:transform 0.2s;color:rgba(255,255,255,0.8);font-size:0.9rem;"></i>' +
        '</div>' +
        '<div class="chat-kpi-card__subtitle">' +
        (ready
            ? 'Great — I have enough to get started! Let\'s dive into your assessment.'
            : 'I need ' + (needed - collected) + ' more to personalize your assessment.') +
        '</div>' +
        '<div style="margin:8px 0;">' +
        '<div class="chat-kpi-bar-wrap">' +
        '<div class="chat-kpi-bar" style="width:' + pct + '%;background:' + (ready ? '#6bc96b' : '#c89a40') + ';transition:width 0.5s ease;"></div>' +
        '</div>' +
        '</div>' +
        '<div class="chat-kpi-group">' +
        '<div class="chat-kpi-group__body" style="display:none;">' +
        itemsHtml +
        '</div>' +
        '</div>' +
        '</div>';

    var ppEl = document.getElementById('chatProfileProgress');
    if (ppEl) {
        ppEl.innerHTML = cardHtml;
    } else {
        var div = document.createElement('div');
        div.id = 'chatProfileProgress';
        div.className = 'chat-bubble chat-bubble--bot chat-bubble--kpi';
        div.innerHTML = cardHtml;
        messagesEl.appendChild(div);
    }

    // Bind click handlers on profile items
    bindProfileItemClicks();
    scrollToBottom();
}

function bindProfileItemClicks() {
    var ppEl = document.getElementById('chatProfileProgress');
    if (!ppEl) return;
    ppEl.querySelectorAll('.profile-card-item').forEach(function(item) {
        item.onclick = function() {
            var field = this.getAttribute('data-field');
            var label = this.getAttribute('data-label');
            var value = this.getAttribute('data-value');
            openProfileFieldModal(field, label, value);
        };
    });
}

// ─── Deferred Question (shown after content banner is acknowledged) ──────

function appendContinueButton() {
    var btn = document.createElement('button');
    btn.className = 'content-continue-btn';
    btn.innerHTML = '<i class="bi bi-arrow-right me-1"></i>Continue Assessment';
    btn.onclick = function() {
        serveDeferredQuestion();
    };
    optionsArea.appendChild(btn);
    scrollToBottom();
}

function serveDeferredQuestion() {
    if (!_deferredQuestion) return;
    var dq = _deferredQuestion;
    _deferredQuestion = null;
    // Clear content banners and continue button
    optionsArea.querySelectorAll('.content-banner, .content-continue-btn, .growth-content-header').forEach(function(el) { el.remove(); });
    if (dq.bubble) addBotMessage(dq.bubble, null, {question: true});
    showOptions(dq.choices, dq.source);
    scrollToBottom();
}

// ─── Send Message ────────────────────────────────────────────────────────

function sendMessageRaw(text, displayLabel) {
    if (!text.trim()) { console.warn('[chat] sendMessageRaw: empty text, ignoring'); return; }
    if (!sessionId) { console.error('[chat] sendMessageRaw: no sessionId! Cannot send:', text); return; }
    // If a deferred question is waiting, serve it instead of calling the API
    if (_deferredQuestion) {
        addUserMessage(displayLabel || text);
        serveDeferredQuestion();
        return;
    }
    console.log('[chat] sendMessageRaw:', text.substring(0, 80));
    _lastMessage = text;
    _retryCount = 0;
    chatInput.value = '';
    // Show thinking bubble immediately
    typingEl.style.display = 'block';
    messagesEl.appendChild(typingEl);
    scrollToBottom();
    var payload = {session_id: sessionId, message: text};
    if (displayLabel && displayLabel !== text) { payload.display_label = displayLabel; }
    chatApiPost('assessment_chat', payload, handleApiResponse);
}

function sendMessage(text) {
    if (!text.trim()) { console.warn('[chat] sendMessage: empty text, ignoring'); return; }
    if (!sessionId) { console.error('[chat] sendMessage: no sessionId! Cannot send:', text); return; }
    // If a deferred question is waiting, serve it instead of calling the API
    if (_deferredQuestion && text !== '[start]' && text !== '[resume]') {
        addUserMessage(text);
        serveDeferredQuestion();
        return;
    }
    console.log('[chat] sendMessage:', text.substring(0, 80), '| session:', sessionId);
    if (text !== '[start]' && text !== '[resume]') {
        addUserMessage(text);
    }
    _lastMessage = text;
    _retryCount = 0;
    chatInput.value = '';

    // Show thinking bubble immediately — no reason to wait
    typingEl.style.display = 'block';
    messagesEl.appendChild(typingEl);
    scrollToBottom();

    // WP9: Pulse on every client-server interaction (send)
    if (window.lps) {
        try { window.lps.pulse(); } catch(e) {}
        if (window.pulseRipple) try { window.pulseRipple(); } catch(e) {}
    }

    chatApiPost('assessment_chat', {session_id: sessionId, message: text}, handleApiResponse);
}

var _retryCount = 0;
var _lastMessage = '';
var _deferredQuestion = null; // {choices, source, bubble} — held when content banner takes priority

function handleApiResponse(resp) {
    chatInput.focus();

    if (resp.error) {
        console.warn('[chat] handleApiResponse: error received:', resp.error, '| retry:', _retryCount + '/3');
        if (_retryCount < 3 && _lastMessage) {
            _retryCount++;
            var _delay = 1500 * _retryCount;
            console.log('[chat] Retrying in', _delay + 'ms (attempt', _retryCount + ')...');
            if (_retryCount === 1) {
                typingEl.style.display = 'block';
                messagesEl.appendChild(typingEl);
                scrollToBottom();
            }
            setTimeout(function() {
                chatApiPost('assessment_chat', {session_id: sessionId, message: _lastMessage}, handleApiResponse);
            }, _delay);
            return;
        }
        console.error('[chat] All retries exhausted. Showing error to user.');
        _retryCount = 0;
        typingEl.style.display = 'none';
        addBotMessage("I'm having a little trouble right now \u2014 could you try sending that again?");
        return;
    }
    _retryCount = 0;
    typingEl.style.display = 'none';

    var r = resp.results || {};
    console.group('[chat] ── handleApiResponse ──');
    console.log('[chat] phase:', r.phase || '(none)', '| display_message:', r.display_message ? r.display_message.substring(0, 80) + '...' : '(none)');
    console.log('[chat] choices:', r.choices ? r.choices.length : 0,
        '| inferred_items:', r.inferred_items ? r.inferred_items.length : 0,
        '| content_recs:', r.content_recommendations ? r.content_recommendations.length : 0);
    console.log('[chat] session_complete:', !!r.session_complete, '| hard_wall:', r.hard_wall ? 'present' : 'none');
    if (r.score_update) console.log('[chat] score_update:', JSON.stringify(r.score_update));
    if (r.placement) console.log('[chat] placement:', JSON.stringify(r.placement));
    if (r._debug) console.log('[chat] _debug keys:', Object.keys(r._debug).join(', '));

    // Update phase
    if (r.phase) {
        phase = r.phase;
        phaseBadge.textContent = phase;
        // Hide compass click zone during profile phase — it steals clicks from choices
        var cz = document.getElementById('compassClickZone');
        if (cz) cz.style.pointerEvents = (phase === 'profile') ? 'none' : 'auto';
    }

    // Display AI message
    if (r.display_message) {
        addBotMessage(r.display_message, r._debug || null);
    } else {
        console.warn('[chat] No display_message in response — nothing to show user');
    }

    // ── Profile progress card (before question, after response) ──
    // Only show if no hard wall / growth path will be shown
    var hasHardWall = r.hard_wall && phase !== 'profile';
    if (r.profile_progress && !hasHardWall) {
        var pp = r.profile_progress;
        var ppEl = document.getElementById('chatProfileProgress');
        var collected = pp.collected || 0;
        var total = pp.total || 5;
        var needed = pp.needed || 3;
        var allDone = collected >= total;

        if (ppEl) {
            // Card already exists — update in place (don't remove + re-add)
            // If all fields are collected, just leave it where it is (no re-render)
            if (!allDone) {
                updateProfileCard(pp);
            }
        } else {
            // First appearance — create the card
            updateProfileCard(pp);
        }
    }

    // Handle hard wall display — inline accordion in the chat flow (skip during profile phase)
    if (hasHardWall) {
        var blocking = null;
        if (typeof r.hard_wall === 'object') {
            for (var wid in r.hard_wall) {
                if (r.hard_wall[wid] && r.hard_wall[wid].currentlyBlocking) {
                    blocking = r.hard_wall[wid];
                    blocking.id = wid;
                    break;
                }
            }
        }
        if (blocking) {
            console.warn('[chat] HARD WALL blocking:', blocking.id);
            var kpiDetails = blocking.kpiDetails || [];
            if (kpiDetails.length) {
                var groups = {};
                var levelOrder = ['none', 'beginner', 'emerging', 'developing', 'mastery'];
                var levelColors = {none: '#e07878', beginner: '#e07878', emerging: '#c89a40', developing: '#6bc96b', mastery: '#6bc96b'};
                var levelIcons = {none: 'bi-circle', beginner: 'bi-arrow-up-circle', emerging: 'bi-arrow-up-circle-fill', developing: 'bi-check-circle', mastery: 'bi-star-fill'};

                kpiDetails.forEach(function(kpi) {
                    var lvl = kpi.currentLevel || 'none';
                    if (!groups[lvl]) groups[lvl] = [];
                    groups[lvl].push(kpi);
                });

                var totalKpi = kpiDetails.length;
                var met = kpiDetails.filter(function(k) {
                    var ci = levelOrder.indexOf(k.currentLevel || 'none');
                    var ri = levelOrder.indexOf(k.requiredLevel || 'developing');
                    return ci >= ri;
                }).length;

                var accordionHtml =
                    '<div class="chat-kpi-card">' +
                    '<div class="chat-kpi-card__header">' +
                    '<i class="bi bi-rocket-takeoff"></i> ' +
                    '<span>Your Growth Path</span>' +
                    '<span class="chat-kpi-card__count">' + met + '/' + totalKpi + ' achieved</span>' +
                    '</div>' +
                    '<div class="chat-kpi-card__subtitle">' +
                    'These are the skills to develop on your way to the next level. ' +
                    'The recommended content and coaching below can help!' +
                    '</div>';

                levelOrder.forEach(function(lvl) {
                    if (!groups[lvl]) return;
                    var color = levelColors[lvl] || '#888';
                    var icon = levelIcons[lvl] || 'bi-circle';
                    var items = groups[lvl];

                    accordionHtml +=
                        '<div class="chat-kpi-group">' +
                        '<button class="chat-kpi-group__toggle" onclick="this.parentElement.classList.toggle(\'open\')">' +
                        '<i class="bi ' + icon + '" style="color:' + color + ';"></i> ' +
                        '<span class="chat-kpi-group__level" style="color:' + color + ';">' + lvl + '</span>' +
                        '<span class="chat-kpi-group__badge">' + items.length + '</span>' +
                        '<i class="bi bi-chevron-down chat-kpi-group__arrow"></i>' +
                        '</button>' +
                        '<div class="chat-kpi-group__body">';

                    items.forEach(function(kpi) {
                        var curIdx = levelOrder.indexOf(kpi.currentLevel || 'none');
                        var reqIdx = levelOrder.indexOf(kpi.requiredLevel || 'developing');
                        if (curIdx < 0) curIdx = 0;
                        if (reqIdx < 0) reqIdx = 3;
                        var pct = reqIdx > 0 ? Math.min(100, Math.round((curIdx / reqIdx) * 100)) : 0;

                        accordionHtml +=
                            '<div class="chat-kpi-row">' +
                            '<div class="chat-kpi-label">' + (kpi.label || kpi.id) + '</div>' +
                            '<div class="chat-kpi-bar-wrap">' +
                            '<div class="chat-kpi-bar" style="width:' + pct + '%;background:' + color + ';"></div>' +
                            '</div>' +
                            '</div>';
                    });

                    accordionHtml += '</div></div>';
                });

                accordionHtml += '</div>';

                // Remove old card so updated version appears at current scroll position
                var existingKpi = document.getElementById('chatGrowthPath');
                if (existingKpi) existingKpi.remove();
                var div = document.createElement('div');
                div.id = 'chatGrowthPath';
                div.className = 'chat-bubble chat-bubble--bot chat-bubble--kpi';
                div.innerHTML = accordionHtml;
                messagesEl.appendChild(div);
                scrollToBottom();
            }
        }
    }

    // ── Cascade petal drops for every resolved item ──
    if (r.inferred_items && r.inferred_items.length && window.lps) {
        console.log('[chat] Cascading', r.inferred_items.length, 'petal drops:', r.inferred_items.map(function(i) { return i.id; }).join(', '));
        setTimeout(function() {
            cascadeDrops(r.inferred_items);
        }, 50);
    }

    // ── Content vs Choices: never show both at the same time ──
    var hasContentRecs = r.content_recommendations && r.content_recommendations.length > 0;
    var hasChoices = r.choices && r.choices.length > 0;

    if (hasContentRecs && hasChoices) {
        // Defer question + choices — show content banner first
        var src = r.choices_source || 'matrix';
        _deferredQuestion = { choices: r.choices, source: src, bubble: r.question_bubble || null };
        console.log('[chat] Content + choices coexist — deferring', r.choices.length, 'choices, showing content first');
    } else {
        // No conflict — show question bubble and choices normally
        if (r.question_bubble) {
            addBotMessage(r.question_bubble, null, {question: true});
        }
        if (hasChoices) {
            var src = r.choices_source || 'matrix';
            console.log('[chat] Rendering', r.choices.length, 'choices (source: ' + src + '):', r.choices.map(function(c) { return c.label || c; }).join(' | '));
            showOptions(r.choices, src);
        }
    }

    // ── Show ONE content recommendation (least-presented first) ──
    if (hasContentRecs) {
        var isGrowthRouted = r.content_recommendations.some(function(c) { return c.growth_routed; });
        console.log('[chat] Content recommendations:', r.content_recommendations.map(function(c) { return c.title; }).join(' | '),
            isGrowthRouted ? '(growth-routed)' : '');
        optionsArea.querySelectorAll('.content-banner').forEach(function(b) { b.remove(); });

        // Pick the least-presented item
        var counts = {};
        try { counts = JSON.parse(sessionStorage.getItem('bannerCounts') || '{}'); } catch(e) {}
        var picked = r.content_recommendations.reduce(function(best, item) {
            var key = item.id || item.title || '';
            var c = counts[key] || 0;
            if (!best || c < (counts[best.id || best.title || ''] || 0)) return item;
            return best;
        }, null);

        if (picked) {
            var pickedKey = picked.id || picked.title || '';
            counts[pickedKey] = (counts[pickedKey] || 0) + 1;
            try { sessionStorage.setItem('bannerCounts', JSON.stringify(counts)); } catch(e) {}
            console.log('[chat] Showing least-presented banner:', pickedKey, '(shown', counts[pickedKey], 'times)');

            var bannerDelay = 50;
            if (isGrowthRouted) {
                setTimeout(function() {
                    var header = document.createElement('div');
                    header.className = 'growth-content-header';
                    header.innerHTML =
                        '<div class="growth-content-header__icon"><i class="bi bi-rocket-takeoff"></i></div>' +
                        '<div class="growth-content-header__text">' +
                            '<div class="growth-content-header__title">Your Growth Path Content</div>' +
                            '<div class="growth-content-header__subtitle">Curated for the areas you want to develop</div>' +
                        '</div>';
                    optionsArea.appendChild(header);
                    showContentBanner(picked);
                    if (_deferredQuestion) appendContinueButton();
                }, bannerDelay);
            } else {
                setTimeout(function() {
                    showContentBanner(picked);
                    if (_deferredQuestion) appendContinueButton();
                }, bannerDelay);
            }
        }
    }

    // Handle session complete
    if (r.session_complete) {
        console.log('[chat] SESSION COMPLETE — loading final recommendations');
        loadContentRecommendations();
    }

    // Final scroll after all rendering (deferred to let DOM settle)
    setTimeout(scrollToBottom, 100);
    console.groupEnd();
}

// ─── Load Content Recommendations from Catalog ──────────────────────

function loadContentRecommendations() {
    chatApiPost('get_recommendations', {}, function(resp) {
        if (resp.error || !resp.results) return;
        var recs = resp.results.recommendations || [];
        if (!recs.length) return;
        // Clear existing banners, show only the least-presented one
        optionsArea.querySelectorAll('.content-banner').forEach(function(b) { b.remove(); });
        var counts = {};
        try { counts = JSON.parse(sessionStorage.getItem('bannerCounts') || '{}'); } catch(e) {}
        var picked = recs.reduce(function(best, item) {
            var key = item.id || item.title || '';
            var c = counts[key] || 0;
            if (!best || c < (counts[best.id || best.title || ''] || 0)) return item;
            return best;
        }, null);
        if (picked) {
            var pickedKey = picked.id || picked.title || '';
            counts[pickedKey] = (counts[pickedKey] || 0) + 1;
            try { sessionStorage.setItem('bannerCounts', JSON.stringify(counts)); } catch(e) {}
            showContentBanner(picked);
        }
    });
}

// Only load content recommendations if the session is already completed (score phase).
// During an active assessment, content appears contextually via handleApiResponse.
if (phase === 'score') {
    loadContentRecommendations();
}

// ─── Input Handlers ──────────────────────────────────────────────────────

chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(chatInput.value);
    }
});

chatSendBtn.addEventListener('click', function() {
    sendMessage(chatInput.value);
});

// ─── Cleanup on SPA navigation ──────────────────────────────────────────

function cleanup() {
    // Elements are inside #chat-overlay-mount which gets replaced by SPA nav
    // Just clear content to prevent stale state
    if (messagesEl) messagesEl.innerHTML = '';
    if (optionsArea) optionsArea.innerHTML = '';
}

window.addEventListener('spa-nav:loading', cleanup);

var observer = new MutationObserver(function() {
    if (!document.getElementById('chat-overlay-mount')) {
        cleanup();
        observer.disconnect();
    }
});
observer.observe(document.getElementById('content-dynamic') || document.body, { childList: true, subtree: true });

// ─── WP10: Dynamic 4-Zone Layout ────────────────────────────────────────
// Adjusts --interactive-max and --lotus-reserve based on interactive content

function updateZoneLayout() {
    var root = document.documentElement;
    var vh = window.innerHeight;
    var optionsCount = optionsArea ? optionsArea.children.length : 0;
    var hasBanners = optionsArea ? optionsArea.querySelectorAll('.content-banner').length > 0 : false;
    var explorerActive = document.querySelector('.chat-messages--explorer-active');

    if (explorerActive) {
        // Explorer takes full space, lotus reserve is minimal
        root.style.setProperty('--interactive-max', '0px');
        root.style.setProperty('--lotus-reserve', '160px');
        return;
    }

    if (optionsCount === 0) {
        // No interactive content — maximize chat, keep good lotus space
        root.style.setProperty('--interactive-max', '0px');
        root.style.setProperty('--lotus-reserve', '220px');
    } else if (optionsCount <= 2 && !hasBanners) {
        // Few MC choices — small interactive area
        root.style.setProperty('--interactive-max', '25vh');
        root.style.setProperty('--lotus-reserve', '200px');
    } else if (hasBanners) {
        // Content banners need more space
        root.style.setProperty('--interactive-max', '35vh');
        root.style.setProperty('--lotus-reserve', '180px');
    } else {
        // Many MC choices or mixed content
        root.style.setProperty('--interactive-max', '40vh');
        root.style.setProperty('--lotus-reserve', '180px');
    }
}

// Observe options area for content changes
if (optionsArea) {
    var zoneObserver = new MutationObserver(updateZoneLayout);
    zoneObserver.observe(optionsArea, { childList: true, subtree: true });
}
window.addEventListener('resize', updateZoneLayout);
updateZoneLayout();

})();
