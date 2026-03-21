/**
 * bg-canvas.js — Full-page canvas background layer
 * Debug pattern: gradient background + grid + corner arrows.
 * Confirms canvas covers entire viewport behind all UI.
 * Responds to data-bs-theme for light/dark mode.
 *
 * Replace draw() with your own rendering logic for gamified
 * backgrounds, particle effects, animations, etc.
 */

(function() {
    var canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var GRID_SIZE = 80;
    var ARROW_SIZE = 6;

    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') !== 'light';
    }

    function resize() {
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        draw();
    }

    function drawArrow(x, y, angle) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.beginPath();
        ctx.moveTo(0, -ARROW_SIZE);
        ctx.lineTo(-ARROW_SIZE * 0.6, ARROW_SIZE * 0.4);
        ctx.lineTo(ARROW_SIZE * 0.6, ARROW_SIZE * 0.4);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function draw() {
        var w = window.innerWidth;
        var h = window.innerHeight;
        var dark = isDark();

        ctx.clearRect(0, 0, w, h);

        if (dark) {
            // ── Dark: deep nebula with colorful accents ─────────────────

            // Base: deep blue-black
            var grad = ctx.createRadialGradient(w * 0.3, h * 0.3, 0, w * 0.5, h * 0.5, Math.max(w, h) * 0.8);
            grad.addColorStop(0, '#1a1a4e');
            grad.addColorStop(0.4, '#16213e');
            grad.addColorStop(0.7, '#0f1629');
            grad.addColorStop(1, '#0a0a1a');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Indigo glow — bottom right
            var g1 = ctx.createRadialGradient(w * 0.8, h * 0.75, 0, w * 0.8, h * 0.75, w * 0.5);
            g1.addColorStop(0, 'rgba(99, 102, 241, 0.45)');
            g1.addColorStop(0.4, 'rgba(99, 102, 241, 0.2)');
            g1.addColorStop(1, 'transparent');
            ctx.fillStyle = g1;
            ctx.fillRect(0, 0, w, h);

            // Violet glow — top left
            var g2 = ctx.createRadialGradient(w * 0.15, h * 0.2, 0, w * 0.15, h * 0.2, w * 0.45);
            g2.addColorStop(0, 'rgba(139, 92, 246, 0.35)');
            g2.addColorStop(0.4, 'rgba(139, 92, 246, 0.15)');
            g2.addColorStop(1, 'transparent');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, w, h);

            // Cyan accent — top right
            var g3 = ctx.createRadialGradient(w * 0.85, h * 0.15, 0, w * 0.85, h * 0.15, w * 0.35);
            g3.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
            g3.addColorStop(0.5, 'rgba(6, 182, 212, 0.1)');
            g3.addColorStop(1, 'transparent');
            ctx.fillStyle = g3;
            ctx.fillRect(0, 0, w, h);

            // Rose accent — bottom left
            var g4 = ctx.createRadialGradient(w * 0.1, h * 0.85, 0, w * 0.1, h * 0.85, w * 0.3);
            g4.addColorStop(0, 'rgba(244, 63, 94, 0.2)');
            g4.addColorStop(0.5, 'rgba(244, 63, 94, 0.08)');
            g4.addColorStop(1, 'transparent');
            ctx.fillStyle = g4;
            ctx.fillRect(0, 0, w, h);

            // Grid + arrows — light on dark
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.04)';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.06)';
            var labelColor = 'rgba(255, 255, 255, 0.1)';

        } else {
            // ── Light: grey/white with soft color washes ────────────────

            // Base: slightly darker on left, light on right
            var grad = ctx.createLinearGradient(0, 0, w, 0);
            grad.addColorStop(0, '#e2e2e8');
            grad.addColorStop(0.3, '#eaeaee');
            grad.addColorStop(0.6, '#f0f0f3');
            grad.addColorStop(1, '#f7f7f9');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Indigo wash — top left (whisper)
            var g1 = ctx.createRadialGradient(w * 0.08, h * 0.15, 0, w * 0.08, h * 0.15, w * 0.45);
            g1.addColorStop(0, 'rgba(67, 56, 202, 0.04)');
            g1.addColorStop(0.4, 'rgba(67, 56, 202, 0.015)');
            g1.addColorStop(1, 'transparent');
            ctx.fillStyle = g1;
            ctx.fillRect(0, 0, w, h);

            // Violet wash — bottom left (whisper)
            var g2 = ctx.createRadialGradient(w * 0.15, h * 0.8, 0, w * 0.15, h * 0.8, w * 0.4);
            g2.addColorStop(0, 'rgba(109, 40, 217, 0.03)');
            g2.addColorStop(0.4, 'rgba(109, 40, 217, 0.01)');
            g2.addColorStop(1, 'transparent');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, w, h);

            // Cyan wash — right side (whisper)
            var g3 = ctx.createRadialGradient(w * 0.85, h * 0.5, 0, w * 0.85, h * 0.5, w * 0.3);
            g3.addColorStop(0, 'rgba(6, 182, 212, 0.02)');
            g3.addColorStop(0.5, 'rgba(6, 182, 212, 0.005)');
            g3.addColorStop(1, 'transparent');
            ctx.fillStyle = g3;
            ctx.fillRect(0, 0, w, h);

            // Grid + arrows — dark on light
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.06)';
            ctx.fillStyle = 'rgba(0, 0, 0, 0.06)';
            var labelColor = 'rgba(0, 0, 0, 0.12)';
        }

        // Grid lines
        ctx.lineWidth = 1;
        ctx.beginPath();
        for (var x = 0; x <= w; x += GRID_SIZE) {
            ctx.moveTo(x + 0.5, 0);
            ctx.lineTo(x + 0.5, h);
        }
        for (var y = 0; y <= h; y += GRID_SIZE) {
            ctx.moveTo(0, y + 0.5);
            ctx.lineTo(w, y + 0.5);
        }
        ctx.stroke();

        // Arrows at intersections — pointing toward nearest corner
        var midX = w / 2;
        var midY = h / 2;
        for (var gx = GRID_SIZE; gx < w; gx += GRID_SIZE) {
            for (var gy = GRID_SIZE; gy < h; gy += GRID_SIZE) {
                var cornerX = gx < midX ? 0 : w;
                var cornerY = gy < midY ? 0 : h;
                var angle = Math.atan2(cornerY - gy, cornerX - gx) - Math.PI / 2;
                drawArrow(gx, gy, angle);
            }
        }

        // Corner labels
        ctx.fillStyle = labelColor;
        ctx.font = '10px monospace';
        ctx.textBaseline = 'top';
        ctx.textAlign = 'left';
        ctx.fillText('TL (0,0)', 4, 4);
        ctx.textAlign = 'right';
        ctx.fillText('TR (' + w + ',0)', w - 4, 4);
        ctx.textBaseline = 'bottom';
        ctx.fillText('BR (' + w + ',' + h + ')', w - 4, h - 4);
        ctx.textAlign = 'left';
        ctx.fillText('BL (0,' + h + ')', 4, h - 4);
    }

    resize();
    window.addEventListener('resize', resize);

    var observer = new MutationObserver(function() { draw(); });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    // Expose a safe redraw that does NOT fork the RAF loop.
    // bg-canvas redraws itself when needed (resize, theme change).
    // External callers should never invoke draw() directly.
    window.bgCanvasDraw = function() {
        // no-op: prevents RAF loop forking when called externally.
    };
})();
