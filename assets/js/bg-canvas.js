/**
 * @module bg-canvas
 * @description Full-page canvas background layer for ElevateHER.
 * Renders a multi-stop radial/linear gradient nebula in dark mode and
 * soft color washes in light mode.  Automatically redraws on window
 * resize and `data-bs-theme` attribute changes.
 *
 * Exposes `window.bgCanvasDraw` as a **no-op** so external callers
 * (e.g. pong-game.js) can call it safely without forking a second RAF loop.
 */

(function() {
    var canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;

    /**
     * Checks whether the page is currently in dark mode.
     * @returns {boolean} `true` when `data-bs-theme` is anything other than "light".
     */
    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') !== 'light';
    }

    /**
     * Resizes the canvas to match the current viewport dimensions,
     * applies the device-pixel-ratio transform, and triggers a redraw.
     */
    function resize() {
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        draw();
    }

    /**
     * Draws the full background gradient onto the canvas.
     * In dark mode a deep nebula with indigo, violet, cyan, and rose
     * accent glows is rendered.  In light mode a subtle grey-white
     * base with whisper-level color washes is used instead.
     */
    function draw() {
        var w = window.innerWidth;
        var h = window.innerHeight;
        var dark = isDark();

        ctx.clearRect(0, 0, w, h);

        if (dark) {
            // -- Dark: deep nebula with colorful accents --

            // Base: deep blue-black
            var grad = ctx.createRadialGradient(w * 0.3, h * 0.3, 0, w * 0.5, h * 0.5, Math.max(w, h) * 0.8);
            grad.addColorStop(0, '#1a1a4e');
            grad.addColorStop(0.4, '#16213e');
            grad.addColorStop(0.7, '#0f1629');
            grad.addColorStop(1, '#0a0a1a');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Indigo glow -- bottom right
            var g1 = ctx.createRadialGradient(w * 0.8, h * 0.75, 0, w * 0.8, h * 0.75, w * 0.5);
            g1.addColorStop(0, 'rgba(99, 102, 241, 0.45)');
            g1.addColorStop(0.4, 'rgba(99, 102, 241, 0.2)');
            g1.addColorStop(1, 'transparent');
            ctx.fillStyle = g1;
            ctx.fillRect(0, 0, w, h);

            // Violet glow -- top left
            var g2 = ctx.createRadialGradient(w * 0.15, h * 0.2, 0, w * 0.15, h * 0.2, w * 0.45);
            g2.addColorStop(0, 'rgba(139, 92, 246, 0.35)');
            g2.addColorStop(0.4, 'rgba(139, 92, 246, 0.15)');
            g2.addColorStop(1, 'transparent');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, w, h);

            // Cyan accent -- top right
            var g3 = ctx.createRadialGradient(w * 0.85, h * 0.15, 0, w * 0.85, h * 0.15, w * 0.35);
            g3.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
            g3.addColorStop(0.5, 'rgba(6, 182, 212, 0.1)');
            g3.addColorStop(1, 'transparent');
            ctx.fillStyle = g3;
            ctx.fillRect(0, 0, w, h);

            // Rose accent -- bottom left
            var g4 = ctx.createRadialGradient(w * 0.1, h * 0.85, 0, w * 0.1, h * 0.85, w * 0.3);
            g4.addColorStop(0, 'rgba(244, 63, 94, 0.2)');
            g4.addColorStop(0.5, 'rgba(244, 63, 94, 0.08)');
            g4.addColorStop(1, 'transparent');
            ctx.fillStyle = g4;
            ctx.fillRect(0, 0, w, h);

        } else {
            // -- Light: grey/white with soft color washes --

            // Base: slightly darker on left, light on right
            var grad = ctx.createLinearGradient(0, 0, w, 0);
            grad.addColorStop(0, '#e2e2e8');
            grad.addColorStop(0.3, '#eaeaee');
            grad.addColorStop(0.6, '#f0f0f3');
            grad.addColorStop(1, '#f7f7f9');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, w, h);

            // Indigo wash -- top left (whisper)
            var g1 = ctx.createRadialGradient(w * 0.08, h * 0.15, 0, w * 0.08, h * 0.15, w * 0.45);
            g1.addColorStop(0, 'rgba(67, 56, 202, 0.04)');
            g1.addColorStop(0.4, 'rgba(67, 56, 202, 0.015)');
            g1.addColorStop(1, 'transparent');
            ctx.fillStyle = g1;
            ctx.fillRect(0, 0, w, h);

            // Violet wash -- bottom left (whisper)
            var g2 = ctx.createRadialGradient(w * 0.15, h * 0.8, 0, w * 0.15, h * 0.8, w * 0.4);
            g2.addColorStop(0, 'rgba(109, 40, 217, 0.03)');
            g2.addColorStop(0.4, 'rgba(109, 40, 217, 0.01)');
            g2.addColorStop(1, 'transparent');
            ctx.fillStyle = g2;
            ctx.fillRect(0, 0, w, h);

            // Cyan wash -- right side (whisper)
            var g3 = ctx.createRadialGradient(w * 0.85, h * 0.5, 0, w * 0.85, h * 0.5, w * 0.3);
            g3.addColorStop(0, 'rgba(6, 182, 212, 0.02)');
            g3.addColorStop(0.5, 'rgba(6, 182, 212, 0.005)');
            g3.addColorStop(1, 'transparent');
            ctx.fillStyle = g3;
            ctx.fillRect(0, 0, w, h);

        }
    }

    resize();
    window.addEventListener('resize', resize);

    var observer = new MutationObserver(function() { draw(); });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    /**
     * Exposed as a safe no-op redraw entry point.
     * bg-canvas redraws itself when needed (resize, theme change).
     * External callers should never invoke `draw()` directly.
     * @function window.bgCanvasDraw
     */
    window.bgCanvasDraw = function() {
        // no-op: prevents RAF loop forking when called externally.
    };
})();
