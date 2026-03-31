/**
 * explorer-view.js
 * ElevateHER Explorer View — Stage tiles grid with dimension bars, hard wall dividers.
 * Integrates with LotusProgressSystem view state machine.
 */
(function() {
'use strict';

var container = null;
var lps = window.lps || null;
var frameworkSummary = null;
var userState = null;
var currentStageId = '00'; // Module-scope current stage for detail view access

// Dimension colors
var DIM_COLORS = {
    mindset:  { gradient: 'linear-gradient(90deg, #b388d9, #d4b8f0)', label: 'M', color: '#b388d9' },
    toolset:  { gradient: 'linear-gradient(90deg, #6ca6d9, #a3cef0)', label: 'T', color: '#6ca6d9' },
    skillset: { gradient: 'linear-gradient(90deg, #6bc96b, #a8e6a8)', label: 'S', color: '#6bc96b' },
};

// ─── Toggle Button ───────────────────────────────────────────────────────

function createToggleButton() {
    var existing = document.getElementById('explorerToggle');
    if (existing) return existing;

    var btn = document.createElement('button');
    btn.id = 'explorerToggle';
    btn.title = 'Explorer View';
    btn.innerHTML = '&#9678;'; // compass symbol
    btn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:1400;width:48px;height:48px;' +
        'border-radius:50%;border:1px solid rgba(255,255,255,0.15);background:rgba(0,0,0,0.4);' +
        'backdrop-filter:blur(10px);color:#e8ecf0;font-size:1.4rem;cursor:pointer;' +
        'transition:all 0.2s;display:flex;align-items:center;justify-content:center;pointer-events:auto;';
    btn.addEventListener('mouseenter', function() { btn.style.background = 'rgba(46,117,182,0.4)'; });
    btn.addEventListener('mouseleave', function() { btn.style.background = 'rgba(0,0,0,0.4)'; });
    btn.addEventListener('click', toggleExplorer);
    document.body.appendChild(btn);
    return btn;
}

var explorerOpen = false;

function toggleExplorer() {
    if (!container) buildExplorer();

    // Elements to hide/show
    var chatEls = [
        document.getElementById('chatMessages'),
        document.getElementById('chatInputBar'),
        document.getElementById('chatOptionsArea'),
        document.getElementById('chatContentArea'),
        document.getElementById('chatTyping'),
        document.getElementById('chatPhaseBadge'),
    ];
    var logoEl = document.getElementById('chatLogo');

    if (!explorerOpen) {
        // Show explorer, hide chat
        explorerOpen = true;
        container.style.display = 'block';
        container.style.opacity = '0';
        requestAnimationFrame(function() { container.style.opacity = '1'; });

        chatEls.forEach(function(el) { if (el) el.classList.add('chat-messages--explorer-active'); });
        if (logoEl) logoEl.classList.add('chat-logo--explorer-active');

        if (lps) {
            try { lps.setViewState('EXPLORER_OVERVIEW'); } catch(e) {}
            try { lps.canvas.transitionPetals('part'); } catch(e) {}
        }
    } else {
        // Hide explorer, show chat
        explorerOpen = false;
        container.style.opacity = '0';
        setTimeout(function() { container.style.display = 'none'; }, 300);

        chatEls.forEach(function(el) { if (el) el.classList.remove('chat-messages--explorer-active'); });
        if (logoEl) logoEl.classList.remove('chat-logo--explorer-active');

        if (lps) {
            try { lps.canvas.transitionPetals('close'); } catch(e) {}
            try { lps.setViewState('LOTUS_VIEW'); } catch(e) {}
        }

        // WP7: Relay pending reflections to chat
        relayReflectionsToChat();
    }
}

// ─── Build Explorer DOM ──────────────────────────────────────────────────

function buildExplorer() {
    container = document.createElement('div');
    container.id = 'explorer-container';
    container.style.cssText = 'display:none;pointer-events:auto;';

    var inner = document.createElement('div');
    inner.style.cssText = 'max-width:800px;margin:0 auto;display:flex;flex-direction:column;gap:12px;';

    // Header — centered branding like the demo
    var header = document.createElement('div');
    header.style.cssText = 'text-align:center;margin-bottom:16px;position:relative;';
    header.innerHTML =
        '<div style="font-size:1.4rem;font-weight:300;letter-spacing:2px;color:rgba(255,255,255,0.85);">Elevate<em>HER</em>&reg; Compass</div>' +
        '<div style="font-size:0.75rem;color:rgba(255,255,255,0.45);letter-spacing:1px;">Your DSWA Training Continuum</div>' +
        '<button id="explorerClose" style="position:absolute;top:0;right:0;background:none;border:none;color:rgba(255,255,255,0.5);' +
        'font-size:1.5rem;cursor:pointer;padding:4px 8px;line-height:1;">&times;</button>';
    inner.appendChild(header);

    // Fetch data and render
    loadDataAndRender(inner);

    container.appendChild(inner);
    var mount = document.getElementById('chat-overlay-mount');
    if (mount) {
        mount.style.position = 'relative';
        mount.appendChild(container);
    } else {
        document.body.appendChild(container);
    }

    document.getElementById('explorerClose').addEventListener('click', toggleExplorer);

    // Click outside inner content → close explorer or go back to overview
    container.addEventListener('click', function(e) {
        if (e.target === container) {
            // Check if we're in a detail view (inner has a back button)
            var backBtn = container.querySelector('.stage-detail-back');
            if (backBtn) {
                backBtn.click(); // Go back to explorer overview
            } else {
                toggleExplorer(); // Close explorer entirely
            }
        }
    });
}

function loadDataAndRender(inner) {
    // Use cached data if available
    if (frameworkSummary) {
        renderStages(inner, frameworkSummary, userState);
        return;
    }

    explorerApiPost('get_framework_summary', {}, function(resp) {
        if (resp.error || !resp.results || !resp.results.summary) {
            inner.innerHTML += '<p style="color:rgba(255,255,255,0.5);text-align:center;">Framework data not available.</p>';
            return;
        }
        frameworkSummary = resp.results.summary;

        explorerApiPost('get_lotus_state', {}, function(stateResp) {
            userState = (stateResp.results || {});
            renderStages(inner, frameworkSummary, userState);
        });
    });
}

function renderStages(inner, summary, state) {
    var stages = summary.stages || [];
    var hardWalls = summary.hardWalls || [];
    var placement = (state && state.placement) || {};
    var currentStage = (state && state.currentStage) || placement.placedStage || '00';
    currentStageId = currentStage; // Store at module scope for detail view
    var wallBlocking = placement.hardWallBlocking || null;

    // Build wall lookup
    var wallAfterStage = {};
    hardWalls.forEach(function(w) {
        if (w.from) wallAfterStage[w.from] = w;
    });

    stages.forEach(function(stg) {
        var stageNum = parseInt(stg.id, 10);
        var isCurrent = stg.id === currentStage;
        var isPast = parseInt(stg.id, 10) < parseInt(currentStage, 10);
        var isFuture = parseInt(stg.id, 10) > parseInt(currentStage, 10);

        // Stage tile
        var tile = document.createElement('div');
        tile.className = 'explorer-stage-tile' + (isPast ? ' explorer-ghost' : '') + (isCurrent ? ' explorer-current' : '') + (isFuture ? ' explorer-locked' : '');

        // Header row
        var headerRow = document.createElement('div');
        headerRow.className = 'explorer-stage-header';

        var numBadge = document.createElement('div');
        numBadge.className = 'explorer-stage-num';
        numBadge.textContent = stg.id;
        if (isCurrent) numBadge.style.background = 'rgba(46,117,182,0.8)';

        var info = document.createElement('div');
        info.className = 'explorer-stage-info';
        info.innerHTML = '<div class="explorer-stage-name">' + (stg.name || '') + '</div>' +
            '<div class="explorer-stage-tagline">' + (stg.tagline || '') + '</div>';

        headerRow.appendChild(numBadge);
        headerRow.appendChild(info);

        if (isCurrent) {
            var badge = document.createElement('span');
            badge.style.cssText = 'font-size:0.65rem;padding:2px 8px;border-radius:10px;background:rgba(46,117,182,0.3);color:#6ca6d9;margin-left:auto;white-space:nowrap;';
            badge.textContent = 'Current Stage';
            headerRow.appendChild(badge);
        }

        tile.appendChild(headerRow);

        // Dimension bars
        var dims = document.createElement('div');
        dims.className = 'explorer-dims';

        ['mindset', 'toolset', 'skillset'].forEach(function(dim) {
            var row = document.createElement('div');
            row.className = 'explorer-dim-row';

            var label = document.createElement('div');
            label.className = 'explorer-dim-label';
            label.style.color = DIM_COLORS[dim].color;
            label.textContent = DIM_COLORS[dim].label;

            var bar = document.createElement('div');
            bar.className = 'explorer-dim-bar';

            var fill = document.createElement('div');
            fill.className = 'explorer-dim-fill';
            fill.style.background = DIM_COLORS[dim].gradient;
            // Get score for this dimension from per-stage scores
            var pct = 0;
            if (state && state.scores && state.scores[stg.id]) {
                var stageScores = state.scores[stg.id];
                if (stageScores[dim] !== undefined) {
                    pct = Math.round(stageScores[dim] * 100);
                }
            }
            fill.style.width = pct + '%';

            bar.appendChild(fill);

            var pctLabel = document.createElement('div');
            pctLabel.className = 'explorer-dim-pct';
            pctLabel.textContent = pct + '%';

            row.appendChild(label);
            row.appendChild(bar);
            row.appendChild(pctLabel);
            dims.appendChild(row);
        });

        tile.appendChild(dims);

        // KPI details on tile
        if (stg.kpis && stg.kpis.length > 0) {
            var kpiWrap = document.createElement('div');
            kpiWrap.className = 'explorer-kpi-summary';
            var kpiTitle = document.createElement('div');
            kpiTitle.className = 'explorer-kpi-title';
            kpiTitle.textContent = 'Key Performance Indicators';
            kpiWrap.appendChild(kpiTitle);
            stg.kpis.forEach(function(k) {
                var row = document.createElement('div');
                row.className = 'explorer-kpi-row';
                var lvl = (k.currentLevel || 'unanswered').toLowerCase();
                // Past stages: show actual level if answered, but upgrade unanswered to "passed"
                // since user has already advanced beyond this stage
                if (isPast && (lvl === 'unanswered')) {
                    lvl = 'passed';
                }
                var lvlClass = lvl === 'mastery' ? 'kpi-lvl-mastery' : lvl === 'developing' ? 'kpi-lvl-developing' : lvl === 'beginner' ? 'kpi-lvl-beginner' : lvl === 'passed' ? 'kpi-lvl-passed' : 'kpi-lvl-unanswered';
                var lvlText = lvl === 'unanswered' ? '—' : lvl === 'passed' ? '✓' : lvl;
                row.innerHTML =
                    '<span class="explorer-kpi-metric">' + esc(k.metric) + '</span>' +
                    '<span class="explorer-kpi-level ' + lvlClass + '">' + esc(lvlText) + '</span>';
                kpiWrap.appendChild(row);
            });
            tile.appendChild(kpiWrap);
        }

        // Click to drill (placeholder for stage detail)
        tile.addEventListener('click', function() {
            showStageDetail(stg);
        });

        inner.appendChild(tile);

        // Hard wall divider after this stage
        if (wallAfterStage[stg.id]) {
            var wall = wallAfterStage[stg.id];
            var divider = document.createElement('div');
            divider.className = 'explorer-wall-divider';

            var line1 = document.createElement('div');
            line1.className = 'explorer-wall-line';
            var label = document.createElement('div');
            label.className = 'explorer-wall-label';
            label.textContent = '\uD83C\uDFDB ' + (wall.label || 'Hard Wall');
            var line2 = document.createElement('div');
            line2.className = 'explorer-wall-line';

            divider.appendChild(line1);
            divider.appendChild(label);
            divider.appendChild(line2);

            // Wall status
            var status = document.createElement('div');
            status.className = 'explorer-wall-status';
            if (wallBlocking && wallBlocking.label === wall.label) {
                status.className += ' wall-blocking';
                status.textContent = '\u23F8 Blocking — develop this area to advance';
            } else if (parseInt(currentStage, 10) > parseInt(stg.id, 10)) {
                status.className += ' wall-crossed';
                status.textContent = '\u2714 Crossed';
            } else {
                status.className += ' wall-close';
                status.textContent = 'Ahead';
            }
            divider.appendChild(status);

            inner.appendChild(divider);
        }
    });
}

// ─── Stage Detail ────────────────────────────────────────────────────────

// Track pending reflections for chat relay (WP7)
var pendingReflections = [];

function showStageDetail(stageSummary) {
    if (!container) return;
    var inner = container.querySelector('div');
    if (!inner) return;

    // Save scroll and content for back navigation
    var prevScrollTop = container.scrollTop;
    var prevHTML = inner.innerHTML;
    inner.innerHTML = '';

    // Show loading state
    var loading = document.createElement('div');
    loading.style.cssText = 'text-align:center;padding:40px;color:rgba(180,200,220,0.5);font-size:0.85rem;';
    loading.textContent = 'Loading stage detail\u2026';
    inner.appendChild(loading);

    if (lps) try { lps.setViewState('STAGE_DETAIL'); } catch(e) {}

    // Fetch full stage data from API
    explorerApiPost('get_stage_detail', { stage: stageSummary.id }, function(resp) {
        inner.innerHTML = '';

        if (resp.error || !resp.results || !resp.results.stage) {
            inner.innerHTML = '<p style="color:rgba(255,100,100,0.7);text-align:center;">Failed to load stage detail.</p>';
            return;
        }

        var stage, proficiency, responses, responseMeta, selfReported, questionLookup;
        try {
        stage = resp.results.stage;
        proficiency = resp.results.proficiency || {};
        responses = resp.results.responses || {};
        responseMeta = resp.results.responseMeta || {};
        selfReported = resp.results.selfReported || {};
        questionLookup = resp.results.questionLookup || {};

        // ── Back Button ──
        var back = document.createElement('button');
        back.className = 'stage-detail-back';
        back.innerHTML = '&#8592; All Stages';
        back.addEventListener('click', function() {
            inner.innerHTML = prevHTML;
            rebindTileClicks(inner);
            container.scrollTop = prevScrollTop;
            if (lps) try { lps.setViewState('EXPLORER_OVERVIEW'); } catch(e) {}
        });
        inner.appendChild(back);

        // ── Header Card ──
        var header = document.createElement('div');
        header.className = 'stage-detail-header';
        header.innerHTML =
            '<div class="stage-detail-title">' +
                '<div class="explorer-stage-num" style="background:rgba(46,117,182,0.8);">' + esc(stage.id) + '</div>' +
                '<div>' +
                    '<div class="stage-detail-name">' + esc(stage.name) + '</div>' +
                    '<div class="stage-detail-tagline">' + esc(stage.tagline) + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="stage-detail-desc">' + esc(stage.description) + '</div>';
        inner.appendChild(header);

        // ── About This Stage (behavior, belief, stuck, unlocks) ──
        var aboutSection = document.createElement('div');
        aboutSection.className = 'stage-detail-section';
        aboutSection.innerHTML = '<div class="stage-detail-section-title section-meta">About This Stage</div>';
        var metaBlocks = [
            { label: 'Typical Behavior', text: stage.behavior },
            { label: 'Core Belief', text: stage.belief },
            { label: 'What Stuck Looks Like', text: stage.stuck },
            { label: 'What This Stage Unlocks', text: stage.unlocks },
        ];
        metaBlocks.forEach(function(mb) {
            if (!mb.text) return;
            var block = document.createElement('div');
            block.className = 'stage-detail-meta-block';
            block.innerHTML =
                '<div class="stage-detail-meta-label">' + esc(mb.label) + '</div>' +
                '<div class="stage-detail-meta-text">' + esc(mb.text) + '</div>';
            aboutSection.appendChild(block);
        });
        inner.appendChild(aboutSection);

        // ── Diagnostic Items by Dimension (WP2) ──
        ['mindset', 'toolset', 'skillset'].forEach(function(dim) {
            var dimItems = (stage.items || []).filter(function(it) { return it.dimension === dim; });
            if (dimItems.length === 0) return;

            var section = document.createElement('div');
            section.className = 'stage-detail-section';
            section.innerHTML = '<div class="stage-detail-section-title dim-' + dim + '">' +
                dim.charAt(0).toUpperCase() + dim.slice(1) +
                ' <span style="opacity:0.4;font-weight:400;">(' + dimItems.length + ' items)</span></div>';

            dimItems.forEach(function(item) {
                var level = proficiency[item.id] || 'none';
                var isResolved = (level === 'developing' || level === 'mastery');

                var row = document.createElement('div');
                row.className = 'stage-detail-item stage-detail-item--interactive';

                var dot = document.createElement('div');
                dot.className = 'stage-detail-item-indicator ' + (isResolved ? 'resolved' : 'unresolved');

                var textDiv = document.createElement('div');
                textDiv.className = 'stage-detail-item-text';
                textDiv.innerHTML =
                    '<div class="stage-detail-item-label">' + esc(item.label) + '</div>' +
                    (item.sub ? '<div class="stage-detail-item-sub">' + esc(item.sub) + '</div>' : '');

                var chevron = document.createElement('div');
                chevron.className = 'stage-detail-item-chevron';
                chevron.innerHTML = '&#9654;';

                row.appendChild(dot);
                row.appendChild(textDiv);
                row.appendChild(chevron);

                // Expandable entry panel
                var entry = document.createElement('div');
                entry.className = 'stage-detail-entry';

                if (isResolved) {
                    // Resolved: show observation + "What worked well?" textarea
                    var obs = (responseMeta[item.id] || {}).observation || '';
                    entry.innerHTML =
                        (obs ? '<div class="stage-detail-entry__question">' + esc(obs) + '</div>' : '') +
                        '<div class="stage-detail-entry__question">What worked well? Any challenges along the way?</div>' +
                        '<div class="stage-detail-entry__thanks" data-item="' + esc(item.id) + '">Thank you for sharing your reflection!</div>' +
                        '<div class="stage-detail-entry__actions">' +
                            '<textarea class="stage-detail-entry__input" placeholder="Share your reflection\u2026" data-item="' + esc(item.id) + '">' +
                                esc((selfReported[item.id] || {}).text || '') + '</textarea>' +
                            '<button class="stage-detail-entry__save-btn" data-item="' + esc(item.id) + '">&#9654;</button>' +
                        '</div>' +
                        '<span class="stage-detail-entry__saved">Saved \u2714</span>';
                } else {
                    // Unresolved: show question from matrix + textarea
                    var question = questionLookup[item.id] || 'How do you feel about this area of your development?';
                    entry.innerHTML =
                        '<div class="stage-detail-entry__question">' + esc(question) + '</div>' +
                        '<div class="stage-detail-entry__thanks" data-item="' + esc(item.id) + '">Thank you for sharing your thoughts!</div>' +
                        '<div class="stage-detail-entry__actions">' +
                            '<textarea class="stage-detail-entry__input" placeholder="Share your thoughts\u2026" data-item="' + esc(item.id) + '">' +
                                esc((selfReported[item.id] || {}).text || '') + '</textarea>' +
                            '<button class="stage-detail-entry__save-btn" data-item="' + esc(item.id) + '">&#9654;</button>' +
                        '</div>' +
                        '<span class="stage-detail-entry__saved">Saved \u2714</span>';
                }

                // Toggle expand/collapse
                row.addEventListener('click', function() {
                    var isOpen = row.classList.contains('stage-detail-item--expanded');
                    // Close all other entries in this section
                    section.querySelectorAll('.stage-detail-item--expanded').forEach(function(el) {
                        el.classList.remove('stage-detail-item--expanded');
                    });
                    section.querySelectorAll('.stage-detail-entry--open').forEach(function(el) {
                        el.classList.remove('stage-detail-entry--open');
                    });
                    if (!isOpen) {
                        row.classList.add('stage-detail-item--expanded');
                        entry.classList.add('stage-detail-entry--open');
                    }
                });

                section.appendChild(row);
                section.appendChild(entry);
            });

            // Bind save buttons in this section
            bindSaveButtons(section);
            inner.appendChild(section);
        });

        // ── KPIs (WP3) ──
        if (stage.kpis && stage.kpis.length > 0) {
            var kpiSection = document.createElement('div');
            kpiSection.className = 'stage-detail-section';
            kpiSection.innerHTML = '<div class="stage-detail-section-title section-kpis">Key Performance Indicators</div>';

            var detailIsPast = parseInt(stageSummary.id, 10) < parseInt(currentStageId, 10);
            stage.kpis.forEach(function(kpi) {
                var level = (typeof kpi.currentLevel === 'object' && kpi.currentLevel)
                    ? (kpi.currentLevel.level || 'none') : (kpi.currentLevel || 'none');
                // Past stages: upgrade unanswered KPIs to "passed"
                if (detailIsPast && (level === 'none' || level === 'unanswered')) {
                    level = 'passed';
                }
                var levelClass = 'kpi-level-' + level;

                var kpiRow = document.createElement('div');
                kpiRow.className = 'stage-detail-kpi stage-detail-kpi--interactive';

                var kpiContent = document.createElement('div');
                kpiContent.style.flex = '1';
                var displayLevel = level === 'passed' ? '✓ PASSED' : level.toUpperCase();
                kpiContent.innerHTML =
                    '<div class="stage-detail-kpi-label">' + esc(kpi.metric) + '</div>' +
                    '<span class="stage-detail-kpi-level ' + levelClass + '">' + displayLevel + '</span>';

                var kpiChevron = document.createElement('div');
                kpiChevron.className = 'stage-detail-kpi-chevron';
                kpiChevron.innerHTML = '&#9654;';

                kpiRow.appendChild(kpiContent);
                kpiRow.appendChild(kpiChevron);

                // Expandable KPI detail
                var kpiEntry = document.createElement('div');
                kpiEntry.className = 'stage-detail-entry';
                kpiEntry.innerHTML =
                    '<div class="stage-detail-entry__question" style="margin-top:0;"><strong style="color:rgba(180,200,220,0.3);">Beginner:</strong> ' + esc(kpi.beginner) + '</div>' +
                    '<div class="stage-detail-entry__question"><strong style="color:rgba(200,154,64,0.6);">Developing:</strong> ' + esc(kpi.developing) + '</div>' +
                    '<div class="stage-detail-entry__question"><strong style="color:rgba(107,201,107,0.7);">Mastery:</strong> ' + esc(kpi.mastery) + '</div>';

                kpiRow.addEventListener('click', function() {
                    var isOpen = kpiRow.classList.contains('stage-detail-kpi--expanded');
                    kpiSection.querySelectorAll('.stage-detail-kpi--expanded').forEach(function(el) {
                        el.classList.remove('stage-detail-kpi--expanded');
                    });
                    kpiSection.querySelectorAll('.stage-detail-entry--open').forEach(function(el) {
                        el.classList.remove('stage-detail-entry--open');
                    });
                    if (!isOpen) {
                        kpiRow.classList.add('stage-detail-kpi--expanded');
                        kpiEntry.classList.add('stage-detail-entry--open');
                    }
                });

                kpiSection.appendChild(kpiRow);
                kpiSection.appendChild(kpiEntry);
            });

            inner.appendChild(kpiSection);
        }

        // ── Field Wisdom (WP4) ──
        if (stage.fieldWisdom && stage.fieldWisdom.length > 0) {
            stage.fieldWisdom.forEach(function(fw) {
                var wisdomDiv = document.createElement('div');
                wisdomDiv.className = 'stage-detail-section stage-detail-wisdom';
                var quotes = (fw.quotes || []).map(function(q) {
                    return '<em>&ldquo;' + esc(q) + '&rdquo;</em>';
                }).join('<br><br>');
                wisdomDiv.innerHTML =
                    '<div class="stage-detail-section-title" style="color:rgba(200,180,140,0.7);">\uD83C\uDF3F Field Wisdom</div>' +
                    '<div class="stage-detail-wisdom-text">' + quotes + '</div>' +
                    '<div class="stage-detail-wisdom-speaker">\u2014 ' + esc(fw.speaker || '') +
                        (fw.role ? ', ' + esc(fw.role) : '') +
                        (fw.source ? ' (' + esc(fw.source) + ')' : '') + '</div>';
                inner.appendChild(wisdomDiv);
            });
        }

        // ── Content & Training (WP5) ──
        if (stage.content && stage.content.length > 0) {
            var contentSection = document.createElement('div');
            contentSection.className = 'stage-detail-section';
            contentSection.innerHTML = '<div class="stage-detail-section-title section-content">\uD83D\uDCDA Content &amp; Training</div>' +
                '<div class="stage-detail-content-grid"></div>';
            var grid = contentSection.querySelector('.stage-detail-content-grid');

            stage.content.forEach(function(ci) {
                var dimColor = '#6ca6d9'; // default blue
                var dims = (ci.dimensions || []);
                if (dims.length > 0) {
                    var d = dims[0].toLowerCase();
                    if (d === 'mindset') dimColor = '#b388d9';
                    else if (d === 'skillset') dimColor = '#6bc96b';
                }

                var card = document.createElement('div');
                card.className = 'stage-detail-content-card';
                card.innerHTML =
                    '<div class="stage-detail-content-card__accent" style="background:' + dimColor + ';"></div>' +
                    '<div class="stage-detail-content-card__thumb">' +
                        (ci.thumbnail ? '<img src="' + esc(ci.thumbnail) + '" alt="">' : '<div style="width:100%;height:100%;background:rgba(255,255,255,0.04);border-radius:4px;"></div>') +
                    '</div>' +
                    '<div class="stage-detail-content-card__info">' +
                        '<div class="stage-detail-content-card__title">' + esc(ci.title || '') + '</div>' +
                        '<div class="stage-detail-content-card__meta">' +
                            (dims.length ? '<span class="stage-detail-content-card__badge" style="background:' + dimColor + '22;color:' + dimColor + ';">' + esc(dims[0]) + '</span>' : '') +
                            '<span class="stage-detail-content-card__duration">' + esc(ci.type || '') +
                                (ci.estimatedDuration ? ' \u00B7 ' + esc(ci.estimatedDuration) : '') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="stage-detail-content-card__arrow">\u203A</div>';

                card.addEventListener('click', function() {
                    showContentDetail(ci, inner, stage);
                });

                grid.appendChild(card);
            });

            // Community link
            var communityLink = document.createElement('div');
            communityLink.style.cssText = 'text-align:center;margin-top:12px;';
            communityLink.innerHTML = '<a href="https://dswa.org/community" target="_blank" rel="noopener" ' +
                'style="color:rgba(100,175,220,0.7);font-size:0.75rem;text-decoration:none;">' +
                'Explore more resources in the DSWA Community Catalog \u2192</a>';
            contentSection.appendChild(communityLink);

            inner.appendChild(contentSection);
        }

        container.scrollTop = 0;
        } catch(e) { console.error('[explorer] stage detail error:', e); }
    });
}

// ── Content Detail Overlay (WP6) ─────────────────────────────────────────

function showContentDetail(content, parentInner, stage) {
    if (!container) return;
    var inner = container.querySelector('div');
    if (!inner) return;

    var prevHTML = inner.innerHTML;
    var prevScroll = container.scrollTop;
    inner.innerHTML = '';

    // Back button
    var back = document.createElement('button');
    back.className = 'stage-detail-back';
    back.innerHTML = '&#8592; Back to ' + esc(stage.name || 'Stage');
    back.addEventListener('click', function() {
        inner.innerHTML = prevHTML;
        rebindContentClicks(inner, stage);
        container.scrollTop = prevScroll;
    });
    inner.appendChild(back);

    // Thumbnail gallery
    if (content.thumbnail) {
        var thumbGallery = document.createElement('div');
        thumbGallery.style.cssText = 'margin-bottom:16px;text-align:center;';
        thumbGallery.innerHTML = '<img src="' + esc(content.thumbnail) + '" alt="" style="max-width:100%;max-height:280px;border-radius:8px;' +
            'border:1px solid rgba(255,255,255,0.08);object-fit:contain;">';
        inner.appendChild(thumbGallery);
    }

    // Title + meta
    var detailHeader = document.createElement('div');
    detailHeader.className = 'stage-detail-header';
    var dims = content.dimensions || [];
    var dimBadges = dims.map(function(d) {
        var col = '#6ca6d9';
        if (d.toLowerCase() === 'mindset') col = '#b388d9';
        else if (d.toLowerCase() === 'skillset') col = '#6bc96b';
        return '<span class="stage-detail-content-card__badge" style="background:' + col + '22;color:' + col + ';padding:2px 8px;">' + esc(d) + '</span>';
    }).join(' ');
    var stageBadges = (content.stages || []).map(function(s) {
        return '<span class="stage-detail-content-card__badge" style="background:rgba(46,117,182,0.2);color:#6ca6d9;padding:2px 8px;">Stage ' + esc(s) + '</span>';
    }).join(' ');

    detailHeader.innerHTML =
        '<div class="stage-detail-name" style="margin-bottom:6px;">' + esc(content.title || '') + '</div>' +
        (content.description ? '<div class="stage-detail-desc" style="margin-top:0;margin-bottom:10px;">' + esc(content.description) + '</div>' : '') +
        '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">' +
            stageBadges + dimBadges +
            '<span style="font-size:0.65rem;color:rgba(180,200,220,0.4);">' +
                esc(content.type || '') + (content.estimatedDuration ? ' \u00B7 ' + esc(content.estimatedDuration) : '') +
            '</span>' +
        '</div>' +
        (content.version ? '<div style="font-size:0.6rem;color:rgba(180,200,220,0.25);margin-top:8px;">v' + content.version +
            (content.contentHash ? ' \u00B7 ' + content.contentHash : '') +
            (content.dateUpdated ? ' \u00B7 Updated ' + content.dateUpdated : '') + '</div>' : '');
    inner.appendChild(detailHeader);

    // Learning Objectives (mapped to diagnostic items)
    if (content.itemRefs && content.itemRefs.length > 0) {
        var objSection = document.createElement('div');
        objSection.className = 'stage-detail-section';
        objSection.innerHTML = '<div class="stage-detail-section-title" style="color:rgba(200,180,140,0.7);">Learning Objectives</div>';
        content.itemRefs.forEach(function(ref) {
            var p = document.createElement('div');
            p.className = 'stage-detail-meta-text';
            p.style.cssText = 'padding:4px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.78rem;';
            p.innerHTML = '<span style="color:rgba(100,175,220,0.6);margin-right:6px;">\u2022</span>' + esc(ref);
            objSection.appendChild(p);
        });
        inner.appendChild(objSection);
    }

    // Tags
    if (content.tags && content.tags.length > 0) {
        var tagDiv = document.createElement('div');
        tagDiv.style.cssText = 'display:flex;flex-wrap:wrap;gap:5px;margin-top:10px;';
        content.tags.forEach(function(tag) {
            var span = document.createElement('span');
            span.style.cssText = 'font-size:0.6rem;padding:2px 8px;border-radius:10px;background:rgba(255,255,255,0.05);color:rgba(180,200,220,0.4);';
            span.textContent = tag;
            tagDiv.appendChild(span);
        });
        inner.appendChild(tagDiv);
    }

    // CTA — link to DSWA Community
    var cta = document.createElement('div');
    cta.style.cssText = 'margin-top:20px;text-align:center;';
    if (content.url) {
        cta.innerHTML = '<a href="' + esc(content.url) + '" target="_blank" rel="noopener" ' +
            'style="display:inline-block;padding:10px 28px;background:rgba(46,117,182,0.3);border:1px solid rgba(46,117,182,0.4);' +
            'border-radius:20px;color:#6ca6d9;font-size:0.82rem;font-weight:600;text-decoration:none;transition:background 0.2s;">' +
            'View in DSWA Community \u2192</a>';
    } else {
        cta.innerHTML = '<span style="font-size:0.75rem;color:rgba(180,200,220,0.35);">Coming soon to the DSWA Community</span>';
    }
    inner.appendChild(cta);

    container.scrollTop = 0;
}

// ── Save Button Binding ──────────────────────────────────────────────────

function bindSaveButtons(container) {
    container.querySelectorAll('.stage-detail-entry__save-btn').forEach(function(btn) {
        // Enter key on textarea triggers save
        var entry = btn.closest('.stage-detail-entry');
        var textarea = entry ? entry.querySelector('textarea') : null;
        if (textarea) {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    btn.click();
                }
            });
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var itemId = btn.getAttribute('data-item');
            var entry = btn.closest('.stage-detail-entry');
            var textarea = entry ? entry.querySelector('textarea') : null;
            var text = textarea ? textarea.value.trim() : '';
            if (!text || !itemId) return;

            btn.disabled = true;
            btn.innerHTML = '\u2026';

            explorerApiPost('save_explorer_reflection', { item_id: itemId, reflection: text }, function(resp) {
                btn.disabled = false;
                btn.innerHTML = '&#9654;';
                if (resp.results && resp.results.saved) {
                    // Show thank-you bubble (persists)
                    var thanks = entry.querySelector('.stage-detail-entry__thanks[data-item="' + itemId + '"]');
                    if (thanks) {
                        thanks.classList.add('stage-detail-entry__thanks--visible');
                    }
                    // Track for chat relay
                    pendingReflections.push({ itemId: itemId, text: text });
                }
            });
        });
    });
}

// ── Rebind Helpers ───────────────────────────────────────────────────────

function rebindTileClicks(inner) {
    // Re-fetch and rebuild explorer on back to ensure fresh data + event listeners
    inner.innerHTML = '';
    var header = document.createElement('div');
    header.style.cssText = 'text-align:center;margin-bottom:16px;position:relative;';
    header.innerHTML =
        '<div style="font-size:1.4rem;font-weight:300;letter-spacing:2px;color:rgba(255,255,255,0.85);">Elevate<em>HER</em>&reg; Compass</div>' +
        '<div style="font-size:0.75rem;color:rgba(255,255,255,0.45);letter-spacing:1px;">Your DSWA Training Continuum</div>' +
        '<button id="explorerClose" style="position:absolute;top:0;right:0;background:none;border:none;color:rgba(255,255,255,0.5);' +
        'font-size:1.5rem;cursor:pointer;padding:4px 8px;line-height:1;">&times;</button>';
    inner.appendChild(header);
    document.getElementById('explorerClose').addEventListener('click', toggleExplorer);

    // Force re-fetch for fresh user data
    frameworkSummary = null;
    userState = null;
    loadDataAndRender(inner);
}

function rebindContentClicks(inner, stage) {
    // Re-bind content card click handlers after innerHTML restore
    inner.querySelectorAll('.stage-detail-content-card').forEach(function(card, idx) {
        if (stage.content && stage.content[idx]) {
            card.addEventListener('click', function() {
                showContentDetail(stage.content[idx], inner, stage);
            });
        }
    });
    // Re-bind save buttons
    bindSaveButtons(inner);
    // Re-bind item expand/collapse
    inner.querySelectorAll('.stage-detail-item--interactive').forEach(function(row) {
        var entry = row.nextElementSibling;
        if (entry && entry.classList.contains('stage-detail-entry')) {
            row.addEventListener('click', function() {
                var isOpen = row.classList.contains('stage-detail-item--expanded');
                var section = row.closest('.stage-detail-section');
                if (section) {
                    section.querySelectorAll('.stage-detail-item--expanded').forEach(function(el) { el.classList.remove('stage-detail-item--expanded'); });
                    section.querySelectorAll('.stage-detail-entry--open').forEach(function(el) { el.classList.remove('stage-detail-entry--open'); });
                }
                if (!isOpen) {
                    row.classList.add('stage-detail-item--expanded');
                    entry.classList.add('stage-detail-entry--open');
                }
            });
        }
    });
    // Re-bind KPI expand/collapse
    inner.querySelectorAll('.stage-detail-kpi--interactive').forEach(function(row) {
        var entry = row.nextElementSibling;
        if (entry && entry.classList.contains('stage-detail-entry')) {
            row.addEventListener('click', function() {
                var isOpen = row.classList.contains('stage-detail-kpi--expanded');
                var section = row.closest('.stage-detail-section');
                if (section) {
                    section.querySelectorAll('.stage-detail-kpi--expanded').forEach(function(el) { el.classList.remove('stage-detail-kpi--expanded'); });
                    section.querySelectorAll('.stage-detail-entry--open').forEach(function(el) { el.classList.remove('stage-detail-entry--open'); });
                }
                if (!isOpen) {
                    row.classList.add('stage-detail-kpi--expanded');
                    entry.classList.add('stage-detail-entry--open');
                }
            });
        }
    });
}

// ── HTML escape utility ──────────────────────────────────────────────────

function esc(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

// ─── WP7: Relay Reflections to Chat ──────────────────────────────────────

function relayReflectionsToChat() {
    if (pendingReflections.length === 0) return;
    var reflections = pendingReflections.slice(); // copy
    pendingReflections = [];

    // Build a summary message to send to the chat
    var summary = reflections.map(function(r) {
        return '[Self-assessment: ' + r.itemId + '] ' + r.text;
    }).join('\n');

    // Send to chat via the assessment_chat endpoint as a user message
    if (typeof chatApiPost === 'function') {
        chatApiPost('assessment_chat', {
            message: '[explorer-reflections]\n' + summary
        }, function(resp) {
            // AI will respond with "Here's what I noticed from your self-assessment" message
            // The chat-overlay handles rendering the response
        });
    }
}

// ─── WP9: Pulse wrapper for explorer API calls ──────────────────────────

function explorerApiPost(action, data, callback) {
    // Trigger lotus pulse + ripple on every explorer API interaction
    if (window.lps) {
        try { window.lps.pulse(); } catch(e) {}
        if (window.pulseRipple) try { window.pulseRipple(); } catch(e) {}
    }
    apiPost(action, data, callback);
}

// ─── Initialize ──────────────────────────────────────────────────────────

function init() {
    // Only show toggle on assessment and dashboard pages
    var page = new URLSearchParams(window.location.search).get('page') || 'assessment';
    if (page === 'dashboard' || page === 'assessment') {
        createToggleButton();
        addCompassClickZone();
    }

    // Get LPS reference when ready
    if (window.lps) {
        lps = window.lps;
    } else {
        window.addEventListener('lps-ready', function(e) {
            lps = e.detail || window.lps;
        });
    }
}

// Clickable zone over the compass/lotus center to toggle explorer
function addCompassClickZone() {
    var existing = document.getElementById('compassClickZone');
    if (existing) return;

    var zone = document.createElement('div');
    zone.id = 'compassClickZone';
    zone.title = 'Open Explorer';
    var sidebar = document.getElementById('sidebar');
    var sW = sidebar ? sidebar.offsetWidth : 0;
    var cx = sW + (window.innerWidth - sW) / 2;
    zone.style.cssText = 'position:fixed;bottom:15px;left:' + cx + 'px;transform:translateX(-50%);' +
        'width:40px;height:40px;z-index:10;cursor:pointer;border-radius:50%;pointer-events:auto;';
    zone.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleExplorer();
    });
    document.body.appendChild(zone);
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Reinitialize on SPA navigation
window.addEventListener('spa-nav:loaded', function() {
    // Remove old toggle and compass zone if page changed
    var existing = document.getElementById('explorerToggle');
    if (existing) existing.remove();
    var czExisting = document.getElementById('compassClickZone');
    if (czExisting) czExisting.remove();
    if (container) { container.remove(); container = null; }
    explorerOpen = false;
    frameworkSummary = null;
    userState = null;
    init();
});

})();
