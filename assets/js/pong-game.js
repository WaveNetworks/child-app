/**
 * pong-game.js — Self-playing Pong on #bgCanvas
 * Layers a pong game ON TOP of the existing nebula background.
 * bg-canvas.js draws the nebula/grid first, then this draws game elements over it.
 * Two AI paddles with imperfect tracking for natural-looking rallies.
 * Responds to data-bs-theme for light/dark neon aesthetics.
 *
 * API:
 *   window.pongGame.start()   — begin the game loop
 *   window.pongGame.stop()    — stop and restore normal canvas
 *   window.pongGame.active    — boolean, current state
 *   window.pongGame.settings  — { ballSpeed, paddleSpeed, aiReaction, trailLength, particleCount }
 */

(function() {
    var canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var animId = null;
    var active = false;

    // Settings (adjustable from game.php UI)
    var settings = {
        ballSpeed: 5,
        paddleSpeed: 4,
        aiReaction: 0.85,      // 0-1, how well AI tracks (lower = more mistakes)
        trailLength: 12,
        particleCount: 8
    };

    // Game state
    var W, H;
    var ball, paddleL, paddleR, scoreL, scoreR;
    var trail = [];
    var particles = [];
    var PADDLE_W = 14;
    var PADDLE_H, BALL_R;
    var rallyCount = 0;

    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') !== 'light';
    }

    function colors() {
        var dark = isDark();
        return {
            court:       dark ? 'rgba(255,255,255,0.06)'     : 'rgba(0,0,0,0.06)',
            centerLine:  dark ? 'rgba(255,255,255,0.10)'     : 'rgba(0,0,0,0.10)',
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
    }

    function resize() {
        W = window.innerWidth;
        H = window.innerHeight;
        // Ensure canvas pixel buffer matches viewport (bg-canvas also does this on resize,
        // but we need it correct for our animation frames too)
        canvas.width = W * dpr;
        canvas.height = H * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        PADDLE_H = Math.max(80, H * 0.12);
        BALL_R = Math.max(8, Math.min(W, H) * 0.008);
    }

    function resetBall(dir) {
        var angle = (Math.random() * 0.8 - 0.4); // -0.4 to 0.4 radians
        var speed = settings.ballSpeed * (1 + rallyCount * 0.002); // slight speedup over time
        ball = {
            x: W / 2,
            y: H / 2,
            vx: Math.cos(angle) * speed * (dir || (Math.random() < 0.5 ? 1 : -1)),
            vy: Math.sin(angle) * speed
        };
        trail = [];
    }

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

    function updateAI(paddle, side) {
        var targetY = ball.y - PADDLE_H / 2;
        var approaching = (side === 'left' && ball.vx < 0) || (side === 'right' && ball.vx > 0);

        if (approaching) {
            var dx = side === 'left' ? (paddle.x - ball.x) : (paddle.x - ball.x);
            var timeToArrive = Math.abs(dx / ball.vx);
            var predictedY = ball.y + ball.vy * timeToArrive;

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

        // Score
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

        // Particles
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

    function drawGame() {
        var c = colors();

        // Let bg-canvas draw the nebula first by dispatching its draw.
        // bg-canvas.js exposes nothing, but its resize handler redraws.
        // Instead, we just draw our game elements on top — bg-canvas already
        // drew the nebula on the last resize/theme-change. We only need to
        // re-trigger it each frame. We call the exposed redraw if available,
        // otherwise we accept the static nebula underneath.
        if (window.bgCanvasDraw) {
            window.bgCanvasDraw();
        }

        // Subtle court border
        ctx.strokeStyle = c.court;
        ctx.lineWidth = 2;
        ctx.strokeRect(20, 10, W - 40, H - 20);

        // Center line (dashed)
        ctx.setLineDash([10, 10]);
        ctx.strokeStyle = c.centerLine;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(W / 2, 10);
        ctx.lineTo(W / 2, H - 10);
        ctx.stroke();
        ctx.setLineDash([]);

        // Center circle
        ctx.beginPath();
        ctx.arc(W / 2, H / 2, 50, 0, Math.PI * 2);
        ctx.strokeStyle = c.centerLine;
        ctx.stroke();

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

    function loop() {
        if (!active) return;
        update();
        drawGame();
        animId = requestAnimationFrame(loop);
    }

    function start() {
        if (active) return;
        active = true;
        resize();
        init();
        canvas.setAttribute('data-game-active', 'true');
        loop();
    }

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

    // Public API
    window.pongGame = {
        start: start,
        stop: stop,
        get active() { return active; },
        settings: settings
    };
})();
