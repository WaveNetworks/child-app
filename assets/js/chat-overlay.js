/**
 * chat-overlay.js
 * Chat overlay for the conversational view.
 *
 * Connects to the chat API action.
 * Renders MC choices, cascading petal drops, and content banners with thumbnails.
 *
 * Sections:
 *  1. Initialization & Configuration
 *  2. DOM References
 *  3. Auto-start / Resume Flow
 *  4. API Wrapper
 *  5. Splash Overlay
 *  6. Chat Message Rendering
 *  7. Options & Content Banners
 *  8. Scroll Management
 *  9. Admin Debug Modal
 * 10. Cascading Petal Drops
 * 11. Profile Field Modal
 * 12. Profile Card
 * 13. Deferred Questions
 * 14. Send Message
 * 15. API Response Handler
 * 16. Content Recommendations
 * 17. Input Handlers
 * 18. Cleanup / SPA Navigation
 * 19. Dynamic 4-Zone Layout
 */
(function() {
'use strict';

/* =========================================================================
 * 1. INITIALIZATION & CONFIGURATION
 * Mount element lookup, session data, admin flag, dimension colour palette.
 * ========================================================================= */

var mount = document.getElementById('chat-overlay-mount');
if (!mount) { console.error('[chat] #chat-overlay-mount not found — chat-overlay.js aborting'); return; }

/** @type {string} Active session ID (set on auto-start or from data attribute) */
var sessionId = mount.getAttribute('data-session-id') || '';

/** @type {string} Current assessment phase (profile, assessment, score) */
var phase = mount.getAttribute('data-phase') || 'profile';

/** @type {boolean} Whether a session already exists on page load */
var hasSession = !!sessionId;

/** @type {string} Logo URL for branding */
var logoUrl = mount.getAttribute('data-logo-url') || '';

/** @type {string} Site name for branding */
var siteName = mount.getAttribute('data-site-name') || 'App';

/** @type {boolean} True when the logged-in user is an admin (enables debug UI) */
var isAdmin = document.body.getAttribute('data-is-admin') === '1';

/** @type {Array<Object>} Debug payloads indexed per bot message (admin only) */
var _debugStore = [];

/**
 * Conditional debug logger — only outputs when the current user is an admin.
 * Replaces scattered console.log calls in production paths.
 * @param {...*} args - Arguments forwarded to console.log
 */
function dbg() { if (isAdmin) console.log.apply(console, arguments); }

/**
 * Dimension colour map — accent, background, and text colours keyed by
 * lowercase dimension name. Lookup uses `dim.toLowerCase()`.
 * @type {Object<string, {accent: string, bg: string, text: string}>}
 */
var dimColors = {
    mindset:  { accent: '#b388d9', bg: 'rgba(179,136,217,0.15)', text: '#d4b8f0' },
    toolset:  { accent: '#6ca6d9', bg: 'rgba(108,166,217,0.15)', text: '#a3cef0' },
    skillset: { accent: '#6bc96b', bg: 'rgba(107,201,107,0.15)', text: '#a8e6a8' },
};

/* =========================================================================
 * 2. DOM REFERENCES
 * All elements are defined in assessment.php and must exist at init time.
 * ========================================================================= */

/** @type {HTMLElement} Scrollable message container */
var messagesEl = document.getElementById('chatMessages');

/** @type {HTMLElement} Typing / thinking indicator bubble */
var typingEl = document.getElementById('chatTyping');

/** @type {HTMLElement} Bottom input bar wrapper */
var inputBar = document.getElementById('chatInputBar');

/** @type {HTMLElement} Area below messages for MC choices / content banners */
var optionsArea = document.getElementById('chatOptionsArea');

/** @type {HTMLElement} Phase badge label (profile / assessment / score) */
var phaseBadge = document.getElementById('chatPhaseBadge');

/** @type {HTMLInputElement} Free-text chat input */
var chatInput = document.getElementById('chatInput');

/** @type {HTMLButtonElement} Send button */
var chatSendBtn = document.getElementById('chatSendBtn');

// Verify all DOM elements found
var _domCheck = {chatMessages: !!messagesEl, chatTyping: !!typingEl, chatInputBar: !!inputBar,
    chatOptionsArea: !!optionsArea, chatPhaseBadge: !!phaseBadge, chatInput: !!chatInput, chatSendBtn: !!chatSendBtn};
var _domMissing = Object.keys(_domCheck).filter(function(k) { return !_domCheck[k]; });
if (_domMissing.length) {
    console.error('[chat] MISSING DOM elements:', _domMissing.join(', '));
} else {
    dbg('[chat] All DOM elements found');
}

/* =========================================================================
 * 3. AUTO-START / RESUME FLOW
 * If no session exists, call assessment_start then send [start].
 * If a session exists, load chat history and restore choices.
 * ========================================================================= */

// Clear stale content from previous session on load
messagesEl.innerHTML = '';
optionsArea.innerHTML = '';

if (!hasSession) {
    dbg('[chat] No existing session — will auto-start');
    chatInput.disabled = true;
    chatSendBtn.disabled = true;

    /**
     * Kicks off a new assessment session via the assessment_start API action,
     * then sends the [start] command to begin the chat flow.
     */
    function doAutoStart() {
        dbg('[chat] Calling assessment_start...');
        chatApiPost('assessment_start', {}, function(resp) {
            if (resp.error) {
                console.error('[chat] assessment_start FAILED:', resp.error);
                addBotMessage('<span style="color:#e07878;">' + resp.error + '</span>');
                chatInput.disabled = false;
                chatSendBtn.disabled = false;
                return;
            }
            sessionId = resp.results.session_id;
            hasSession = true;
            dbg('[chat] Session created:', sessionId, '| phase:', resp.results.phase, '| resuming:', resp.results.resuming);
            chatInput.disabled = false;
            chatSendBtn.disabled = false;
            sendMessage('[start]');
        });
    }

    setTimeout(doAutoStart, 100);
} else {
    // Existing session — load chat history from DB (no AI call needed)
    dbg('[chat] Resume flow — session:', sessionId);
    (function() {
        chatApiPost('get_chat_history', {session_id: sessionId}, function(resp) {
            dismissSplash();
            var history = (resp.results && resp.results.history) || [];
            dbg('[chat] History messages:', history.length, '| phase:', (resp.results && resp.results.phase) || '(none)');
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
                    dbg('[chat] Showing', nextChoices.length, 'choices from next matrix question:',
                        resp.results.next_question_id || '(unknown)');
                    if (resp.results.next_question_prompt) {
                        addBotMessage(resp.results.next_question_prompt, null, {question: true});
                    }
                    showOptions(nextChoices, 'matrix');
                } else if (resp.results.next_question_prompt) {
                    // Free-form question — show the question prompt directly, no AI call needed
                    dbg('[chat] Free-form question available:',
                        resp.results.next_question_id || '(unknown)', '— showing prompt directly');
                    var promptDiv = document.createElement('div');
                    promptDiv.className = 'chat-bubble chat-bubble--bot';
                    promptDiv.innerHTML = resp.results.next_question_prompt;
                    messagesEl.appendChild(promptDiv);
                } else {
                    // No question available at all — re-engage AI as last resort
                    dbg('[chat] No question available — sending [resume] to re-engage AI');
                    sendMessage('[resume]');
                    return; // sendMessage handles scrolling
                }

                scrollToBottom();
                // Ensure scroll happens after layout settles (choices/options may change layout)
                setTimeout(scrollToBottom, 100);
                setTimeout(scrollToBottom, 400);
                // Update phase badge
                if (resp.results.phase) {
                    phase = resp.results.phase;
                    phaseBadge.textContent = phase;
                    var cz = document.getElementById('compassClickZone');
                    if (cz) cz.style.pointerEvents = (phase === 'profile') ? 'none' : 'auto';
                }
                dbg('[chat] History replay complete');
            } else {
                // No history yet — fall back to resume
                dbg('[chat] No history found — sending [resume]');
                sendMessage('[resume]');
            }
        });
    })();
}

/* =========================================================================
 * 4. API WRAPPER
 * Single POST helper for all backend communication. Handles JSON parsing,
 * HTTP errors, and network failures uniformly.
 * ========================================================================= */

/**
 * Send a POST request to the assessment API.
 * @param {string}   action   - The API action name (e.g. 'assessment_chat')
 * @param {Object}   data     - Key/value pairs to send as FormData
 * @param {Function} callback - Called with the parsed JSON response (or an error object)
 */
function chatApiPost(action, data, callback) {
    var formData = new FormData();
    formData.append('action', action);
    for (var key in data) { formData.append(key, data[key]); }

    dbg('[chat] API >> POST', action, Object.keys(data).length ? data : '(no params)');

    fetch('../api/index.php', { method: 'POST', body: formData })
        .then(function(r) {
            dbg('[chat] API << HTTP', r.status, r.statusText, 'for', action);
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
                dbg('[chat] API << OK for', action);
            }
            callback(json);
        })
        .catch(function(err) {
            console.error('[chat] API << FETCH FAILED for', action + ':', err.message || err);
            callback({ error: 'Connection error — please try again.' });
        });
}

/* =========================================================================
 * 5. SPLASH OVERLAY
 * Full-screen loading splash dismissed once the first real content arrives.
 * ========================================================================= */

/** @type {HTMLElement|null} Splash overlay element (removed after dismiss) */
var splashEl = document.getElementById('chatSplash');

/**
 * Fade out and remove the splash overlay.
 * Safe to call multiple times — no-ops after the first dismiss.
 */
function dismissSplash() {
    if (!splashEl) return;
    splashEl.classList.add('chat-splash--hidden');
    setTimeout(function() {
        if (splashEl && splashEl.parentNode) splashEl.parentNode.removeChild(splashEl);
        splashEl = null;
    }, 700);
}

/* =========================================================================
 * 6. CHAT MESSAGE RENDERING
 * Functions to append bot and user message bubbles to the chat stream.
 * ========================================================================= */

/**
 * Append a bot message bubble to the chat stream.
 * Optionally attaches an admin debug button if debug data is provided.
 * @param {string}      html      - HTML content for the bubble
 * @param {Object|null} debugData - AI debug payload (admin only, optional)
 * @param {Object}      [opts]    - Options: {question: boolean} adds question styling
 */
function addBotMessage(html, debugData, opts) {
    dismissSplash();
    typingEl.style.display = 'none';
    opts = opts || {};

    var div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--bot' + (opts.question ? ' chat-bubble--question' : '');
    div.innerHTML = html;

    // Admin debug icon — shows vendor, model, token count, and cost on hover
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

    // Pulse the visualization on every bot message
    if (window.lps) {
        try { window.lps.pulse(); } catch(e) {}
        if (window.pulseRipple) try { window.pulseRipple(); } catch(e) {}
    }
}

/**
 * Append a user message bubble to the chat stream.
 * Uses textContent (not innerHTML) to prevent XSS from user input.
 * @param {string} text - Plain text message from the user
 */
function addUserMessage(text) {
    var div = document.createElement('div');
    div.className = 'chat-bubble chat-bubble--user';
    div.textContent = text;
    messagesEl.appendChild(div);
    scrollToBottom();
}

/* =========================================================================
 * 7. OPTIONS & CONTENT BANNERS
 * Renders MC choice buttons and content recommendation banners in the
 * interactive zone below the chat stream.
 * ========================================================================= */

/**
 * Render multiple-choice option buttons in the options area.
 * Clears any existing options first. Each button fades out siblings on click
 * before sending the selected value.
 * @param {Array<Object|string>} choices - Array of {label, value} objects or plain strings
 * @param {string}               source  - Origin of choices ('matrix' or 'ai')
 */
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
 * Show a content recommendation banner with thumbnail.
 * Banner is clickable and navigates to the content detail page or external URL.
 * @param {Object} item - Content item with id, title, description, thumbnail,
 *                         dimensions[], type, estimatedDuration, reason, url
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
            '<div class="content-banner__cta-sub">Community</div>' +
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

/* =========================================================================
 * 8. SCROLL MANAGEMENT
 * Debounced scroll-to-bottom using requestAnimationFrame so multiple rapid
 * calls (e.g. addBotMessage + showOptions + cascadeDrops) collapse into one.
 * ========================================================================= */

/** @type {number|null} Pending rAF handle for debounced scroll */
var _scrollRafId = null;

/**
 * Scroll the message container to the bottom.
 * Uses rAF-based debouncing so multiple rapid calls in the same frame
 * collapse into a single scroll operation.
 */
function scrollToBottom() {
    if (_scrollRafId) return; // already scheduled
    _scrollRafId = requestAnimationFrame(function() {
        _scrollRafId = null;
        messagesEl.scrollTop = messagesEl.scrollHeight + 200;
    });
}

/* =========================================================================
 * 9. ADMIN DEBUG MODAL
 * Populates and shows the Bootstrap modal with AI debug data (system prompt,
 * raw response, token counts, cost, phase transitions).
 * ========================================================================= */

/**
 * Show the admin debug modal with details about an AI response.
 * @param {Object} d - Debug data object (vendor, model, tokens, cost_usd,
 *                     phase_before, phase_after, phase_signal, question_id, etc.)
 */
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

    // Badges row — dynamic badges for resolved MCs, questions, inferred items, etc.
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

/**
 * Escape a string for safe HTML insertion (prevents XSS).
 * @param {string} str - Raw string to escape
 * @returns {string} HTML-escaped string
 */
function escHtml(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

/* =========================================================================
 * 10. CASCADING PETAL DROPS
 * Triggers animated petal drops on the visualization visualisation for each
 * resolved assessment item (dimension + stage + progress).
 * ========================================================================= */

/**
 * Trigger cascading petal drop animations with staggered timing.
 * Each item drops onto the visualization at 300 ms intervals.
 * @param {Array<Object>} items - Items with {dimension, stage, progress, id}
 */
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

/* =========================================================================
 * 11. PROFILE FIELD MODAL
 * "Getting to Know You" card — lets the user pick a profile value from a
 * modal built from server-provided choices, then submits via profile_field_update.
 * ========================================================================= */

/** @type {Object|null} Cached profile choices by field key */
var _profileChoices = null;

/**
 * Load profile choices from the server (cached after first call).
 * @param {Function} cb - Callback receiving the choices object (or null)
 */
function loadProfileChoices(cb) {
    if (_profileChoices) { if (cb) cb(_profileChoices); return; }
    chatApiPost('get_profile_choices', {}, function(resp) {
        if (!resp.error && resp.results && resp.results.profile_choices) {
            _profileChoices = resp.results.profile_choices;
        }
        if (cb) cb(_profileChoices);
    });
}

/**
 * Open a modal for the user to select a profile field value.
 * Builds radio-button options from cached choices, with optional note textarea.
 * On submit, calls profile_field_update and either advances the chat (if the
 * field matches the active question) or confirms inline.
 *
 * @param {string} fieldKey     - Profile field identifier (e.g. 'primaryMotivation')
 * @param {string} fieldLabel   - Human-readable field name for the modal header
 * @param {string} currentValue - Currently selected value (for pre-selection), or empty
 */
function openProfileFieldModal(fieldKey, fieldLabel, currentValue) {
    loadProfileChoices(function(choices) {
        var fieldData = choices ? choices[fieldKey] : null;
        if (!fieldData) { console.warn('[chat] No choices for field:', fieldKey); return; }

        // Remove existing modal if open
        var old = document.getElementById('profileFieldModal');
        if (old) old.remove();

        // ── Build radio-button option labels ──
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

        // ── Assemble modal DOM ──
        // Structure: overlay > modal card > header, prompt, options, textarea, footer
        var modal = document.createElement('div');
        modal.id = 'profileFieldModal';
        modal.className = 'profile-modal-overlay';
        modal.innerHTML =
            '<div class="profile-modal">' +
            /* --- Header with title and close button --- */
            '<div class="profile-modal__header">' +
            '<h6>' + fieldLabel + '</h6>' +
            '<button class="profile-modal__close" onclick="document.getElementById(\'profileFieldModal\').remove()">&times;</button>' +
            '</div>' +
            /* --- Prompt text from field config --- */
            '<div class="profile-modal__prompt">' + (fieldData.prompt || '') + '</div>' +
            /* --- Radio-button options --- */
            '<div class="profile-modal__options">' + optionsHtml + '</div>' +
            /* --- Optional note textarea --- */
            '<div class="profile-modal__message">' +
            '<textarea id="profileFieldMessage" placeholder="Add a note (optional)..." rows="2"></textarea>' +
            '</div>' +
            /* --- Footer with cancel / save buttons --- */
            '<div class="profile-modal__footer">' +
            '<button class="profile-modal__cancel" onclick="document.getElementById(\'profileFieldModal\').remove()">Cancel</button>' +
            '<button class="profile-modal__submit" id="profileFieldSubmit">Save</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(modal);

        // Close on overlay click (outside the modal card)
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });

        // Highlight selected option on click
        modal.querySelectorAll('.profile-modal__option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                modal.querySelectorAll('.profile-modal__option').forEach(function(o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
            });
        });

        // ── Submit handler ──
        document.getElementById('profileFieldSubmit').addEventListener('click', function() {
            var selected = modal.querySelector('input[name="profileFieldValue"]:checked');
            if (!selected) return;
            var fullValue = selected.value; // e.g. "primaryMotivation:financial_goals"
            var cleanValue = fullValue.split(':')[1] || fullValue;
            var message = (document.getElementById('profileFieldMessage').value || '').trim();
            // Get the selected choice label for display
            var selectedLabel = selected.closest('.profile-modal__option').querySelector('span').textContent;

            // Disable button to prevent double-submit
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

/* =========================================================================
 * 12. PROFILE CARD
 * Renders the "Getting to Know You" progress card with expandable item list,
 * progress bar, and click-to-edit bindings.
 * ========================================================================= */

/**
 * Render or update the profile progress card in the chat stream.
 * Shows collected/total fields, a progress bar, and expandable item list
 * with click-to-edit capability.
 * @param {Object} pp - Profile progress data: {collected, total, needed, items[]}
 */
function updateProfileCard(pp) {
    var collected = pp.collected || 0;
    var total = pp.total || 5;
    var needed = pp.needed || 3;
    var pct = Math.round((collected / total) * 100);
    var ready = collected >= needed;

    // ── Build individual field items ──
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

    // ── Assemble the card ──
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

    // Insert or update the card element
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

/**
 * Attach click-to-edit handlers on each profile card item row.
 * Opens the profile field modal pre-populated with the current value.
 */
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

/* =========================================================================
 * 13. DEFERRED QUESTIONS
 * When a content banner is shown, the next question is held in _deferredQuestion
 * and served only after the user clicks "Continue Assessment".
 * ========================================================================= */

/**
 * Append a "Continue Assessment" button after content banners.
 * Clicking it serves the deferred question.
 */
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

/**
 * Serve a deferred question — either resume from backend or show cached choices.
 * Clears content banners and the continue button first.
 */
function serveDeferredQuestion() {
    if (!_deferredQuestion) return;
    var dq = _deferredQuestion;
    _deferredQuestion = null;
    // Clear content banners and continue button
    optionsArea.querySelectorAll('.content-banner, .content-continue-btn, .growth-content-header').forEach(function(el) { el.remove(); });
    if (dq.resume) {
        // Backend suppressed the question — send [resume] to get it fresh
        sendMessage('[resume]');
    } else {
        if (dq.bubble) addBotMessage(dq.bubble, null, {question: true});
        showOptions(dq.choices, dq.source);
        scrollToBottom();
    }
}

/* =========================================================================
 * 14. SEND MESSAGE
 * Two entry points: sendMessage (user-typed) and sendMessageRaw (MC choice).
 * Both share prepareSend() for common setup: clearing input, showing typing
 * indicator, and scrolling to bottom.
 * ========================================================================= */

/** @type {number} Current retry count for failed API calls */
var _retryCount = 0;

/** @type {string} Last message sent (used for retry logic) */
var _lastMessage = '';

/** @type {Object|null} Deferred question held while content banner is shown */
var _deferredQuestion = null;

/**
 * Common setup for both sendMessage and sendMessageRaw.
 * Clears the text input, stores the message for retry, resets retry count,
 * shows the typing indicator, and scrolls to bottom.
 * @param {string} text - The message being sent
 */
function prepareSend(text) {
    _lastMessage = text;
    _retryCount = 0;
    chatInput.value = '';
    typingEl.style.display = 'block';
    messagesEl.appendChild(typingEl);
    scrollToBottom();
}

/**
 * Send a raw message value to the backend (used by MC choice buttons).
 * Does NOT add a user bubble — the caller is responsible for that.
 * Optionally sends a display_label for history rendering if it differs from the value.
 * @param {string} text         - Raw value to send (e.g. 'primaryMotivation:financial_goals')
 * @param {string} displayLabel - Human-readable label for chat history (optional)
 */
function sendMessageRaw(text, displayLabel) {
    if (!text.trim()) { console.warn('[chat] sendMessageRaw: empty text, ignoring'); return; }
    if (!sessionId) { console.error('[chat] sendMessageRaw: no sessionId! Cannot send:', text); return; }
    // If a deferred question is waiting (content was shown), continue to the next question
    if (_deferredQuestion) {
        optionsArea.querySelectorAll('.content-banner, .content-continue-btn, .growth-content-header').forEach(function(el) { el.remove(); });
        _deferredQuestion = null;
    }
    dbg('[chat] sendMessageRaw:', text.substring(0, 80));
    prepareSend(text);
    var payload = {session_id: sessionId, message: text};
    if (displayLabel && displayLabel !== text) { payload.display_label = displayLabel; }
    chatApiPost('assessment_chat', payload, handleApiResponse);
}

/**
 * Send a user-typed message to the backend.
 * Adds a user bubble, shows typing indicator, and posts to assessment_chat.
 * If a deferred question is waiting (and this is not a system command),
 * serves the deferred question instead.
 * @param {string} text - User's typed message
 */
function sendMessage(text) {
    if (!text.trim()) { console.warn('[chat] sendMessage: empty text, ignoring'); return; }
    if (!sessionId) { console.error('[chat] sendMessage: no sessionId! Cannot send:', text); return; }
    // If a deferred question is waiting, serve it instead of calling the API
    if (_deferredQuestion && text !== '[start]' && text !== '[resume]') {
        addUserMessage(text);
        serveDeferredQuestion();
        return;
    }
    dbg('[chat] sendMessage:', text.substring(0, 80), '| session:', sessionId);
    if (text !== '[start]' && text !== '[resume]') {
        addUserMessage(text);
    }
    prepareSend(text);

    // WP9: Pulse on every client-server interaction (send)
    if (window.lps) {
        try { window.lps.pulse(); } catch(e) {}
        if (window.pulseRipple) try { window.pulseRipple(); } catch(e) {}
    }

    chatApiPost('assessment_chat', {session_id: sessionId, message: text}, handleApiResponse);
}

/* =========================================================================
 * 15. API RESPONSE HANDLER
 * Central handler for assessment_chat responses. Updates phase, renders bot
 * messages, profile card, hard-wall KPI accordion, petal drops, content
 * banners, and MC choices.
 * ========================================================================= */

/**
 * Pick the least-presented item from an array, using sessionStorage to track
 * how many times each item has been shown. Increments the count for the
 * picked item and persists back to sessionStorage.
 * @param {Array<Object>} items - Array of items with id and/or title
 * @returns {{picked: Object|null, counts: Object}} The selected item and updated counts
 */
function pickLeastPresented(items) {
    var counts = {};
    try { counts = JSON.parse(sessionStorage.getItem('bannerCounts') || '{}'); } catch(e) {}
    var picked = items.reduce(function(best, item) {
        var key = item.id || item.title || '';
        var c = counts[key] || 0;
        if (!best || c < (counts[best.id || best.title || ''] || 0)) return item;
        return best;
    }, null);
    if (picked) {
        var pickedKey = picked.id || picked.title || '';
        counts[pickedKey] = (counts[pickedKey] || 0) + 1;
        try { sessionStorage.setItem('bannerCounts', JSON.stringify(counts)); } catch(e) {}
    }
    return { picked: picked, counts: counts };
}

/**
 * Handle the API response from assessment_chat.
 * Orchestrates all UI updates: phase badge, bot message, profile card,
 * hard-wall growth path, petal drops, content banners, MC choices,
 * and session completion.
 * @param {Object} resp - API response: {error, results: {phase, display_message, choices, ...}}
 */
function handleApiResponse(resp) {
    chatInput.focus();

    if (resp.error) {
        console.warn('[chat] handleApiResponse: error received:', resp.error, '| retry:', _retryCount + '/3');
        if (_retryCount < 3 && _lastMessage) {
            _retryCount++;
            var _delay = 1500 * _retryCount;
            dbg('[chat] Retrying in', _delay + 'ms (attempt', _retryCount + ')...');
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
    dbg('[chat] handleApiResponse — phase:', r.phase || '(none)',
        '| choices:', r.choices ? r.choices.length : 0,
        '| content_recs:', r.content_recommendations ? r.content_recommendations.length : 0,
        '| session_complete:', !!r.session_complete);

    // Update phase badge and compass click zone
    if (r.phase) {
        phase = r.phase;
        phaseBadge.textContent = phase;
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
        var allDone = collected >= total;

        if (ppEl) {
            if (!allDone) {
                updateProfileCard(pp);
            }
        } else {
            updateProfileCard(pp);
        }
    }

    // ── Handle hard wall display — inline KPI accordion in the chat flow ──
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

                        // Extract stage number from KPI ID (e.g. "s01-kpi-01" → "01")
                        var stageNum = (kpi.id || '').match(/^s(\d+)/);
                        stageNum = stageNum ? stageNum[1] : '';

                        accordionHtml +=
                            '<div class="chat-kpi-row chat-kpi-row--interactive" ' +
                            'onclick="openKpiDetail(\'' + (kpi.id || '').replace(/'/g, "\\'") + '\', \'' + stageNum + '\')" ' +
                            'style="cursor:pointer;">' +
                            '<div class="chat-kpi-label">' + (kpi.label || kpi.id) + '</div>' +
                            '<div class="chat-kpi-bar-wrap">' +
                            '<div class="chat-kpi-bar" style="width:' + pct + '%;background:' + color + ';"></div>' +
                            '</div>' +
                            '<i class="bi bi-chevron-right chat-kpi-row__chevron"></i>' +
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
        dbg('[chat] Cascading', r.inferred_items.length, 'petal drops');
        setTimeout(function() {
            cascadeDrops(r.inferred_items);
        }, 50);
    }

    // ── Content vs Choices: never show both at the same time ──
    var hasContentRecs = r.content_recommendations && r.content_recommendations.length > 0;
    var hasChoices = r.choices && r.choices.length > 0;

    // ── Show question bubble + choices ──
    if (r.question_bubble) {
        addBotMessage(r.question_bubble, null, {question: true});
    }
    if (hasChoices) {
        var src = r.choices_source || 'matrix';
        dbg('[chat] Rendering', r.choices.length, 'choices (source: ' + src + ')');
        showOptions(r.choices, src);
    }
    // If content is shown without choices, the backend suppressed the question.
    // "Continue Assessment" triggers a [resume] to get the next question fresh.
    if (hasContentRecs && !hasChoices && !r.session_complete) {
        _deferredQuestion = { resume: true };
    }

    // ── Show ONE content recommendation (least-presented first) ──
    if (hasContentRecs) {
        var isGrowthRouted = r.content_recommendations.some(function(c) { return c.growth_routed; });
        dbg('[chat] Content recommendations:', r.content_recommendations.length,
            isGrowthRouted ? '(growth-routed)' : '');
        optionsArea.querySelectorAll('.content-banner').forEach(function(b) { b.remove(); });

        var result = pickLeastPresented(r.content_recommendations);
        var picked = result.picked;

        if (picked) {
            dbg('[chat] Showing least-presented banner:', picked.id || picked.title || '');

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
        dbg('[chat] SESSION COMPLETE — loading final recommendations');
        loadContentRecommendations();
    }

    // Final scroll after all rendering (deferred to let DOM settle)
    setTimeout(scrollToBottom, 100);
}

/* =========================================================================
 * 16. CONTENT RECOMMENDATIONS
 * Loads post-assessment content recommendations from the catalog and shows
 * the least-presented item as a banner.
 * ========================================================================= */

/**
 * Fetch content recommendations from the server and display the
 * least-presented item as a content banner.
 * Used at session completion and when resuming a completed session.
 */
function loadContentRecommendations() {
    chatApiPost('get_recommendations', {}, function(resp) {
        if (resp.error || !resp.results) return;
        var recs = resp.results.recommendations || [];
        if (!recs.length) return;
        optionsArea.querySelectorAll('.content-banner').forEach(function(b) { b.remove(); });

        var result = pickLeastPresented(recs);
        if (result.picked) {
            showContentBanner(result.picked);
        }
    });
}

// Only load content recommendations if the session is already completed (score phase).
// During an active assessment, content appears contextually via handleApiResponse.
if (phase === 'score') {
    loadContentRecommendations();
}

/* =========================================================================
 * 17. INPUT HANDLERS
 * Keyboard (Enter to send) and click (send button) bindings.
 * ========================================================================= */

chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(chatInput.value);
    }
});

chatSendBtn.addEventListener('click', function() {
    sendMessage(chatInput.value);
});

/* =========================================================================
 * 18. CLEANUP / SPA NAVIGATION
 * Clears chat state when the user navigates away via SPA, and disconnects
 * the MutationObserver when the mount element is removed.
 * ========================================================================= */

/**
 * Clear chat DOM content to prevent stale state on SPA navigation.
 */
function cleanup() {
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

/* =========================================================================
 * 19. DYNAMIC 4-ZONE LAYOUT
 * Adjusts CSS custom properties --interactive-max and --visual-reserve based
 * on the current interactive content (MC choices, content banners, explorer).
 * Observed via MutationObserver on the options area and window resize.
 * ========================================================================= */

/**
 * Recalculate zone layout CSS variables based on current interactive content.
 * Sets --interactive-max and --visual-reserve on the document root to control
 * how much vertical space the interactive zone and visualization visualisation receive.
 */
function updateZoneLayout() {
    var root = document.documentElement;
    var optionsCount = optionsArea ? optionsArea.children.length : 0;
    var hasBanners = optionsArea ? optionsArea.querySelectorAll('.content-banner').length > 0 : false;
    var explorerActive = document.querySelector('.chat-messages--explorer-active');

    if (explorerActive) {
        // Explorer takes full space, visualization reserve is minimal
        root.style.setProperty('--interactive-max', '0px');
        root.style.setProperty('--visual-reserve', '160px');
        return;
    }

    if (optionsCount === 0) {
        // No interactive content — maximize chat, keep good visualization space
        root.style.setProperty('--interactive-max', '0px');
        root.style.setProperty('--visual-reserve', '220px');
    } else if (optionsCount <= 2 && !hasBanners) {
        // Few MC choices — small interactive area
        root.style.setProperty('--interactive-max', '25vh');
        root.style.setProperty('--visual-reserve', '200px');
    } else if (hasBanners) {
        // Content banners need more space
        root.style.setProperty('--interactive-max', '35vh');
        root.style.setProperty('--visual-reserve', '180px');
    } else {
        // Many MC choices or mixed content
        root.style.setProperty('--interactive-max', '40vh');
        root.style.setProperty('--visual-reserve', '180px');
    }

    // Keep chat scrolled to bottom after zone resize
    messagesEl.scrollTop = messagesEl.scrollHeight;
}

// Observe options area for content changes
if (optionsArea) {
    var zoneObserver = new MutationObserver(updateZoneLayout);
    zoneObserver.observe(optionsArea, { childList: true, subtree: true });
}
window.addEventListener('resize', updateZoneLayout);
updateZoneLayout();

})();
