/**
 * pong-game.js — Self-playing Pong on #bgCanvas
 * Takes over the background canvas with an animated pong game.
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
            bg:          dark ? 'rgba(10, 10, 26, 0.92)'    : 'rgba(240, 240, 243, 0.92)',
            court:       dark ? 'rgba(255,255,255,0.04)'     : 'rgba(0,0,0,0.04)',
            centerLine:  dark ? 'rgba(255,255,255,0.08)'     : 'rgba(0,0,0,0.08)',
            paddleL:     dark ? '#6366f1'                    : '#4338ca',
            paddleLGlow: dark ? 'rgba(99,102,241,0.5)'      : 'rgba(67,56,202,0.3)',
            paddleR:     dark ? '#f43f5e'                    : '#be123c',
            paddleRGlow: dark ? 'rgba(244,63,94,0.5)'       : 'rgba(190,18,60,0.3)',
            ball:        dark ? '#22d3ee'                    : '#0891b2',
            ballGlow:    dark ? 'rgba(34,211,238,0.6)'       : 'rgba(8,145,178,0.35)',
            ballTrail:   dark ? 'rgba(34,211,238,'           : 'rgba(8,145,178,',
            score:       dark ? 'rgba(255,255,255,0.12)'     : 'rgba(0,0,0,0.08)',
            scoreFill:   dark ? 'rgba(255,255,255,0.25)'     : 'rgba(0,0,0,0.18)',
            text:        dark ? 'rgba(255,255,255,0.15)'     : 'rgba(0,0,0,0.12)',
            particle:    dark ? ['#6366f1','#22d3ee','#f43f5e','#a78bfa','#34d399']
                               : ['#4338ca','#0891b2','#be123c','#7c3aed','#059669']
        };
    }

    function resize() {
        W = window.innerWidth;
        H = window.innerHeight;
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
        // AI: track ball with imperfect reaction time + slight offset
        var targetY = ball.y - PADDLE_H / 2;

        // Only react when ball is heading toward this paddle
        var approaching = (side === 'left' && ball.vx < 0) || (side === 'right' && ball.vx > 0);

        if (approaching) {
            // Predict where ball will arrive (simple linear prediction)
            var dx = side === 'left' ? (paddle.x - ball.x) : (paddle.x - ball.x);
            var timeToArrive = Math.abs(dx / ball.vx);
            var predictedY = ball.y + ball.vy * timeToArrive;

            // Bounce prediction off top/bottom
            while (predictedY < 0 || predictedY > H) {
                if (predictedY < 0) predictedY = -predictedY;
                if (predictedY > H) predictedY = 2 * H - predictedY;
            }

            targetY = predictedY - PADDLE_H / 2;
            // Add some imperfection
            targetY += (Math.random() - 0.5) * PADDLE_H * (1 - settings.aiReaction) * 0.5;
        } else {
            // Drift back toward center when ball going away
            targetY = H / 2 - PADDLE_H / 2;
        }

        paddle.targetY = targetY;

        // Smooth movement toward target
        var diff = paddle.targetY - paddle.y;
        var maxMove = settings.paddleSpeed * (approaching ? 1.0 : 0.5);
        if (Math.abs(diff) > maxMove) {
            paddle.y += diff > 0 ? maxMove : -maxMove;
        } else {
            paddle.y += diff * 0.3;
        }

        // Clamp to screen
        if (paddle.y < 0) paddle.y = 0;
        if (paddle.y + PADDLE_H > H) paddle.y = H - PADDLE_H;
    }

    function update() {
        // Move ball
        ball.x += ball.vx;
        ball.y += ball.vy;

        // Trail
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
            // Angle based on where it hits the paddle
            var hitPos = (ball.y - paddleL.y) / PADDLE_H; // 0 to 1
            var angle = (hitPos - 0.5) * 1.2; // -0.6 to 0.6 radians
            var speed = Math.sqrt(ball.vx * ball.vx + ball.vy * ball.vy) * 1.02; // tiny speedup
            speed = Math.min(speed, 12); // cap
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

        // Score — ball past left or right edge
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

        // AI
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

        // Background
        ctx.fillStyle = c.bg;
        ctx.fillRect(0, 0, W, H);

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

        // Scores
        ctx.font = 'bold ' + Math.max(60, H * 0.1) + 'px monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillStyle = c.score;
        ctx.fillText(scoreL, W * 0.25, 30);
        ctx.fillText(scoreR, W * 0.75, 30);

        // Rally counter
        if (rallyCount > 2) {
            ctx.font = '16px monospace';
            ctx.fillStyle = c.text;
            ctx.textBaseline = 'bottom';
            ctx.fillText('Rally: ' + rallyCount, W / 2, H - 20);
        }

        // Ball trail
        for (var i = 0; i < trail.length; i++) {
            var alpha = (i / trail.length) * 0.4;
            var size = BALL_R * (0.3 + (i / trail.length) * 0.7);
            ctx.beginPath();
            ctx.arc(trail[i].x, trail[i].y, size, 0, Math.PI * 2);
            ctx.fillStyle = c.ballTrail + alpha + ')';
            ctx.fill();
        }

        // Ball glow
        var ballGrad = ctx.createRadialGradient(ball.x, ball.y, 0, ball.x, ball.y, BALL_R * 4);
        ballGrad.addColorStop(0, c.ballGlow);
        ballGrad.addColorStop(1, 'transparent');
        ctx.fillStyle = ballGrad;
        ctx.fillRect(ball.x - BALL_R * 4, ball.y - BALL_R * 4, BALL_R * 8, BALL_R * 8);

        // Ball
        ctx.beginPath();
        ctx.arc(ball.x, ball.y, BALL_R, 0, Math.PI * 2);
        ctx.fillStyle = c.ball;
        ctx.fill();

        // Left paddle glow
        ctx.shadowColor = c.paddleLGlow;
        ctx.shadowBlur = 20;
        ctx.fillStyle = c.paddleL;
        roundRect(ctx, paddleL.x, paddleL.y, PADDLE_W, PADDLE_H, 4);
        ctx.fill();
        ctx.shadowBlur = 0;

        // Right paddle glow
        ctx.shadowColor = c.paddleRGlow;
        ctx.shadowBlur = 20;
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
        // Signal to bg-canvas that game is active
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
        // Trigger bg-canvas to redraw by dispatching resize
        window.dispatchEvent(new Event('resize'));
    }

    // Handle window resize while game is running
    window.addEventListener('resize', function() {
        if (!active) return;
        resize();
        // Reposition paddles
        paddleR.x = W - 30 - PADDLE_W;
        // Clamp ball
        if (ball.x > W) ball.x = W - BALL_R;
        if (ball.y > H) ball.y = H - BALL_R;
    });

    // Redraw on theme change
    var observer = new MutationObserver(function() {
        // Colors update automatically on next frame via colors()
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    // Public API
    window.pongGame = {
        start: start,
        stop: stop,
        get active() { return active; },
        settings: settings
    };
})();
