<?php
/**
 * views/game.php
 * HTML5 Game demo — showcases the background canvas with a self-playing Pong game.
 * Toggle the game on/off and adjust settings. The game renders on #bgCanvas
 * (the same canvas used for the nebula background).
 *
 * REMOVAL: This is a demo feature. To remove it entirely, delete these 4 things:
 *   1. This file:               views/game.php
 *   2. The game script:         assets/js/pong-game.js
 *   3. The sidebar link:        views/template.php  (search "REMOVABLE: HTML5 Game")
 *   4. The script tag:          views/template.php  (search "REMOVABLE: Pong game")
 *   5. The route:               app/index.php       (search "REMOVABLE: HTML5 Game")
 *   No database tables, migrations, action files, or shared code to clean up.
 */
$page_title = 'HTML5 Game';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-controller me-2"></i>HTML5 Game</h4>
</div>

<div class="row g-4">
    <!-- Main control card -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-joystick me-1"></i> Self-Playing Pong</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="pongToggle" role="switch">
                    <label class="form-check-label small" for="pongToggle" id="pongToggleLabel">Off</label>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Toggle the switch to launch a self-playing Pong game on the background canvas.
                    Two AI players rally back and forth using the entire browser window as the court.
                    The game runs behind all UI elements using the same <code>#bgCanvas</code> layer
                    that normally shows the nebula gradient.
                </p>

                <div class="row g-3">
                    <!-- Ball Speed -->
                    <div class="col-md-6">
                        <label for="settingBallSpeed" class="form-label small">
                            <i class="bi bi-lightning-charge me-1"></i>Ball Speed
                        </label>
                        <input type="range" class="form-range" id="settingBallSpeed" min="2" max="10" step="0.5" value="5">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Slow</span>
                            <span class="text-muted small fw-bold" id="ballSpeedVal">5</span>
                            <span class="text-muted small">Fast</span>
                        </div>
                    </div>

                    <!-- Paddle Speed -->
                    <div class="col-md-6">
                        <label for="settingPaddleSpeed" class="form-label small">
                            <i class="bi bi-arrows-expand me-1"></i>Paddle Speed
                        </label>
                        <input type="range" class="form-range" id="settingPaddleSpeed" min="2" max="8" step="0.5" value="4">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Slow</span>
                            <span class="text-muted small fw-bold" id="paddleSpeedVal">4</span>
                            <span class="text-muted small">Fast</span>
                        </div>
                    </div>

                    <!-- AI Skill -->
                    <div class="col-md-6">
                        <label for="settingAI" class="form-label small">
                            <i class="bi bi-robot me-1"></i>AI Skill
                        </label>
                        <input type="range" class="form-range" id="settingAI" min="0.4" max="1.0" step="0.05" value="0.85">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Sloppy</span>
                            <span class="text-muted small fw-bold" id="aiVal">0.85</span>
                            <span class="text-muted small">Perfect</span>
                        </div>
                    </div>

                    <!-- Trail Length -->
                    <div class="col-md-6">
                        <label for="settingTrail" class="form-label small">
                            <i class="bi bi-stars me-1"></i>Trail Length
                        </label>
                        <input type="range" class="form-range" id="settingTrail" min="0" max="30" step="1" value="12">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">None</span>
                            <span class="text-muted small fw-bold" id="trailVal">12</span>
                            <span class="text-muted small">Long</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collapsible info cards -->
    <div class="col-12">
        <div class="accordion" id="gameInfoAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHowItWorks">
                        <i class="bi bi-info-circle me-2"></i> How It Works
                    </button>
                </h2>
                <div id="collapseHowItWorks" class="accordion-collapse collapse" data-bs-parent="#gameInfoAccordion">
                    <div class="accordion-body">
                        <div class="row">
                            <div class="col-md-3">
                                <dt class="text-muted small mb-1">Canvas</dt>
                                <dd class="small">The game renders on <code>#bgCanvas</code>, the full-viewport canvas element behind all UI. The nebula gradient and grid stay visible underneath.</dd>
                            </div>
                            <div class="col-md-3">
                                <dt class="text-muted small mb-1">AI Players</dt>
                                <dd class="small">Both paddles are controlled by AI that predicts the ball trajectory with adjustable imperfection. Lower skill = more missed shots and entertaining rallies.</dd>
                            </div>
                            <div class="col-md-3">
                                <dt class="text-muted small mb-1">Physics</dt>
                                <dd class="small">Ball angle changes based on where it hits the paddle. Speed increases slightly with each rally hit. Particle effects burst on every bounce.</dd>
                            </div>
                            <div class="col-md-3">
                                <dt class="text-muted small mb-1">Theming</dt>
                                <dd class="small">Switch between dark and light mode &mdash; the game adapts its color palette in real time. Try toggling the <i class="bi bi-moon-fill"></i> button while the game is running.</dd>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDevInfo">
                        <i class="bi bi-code-slash me-2"></i> For Developers
                    </button>
                </h2>
                <div id="collapseDevInfo" class="accordion-collapse collapse" data-bs-parent="#gameInfoAccordion">
                    <div class="accordion-body">
                        <p class="small text-muted mb-2">
                            This demonstrates the canvas background pattern described in <code>CLAUDE.md</code>:
                        </p>
                        <pre class="small mb-0"><code>// Start the game
window.pongGame.start();

// Stop and restore nebula
window.pongGame.stop();

// Adjust settings live
window.pongGame.settings.ballSpeed = 8;
window.pongGame.settings.aiReaction = 0.6;</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var toggle = document.getElementById('pongToggle');
    var label = document.getElementById('pongToggleLabel');

    if (!toggle || !window.pongGame) return;

    // Restore state from localStorage
    if (localStorage.getItem('wn_pong_active') === 'true') {
        toggle.checked = true;
        label.textContent = 'On';
        window.pongGame.start();
    }

    toggle.addEventListener('change', function() {
        if (this.checked) {
            window.pongGame.start();
            label.textContent = 'On';
            localStorage.setItem('wn_pong_active', 'true');
        } else {
            window.pongGame.stop();
            label.textContent = 'Off';
            localStorage.removeItem('wn_pong_active');
        }
    });

    // Settings sliders
    function bindSlider(id, displayId, settingKey) {
        var slider = document.getElementById(id);
        var display = document.getElementById(displayId);
        if (!slider || !display) return;

        // Restore from localStorage
        var saved = localStorage.getItem('wn_pong_' + settingKey);
        if (saved !== null) {
            slider.value = saved;
            display.textContent = saved;
            window.pongGame.settings[settingKey] = parseFloat(saved);
        }

        slider.addEventListener('input', function() {
            var val = parseFloat(this.value);
            display.textContent = val;
            window.pongGame.settings[settingKey] = val;
            localStorage.setItem('wn_pong_' + settingKey, val);
        });
    }

    bindSlider('settingBallSpeed', 'ballSpeedVal', 'ballSpeed');
    bindSlider('settingPaddleSpeed', 'paddleSpeedVal', 'paddleSpeed');
    bindSlider('settingAI', 'aiVal', 'aiReaction');
    bindSlider('settingTrail', 'trailVal', 'trailLength');
})();
</script>
