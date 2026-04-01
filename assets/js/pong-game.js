/**
 * @module pong-game
 * @description Self-playing Pong game rendered on `#bgCanvas`.
 * Layers game elements ON TOP of the existing nebula background drawn
 * by bg-canvas.js.  Two AI paddles with imperfect tracking produce
 * natural-looking rallies.  Responds to `data-bs-theme` for light/dark
 * neon aesthetics.
 *
 * **Public API** (exposed on `window.pongGame`):
 * | Member     | Type       | Description                              |
 * |------------|------------|------------------------------------------|
 * | `start()`  | function   | Begin the game loop                      |
 * | `stop()`   | function   | Stop and restore normal canvas           |
 * | `active`   | boolean    | Read-only getter for current state       |
 * | `settings` | object     | Tunable parameters (see {@link Settings}) |
 */

(function() {
    var canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;

    /** @type {number|null} RAF handle for the game loop. */
    var animId = null;

    /** @type {boolean} Whether the game is currently running. */
    var active = false;

    /**
     * Tunable game parameters.  Can be modified at runtime via
     * `window.pongGame.settings`.
     * @typedef {Object} Settings
     * @property {number} ballSpeed      - Base ball velocity in px/frame.
     * @property {number} paddleSpeed    - Max paddle movement per frame.
     * @property {number} aiReaction     - 0-1, how well AI tracks (lower = more mistakes).
     * @property {number} trailLength    - Number of trail positions to keep.
     * @property {number} particleCount  - Particles spawned on paddle hit.
     */
    var settings = {
        ballSpeed: 5,
        paddleSpeed: 4,
        aiReaction: 0.85,
        trailLength: 12,
        particleCount: 8
    };

    // -- Game state --
    var W, H;
    var ball, paddleL, paddleR, scoreL, scoreR;
    var trail = [];
    var particles = [];
    var PADDLE_W = 14;
    var PADDLE_H, BALL_R;
    var rallyCount = 0;

    // -- Cached theme colours (recomputed only on theme change) --
    /** @type {Object|null} Cached result of colors(). */
    var _cachedColors = null;
    /** @type {boolean|null} Theme state when colours were last computed. */
    var _cachedColorsDark = null;

    /**
     * Checks whether the page is currently in dark mode.
     * @returns {boolean}
     */
    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') !== 'light';
    }

    /**
     * Returns the colour palette for game elements.  The result is cached
     * and only recomputed when the dark/light theme actually changes.
     * @returns {Object} Palette with keys for paddle, ball, glow, trail, score, text, particle colours.
     */
    function colors() {
        var dark = isDark();
        if (_cachedColors && _cachedColorsDark === dark) return _cachedColors;
        _cachedColorsDark = dark;
        _cachedColors = {
            paddleL:     dark ? '#6366f1'                    : '#4338ca',
            paddleLGlow: dark ? 'rgba(99,102,241,0.6)'      : 'rgba(67,56,202,0.4)',
            paddleR:     dark ? '#f43f5e'                    : '#be123c',
            paddleRGlow: dark ? 'rgba(244,63,94,0.6)'       : 'rgba(190,18,60,0.4)',
            ball:        dark ? '#22d3ee'                    : '#0891b2',
            ballGlow:    dark ? 'rgba(34,211,238,0.7)'       : 'rgba(8,145,178,0.45)',
            ballTrail:   dark ? 'rgba(34,211,238,'           : 'rgba(8,145,178,',
            score:       dark ? 'rgba(255,255,255,0.10)'     : 'rgba(0,0,0,0.07)',
            text:        dark ? 'rgba(255,255,255,0.18)'     : 'rgba(0,0,0,0.15)',
            particle:    dark ? ['#6366f1','#22d3ee','#f43f5e','#a78bfa','#34d399']
                               : ['#4338ca','#0891b2','#be123c','#7c3aed','#059669']
        };
        return _cachedColors;
    }

    /**
     * Updates canvas and game dimensions to match the current viewport.
     */
    function resize() {
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width = W * dpr;
        canvas.height = H * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        PADDLE_H = Math.max(80, H * 0.12);
        BALL_R = Math.max(8, Math.min(W, H) * 0.008);
    }

    /**
     * Resets the ball to the center with a random-ish launch angle.
     * @param {number} [dir] - Horizontal direction (1 = right, -1 = left). Random if omitted.
     */
    function resetBall(dir) {
        var angle = (Math.random() * 0.8 - 0.4); // -0.4 to 0.4 radians
        var speed = settings.ballSpeed * (1 + rallyCount * 0.002);
        ball = {
            x: W / 2,
            y: H / 2,
            vx: Math.cos(angle) * speed * (dir || (Math.random() < 0.5 ? 1 : -1)),
            vy: Math.sin(angle) * speed
        };
        trail = [];
    }

    /**
     * Initialises or resets the full game state (scores, paddles, ball).
     */
    function init() {
        resize();
        scoreL = 0;
        scoreR = 0;
        rallyCount = 0;
        paddleL = { x: 30, y: H / 2 - PADDLE_H / 2, targetY: H / 2 };
        paddleR = { x: W - 30 - PADDLE_W, y: H / 2 - PADDLE_H / 2, targetY: H / 2 };
        particles = [];
        resetBall();
    }

    /**
     * Creates burst particles at a given position.
     * @param {number} x     - Spawn X.
     * @param {number} y     - Spawn Y.
     * @param {number} count - Number of particles to create.
     */
    function spawnParticles(x, y, count) {
        var c = colors();
        for (var i = 0; i < count; i++) {
            var angle = Math.random() * Math.PI * 2;
            var speed = 1 + Math.random() * 3;
            particles.push({
                x: x, y: y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                life: 1,
                decay: 0.02 + Math.random() * 0.03,
                size: 2 + Math.random() * 3,
                color: c.particle[Math.floor(Math.random() * c.particle.length)]
            });
        }
    }

    /**
     * Moves a paddle toward the predicted ball intercept using imperfect AI.
     * @param {Object} paddle           - Paddle state with `x`, `y`, `targetY`.
     * @param {'left'|'right'} side     - Which side this paddle is on.
     */
    function updateAI(paddle, side) {
        var targetY = ball.y - PADDLE_H / 2;
        var approaching = (side === 'left' && ball.vx < 0) || (side === 'right' && ball.vx > 0);

        if (approaching) {
            var dx = paddle.x - ball.x;
            var timeToArrive = Math.abs(dx / ball.vx);
            var predictedY = ball.y + ball.vy * timeToArrive;

            // Reflect predicted Y off top/bottom walls
            while (predictedY < 0 || predictedY > H) {
                if (predictedY < 0) predictedY = -predictedY;
                if (predictedY > H) predictedY = 2 * H - predictedY;
            }

            targetY = predictedY - PADDLE_H / 2;
            targetY += (Math.random() - 0.5) * PADDLE_H * (1 - settings.aiReaction) * 0.5;
        } else {
            targetY = H / 2 - PADDLE_H / 2;
        }

        paddle.targetY = targetY;

        var diff = paddle.targetY - paddle.y;
        var maxMove = settings.paddleSpeed * (approaching ? 1.0 : 0.5);
        if (Math.abs(diff) > maxMove) {
            paddle.y += diff > 0 ? maxMove : -maxMove;
        } else {
            paddle.y += diff * 0.3;
        }

        if (paddle.y < 0) paddle.y = 0;
        if (paddle.y + PADDLE_H > H) paddle.y = H - PADDLE_H;
    }

    /**
     * Advances the game simulation by one tick: moves ball, checks
     * collisions, updates scores, runs AI, and decays particles.
     */
    function update() {
        ball.x += ball.vx;
        ball.y += ball.vy;

        trail.push({ x: ball.x, y: ball.y });
        if (trail.length > settings.trailLength) trail.shift();

        // Top/bottom bounce
        if (ball.y - BALL_R <= 0) {
            ball.y = BALL_R;
            ball.vy = Math.abs(ball.vy);
            spawnParticles(ball.x, ball.y, 4);
        }
        if (ball.y + BALL_R >= H) {
            ball.y = H - BALL_R;
            ball.vy = -Math.abs(ball.vy);
            spawnParticles(ball.x, ball.y, 4);
        }

        // Left paddle collision
        if (ball.vx < 0 &&
            ball.x - BALL_R <= paddleL.x + PADDLE_W &&
            ball.x + BALL_R >= paddleL.x &&
            ball.y >= paddleL.y &&
            ball.y <= paddleL.y + PADDLE_H) {

            ball.x = paddleL.x + PADDLE_W + BALL_R;
            var hitPos = (ball.y - paddleL.y) / PADDLE_H;
            var angle = (hitPos - 0.5) * 1.2;
            var speed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy) * 1.02;
            speed = Math.min(speed, 12);
            ball.vx = Math.cos(angle) * speed;
            ball.vy = Math.sin(angle) * speed;
            rallyCount++;
            spawnParticles(ball.x, ball.y, settings.particleCount);
        }

        // Right paddle collision
        if (ball.vx > 0 &&
            ball.x + BALL_R >= paddleR.x &&
            ball.x - BALL_R <= paddleR.x + PADDLE_W &&
            ball.y >= paddleR.y &&
            ball.y <= paddleR.y + PADDLE_H) {

            ball.x = paddleR.x - BALL_R;
            var hitPos = (ball.y - paddleR.y) / PADDLE_H;
            var angle = Math.PI + (0.5 - hitPos) * 1.2;
            var speed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy) * 1.02;
            speed = Math.min(speed, 12);
            ball.vx = Math.cos(angle) * speed;
            ball.vy = Math.sin(angle) * speed;
            rallyCount++;
            spawnParticles(ball.x, ball.y, settings.particleCount);
        }

        // Score -- ball exits left or right
        if (ball.x < -BALL_R * 2) {
            scoreR++;
            rallyCount = 0;
            spawnParticles(0, ball.y, 15);
            resetBall(1);
        }
        if (ball.x > W + BALL_R * 2) {
            scoreL++;
            rallyCount = 0;
            spawnParticles(W, ball.y, 15);
            resetBall(-1);
        }

        updateAI(paddleL, 'left');
        updateAI(paddleR, 'right');

        // Decay and cull particles
        for (var i = particles.length - 1; i >= 0; i--) {
            var p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vx *= 0.98;
            p.vy *= 0.98;
            p.life -= p.decay;
            if (p.life <= 0) particles.splice(i, 1);
        }
    }

    /**
     * Renders all game visuals (scores, trail, ball, paddles, particles,
     * branding) onto the shared `#bgCanvas` context.
     */
    function drawGame() {
        var c = colors();

        // bg-canvas.js draws the nebula on resize/theme-change.
        // We invoke the exposed no-op to keep the call site explicit.
        if (window.bgCanvasDraw) {
            window.bgCanvasDraw();
        }

        // Scores (bottom of screen)
        ctx.font = 'bold ' + Math.max(60, H * 0.1) + 'px monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.fillStyle = c.score;
        ctx.fillText(scoreL, W * 0.25, H - 20);
        ctx.fillText(scoreR, W * 0.75, H - 20);

        // Rally counter
        if (rallyCount > 2) {
            ctx.font = '16px monospace';
            ctx.fillStyle = c.text;
            ctx.textBaseline = 'bottom';
            ctx.fillText('Rally: ' + rallyCount, W / 2, H - 20);
        }

        // Ball trail
        for (var i = 0; i < trail.length; i++) {
            var alpha = (i / trail.length) * 0.5;
            var size = BALL_R * (0.3 + (i / trail.length) * 0.7);
            ctx.beginPath();
            ctx.arc(trail[i].x, trail[i].y, size, 0, Math.PI * 2);
            ctx.fillStyle = c.ballTrail + alpha + ')';
            ctx.fill();
        }

        // Ball glow
        var ballGrad = ctx.createRadialGradient(ball.x, ball.y, 0, ball.x, ball.y, BALL_R * 5);
        ballGrad.addColorStop(0, c.ballGlow);
        ballGrad.addColorStop(1, 'transparent');
        ctx.fillStyle = ballGrad;
        ctx.fillRect(ball.x - BALL_R * 5, ball.y - BALL_R * 5, BALL_R * 10, BALL_R * 10);

        // Ball
        ctx.beginPath();
        ctx.arc(ball.x, ball.y, BALL_R, 0, Math.PI * 2);
        ctx.fillStyle = c.ball;
        ctx.fill();

        // Left paddle glow
        ctx.shadowColor = c.paddleLGlow;
        ctx.shadowBlur = 25;
        ctx.fillStyle = c.paddleL;
        roundRect(ctx, paddleL.x, paddleL.y, PADDLE_W, PADDLE_H, 4);
        ctx.fill();
        ctx.shadowBlur = 0;

        // Right paddle glow
        ctx.shadowColor = c.paddleRGlow;
        ctx.shadowBlur = 25;
        ctx.fillStyle = c.paddleR;
        roundRect(ctx, paddleR.x, paddleR.y, PADDLE_W, PADDLE_H, 4);
        ctx.fill();
        ctx.shadowBlur = 0;

        // Particles
        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            ctx.globalAlpha = p.life;
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.globalAlpha = 1;

        // Branding
        ctx.font = '11px monospace';
        ctx.fillStyle = c.text;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        ctx.fillText('PONG // self-playing', 30, H - 30);
    }

    /**
     * Traces a rounded-rectangle path on the given context (does not fill/stroke).
     * @param {CanvasRenderingContext2D} ctx - Drawing context.
     * @param {number} x - Left edge.
     * @param {number} y - Top edge.
     * @param {number} w - Width.
     * @param {number} h - Height.
     * @param {number} r - Corner radius.
     */
    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    /**
     * Main RAF loop: runs update + draw each frame while active.
     */
    function loop() {
        if (!active) return;
        update();
        drawGame();
        animId = requestAnimationFrame(loop);
    }

    /**
     * Starts the Pong game loop.  Safe to call multiple times (no-ops if already active).
     */
    function start() {
        if (active) return;
        active = true;
        _cachedColors = null; // force colour refresh on start
        resize();
        init();
        canvas.setAttribute('data-game-active', 'true');
        loop();
    }

    /**
     * Stops the game loop and triggers a resize event so bg-canvas redraws the nebula.
     */
    function stop() {
        if (!active) return;
        active = false;
        if (animId) {
            cancelAnimationFrame(animId);
            animId = null;
        }
        canvas.removeAttribute('data-game-active');
        // Trigger bg-canvas to redraw the nebula
        window.dispatchEvent(new Event('resize'));
    }

    // Handle window resize while game is running
    window.addEventListener('resize', function() {
        if (!active) return;
        resize();
        paddleR.x = W - 30 - PADDLE_W;
        if (ball.x > W) ball.x = W - BALL_R;
        if (ball.y > H) ball.y = H - BALL_R;
    });

    /**
     * Public API exposed on `window.pongGame`.
     * @type {{start: Function, stop: Function, active: boolean, settings: Settings}}
     */
    window.pongGame = {
        start: start,
        stop: stop,
        get active() { return active; },
        settings: settings
    };
})();
