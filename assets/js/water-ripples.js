/**
 * @module water-ripples
 * @description Animated water ripple system ported from a reference demo.
 * Drives `body` background-image, background-position, and background-size
 * to create living water with radial ripple rings, caustic stripe shimmer,
 * and parallax sway tied to the bloom wind vector.
 *
 * Exposes {@link window.pulseRipple} for bloom pulse integration.
 */
(function() {
'use strict';

/**
 * Ripple configuration shared across gradient builders.
 * @typedef {Object} RippleConfig
 * @property {number} topHue  - Base hue for the water palette (degrees, 0-360).
 * @property {number} litTop  - Base lightness offset for the water palette.
 */

/** @type {RippleConfig} */
var _rippleConfig = { topHue: 223, litTop: 28 };

/** @type {string} Static base gradient layer (bottom-most background-image). */
var _staticBgLayers = 'linear-gradient(180deg, hsla(223,65%,37%,1) 0%, hsla(169,60%,33%,1) 100%)';

/** @type {number} Current ripple pulse alpha multiplier (1.0 = steady state). */
var _ripplePulseAlpha = 1.0;

/** @type {number|null} RAF handle for the pulse decay animation. */
var _ripplePulseTimer = null;

/** @type {number} Quantised phase from last frame, used for 30 fps throttle. */
var _lastQPhase = -1;

/** @type {string} Cached composite background-image value. */
var _cachedBgImage = '';

/** @type {number|null} RAF handle for the main sway loop. */
var _swayId = null;

/** @type {{x: number, y: number}} Current wind vector (updated from LPS if available). */
var _windVector = { x: 0.3, y: 0.15 };  // default gentle wind

/**
 * Builds a radial-gradient CSS string representing concentric ripple rings.
 * Each ring is a smooth bell-curve opacity bump in one of three rotating hues.
 *
 * Optimised to collect all colour-stop strings into an array and join once
 * rather than concatenating in the hot loop.
 *
 * @param {number} topHue    - Base hue (degrees).
 * @param {number} litTop    - Base lightness.
 * @param {number} time      - Current time in seconds (fractional).
 * @param {number} [bloomEdge=210] - Pixel radius at which the first ripple starts.
 * @returns {string} A complete `radial-gradient(...)` CSS value.
 */
function buildRippleGradient(topHue, litTop, time, bloomEdge) {
    var edge  = bloomEdge || 210;
    var power = 1.85;
    var scale = 8;
    var maxR  = 1500;
    var phase = (time * 0.12) % 1.0;
    var hues = [topHue + 5, topHue - 10, topHue - 22];
    var sats = [40, 35, 30];
    var lits = [litTop + 8, litTop + 6, litTop + 4];
    var colors = [
        'hsla(' + hues[0] + ',' + sats[0] + '%,' + lits[0] + '%',
        'hsla(' + hues[1] + ',' + sats[1] + '%,' + lits[1] + '%',
        'hsla(' + hues[2] + ',' + sats[2] + '%,' + lits[2] + '%',
    ];

    var stops = ['transparent 0px'];
    var prevEnd = 0;

    for (var i = 0; i < 30; i++) {
        var r = edge + scale * Math.pow(i + phase, power);
        if (r > maxR) break;

        var hw = 6 + (r - edge) * 0.012;
        var distFromEdge = r - edge;
        var fade = Math.max(0, 1 - distFromEdge / (maxR - edge));
        var peak = 0.07 * fade * _ripplePulseAlpha;
        var mid  = peak * 0.55;
        var shoulder = peak * 0.18;

        var c = colors[i % 3];
        var inner = Math.max(prevEnd, r - hw * 2.5);

        // Pre-format fixed-point values to avoid repeated toFixed inside concat
        var fInner    = inner.toFixed(1);
        var fShoulder = shoulder.toFixed(4);
        var fMid      = mid.toFixed(4);
        var fPeak     = peak.toFixed(4);
        var fRmHw12   = (r - hw * 1.2).toFixed(1);
        var fRmHw05   = (r - hw * 0.5).toFixed(1);
        var fR        = r.toFixed(1);
        var fRpHw05   = (r + hw * 0.5).toFixed(1);
        var fRpHw12   = (r + hw * 1.2).toFixed(1);
        var fOuter    = (r + hw * 2.5).toFixed(1);

        stops.push(
            'transparent ' + fInner + 'px',
            c + ',' + fShoulder + ') ' + fRmHw12 + 'px',
            c + ',' + fMid      + ') ' + fRmHw05 + 'px',
            c + ',' + fPeak     + ') ' + fR      + 'px',
            c + ',' + fMid      + ') ' + fRpHw05 + 'px',
            c + ',' + fShoulder + ') ' + fRpHw12 + 'px',
            'transparent ' + fOuter + 'px'
        );
        prevEnd = r + hw * 2.5;
    }

    stops.push('transparent ' + maxR + 'px');

    // Center ripples in content area (offset by sidebar)
    var sidebar = document.getElementById('sidebar');
    var sW = sidebar ? sidebar.offsetWidth : 0;
    var cx = sW + (window.innerWidth - sW) / 2;
    return 'radial-gradient(circle at ' + cx + 'px 100vh, ' + stops.join(', ') + ')';
}

/**
 * Builds three overlapping repeating-linear-gradient CSS layers that
 * simulate caustic light shimmer on the water surface.  Each layer
 * oscillates in opacity over time so the pattern breathes.
 *
 * @param {number} topHue - Base hue (degrees).
 * @param {number} litTop - Base lightness.
 * @param {number} time   - Current time in seconds (fractional).
 * @returns {string} Three comma-separated `repeating-linear-gradient(...)` values.
 */
function buildDynamicStripes(topHue, litTop, time) {
    var a1 = 0.016 + 0.014 * Math.sin(time * 0.31);
    var a1s = a1 * 0.48;
    var a2 = 0.014 + 0.012 * Math.sin(time * 0.43 + 1.7);
    var a2s = a2 * 0.45;
    var a3 = 0.010 + 0.009 * Math.sin(time * 0.23 + 3.1);
    var a3s = a3 * 0.42;

    return 'repeating-linear-gradient(58deg,' +
        'transparent 0px, transparent 10px,' +
        'hsla(' + (topHue+10) + ',35%,' + (litTop+16) + '%,' + a1s.toFixed(4) + ') 22px,' +
        'hsla(' + (topHue+10) + ',40%,' + (litTop+20) + '%,' + a1.toFixed(4) + ') 50px,' +
        'hsla(' + (topHue+10) + ',35%,' + (litTop+16) + '%,' + a1s.toFixed(4) + ') 78px,' +
        'transparent 90px, transparent 105px),' +
    'repeating-linear-gradient(-62deg,' +
        'transparent 0px, transparent 8px,' +
        'hsla(' + (topHue-8) + ',30%,' + (litTop+12) + '%,' + a2s.toFixed(4) + ') 18px,' +
        'hsla(' + (topHue-8) + ',35%,' + (litTop+18) + '%,' + a2.toFixed(4) + ') 42px,' +
        'hsla(' + (topHue-8) + ',30%,' + (litTop+12) + '%,' + a2s.toFixed(4) + ') 66px,' +
        'transparent 76px, transparent 88px),' +
    'repeating-linear-gradient(170deg,' +
        'transparent 0px, transparent 12px,' +
        'hsla(' + (topHue+2) + ',28%,' + (litTop+14) + '%,' + a3s.toFixed(4) + ') 30px,' +
        'hsla(' + (topHue+2) + ',32%,' + (litTop+18) + '%,' + a3.toFixed(4) + ') 55px,' +
        'hsla(' + (topHue+2) + ',28%,' + (litTop+14) + '%,' + a3s.toFixed(4) + ') 80px,' +
        'transparent 95px, transparent 115px)';
}

/**
 * Triggers a ripple pulse by temporarily boosting `_ripplePulseAlpha`
 * to 3.0 and decaying it back to 1.0 over 800 ms using an ease-out
 * cubic curve.  Called externally via {@link window.pulseRipple}.
 */
function pulseRipple() {
    _ripplePulseAlpha = 3.0;
    _lastQPhase = -1;
    var start = performance.now();
    var dur = 800;
    if (_ripplePulseTimer) cancelAnimationFrame(_ripplePulseTimer);
    (function decay() {
        var elapsed = performance.now() - start;
        var t = Math.min(elapsed / dur, 1);
        _ripplePulseAlpha = 3.0 - 2.0 * (1 - Math.pow(1 - t, 3));
        if (t < 1) _ripplePulseTimer = requestAnimationFrame(decay);
        else { _ripplePulseAlpha = 1.0; _ripplePulseTimer = null; }
    })();
}

/**
 * Starts the main animation loop that drives background-image,
 * background-size, and background-position every frame.
 *
 * The loop is gated at ~30 fps via a quantised-phase check so
 * the expensive gradient rebuild only happens when the visual
 * result would actually change (or during an active pulse).
 *
 * Wind vector is pulled from the BloomProgressSystem canvas if
 * available, otherwise falls back to the default gentle breeze.
 */
function startSwayLoop() {
    if (_swayId) return;

    var lps = window.lps || null;

    /**
     * Per-frame callback.  Rebuilds gradients at throttled rate,
     * applies breathing background-size, and computes parallax
     * sway offsets for each background layer.
     */
    function loop() {
        _swayId = requestAnimationFrame(loop);
        var now = performance.now();
        var t = now * 0.001;

        // Get wind from LPS if available
        if (!lps && window.lps) lps = window.lps;
        if (lps && lps.canvas && lps.canvas._windVector) {
            _windVector = lps.canvas._windVector;
        }
        var w = _windVector;
        var mag = Math.sqrt(w.x * w.x + w.y * w.y) || 0.01;

        // Get bloom edge for ripple start distance
        var bloomEdge = 210;
        if (lps && lps.canvas && lps.canvas.R && typeof maxPetalExtent === 'function') {
            bloomEdge = maxPetalExtent(lps.canvas.R) * 0.63;
        }

        // Rebuild ripple gradient at ~30fps (quantised phase check)
        var phase = (t * 0.12) % 1.0;
        var qPhase = (phase * 240) | 0;
        var pulseActive = _ripplePulseAlpha !== 1.0;
        if (qPhase !== _lastQPhase || pulseActive) {
            _lastQPhase = qPhase;
            var stripes = buildDynamicStripes(_rippleConfig.topHue, _rippleConfig.litTop, t);
            _cachedBgImage = buildRippleGradient(
                _rippleConfig.topHue, _rippleConfig.litTop, t, bloomEdge
            ) + ',' + stripes + ',' + _staticBgLayers;
            document.body.style.backgroundImage = _cachedBgImage;
        }

        // Background-size breathing
        var sz1 = 100 + 4 * Math.sin(t * 0.19);
        var sz2 = 100 + 3.5 * Math.sin(t * 0.27 + 1.2);
        var sz3 = 100 + 3 * Math.sin(t * 0.15 + 2.5);
        document.body.style.backgroundSize = 'auto, ' + sz1 + '% ' + sz1 + '%, ' + sz2 + '% ' + sz2 + '%, ' + sz3 + '% ' + sz3 + '%, 100% 100%';

        // Parallax sway offsets per layer
        var driftAngle = t * 0.04;
        var driftIntX = 75 * Math.sin(driftAngle);
        var driftIntY = -75 * Math.cos(driftAngle);

        var ringX = Math.sin(t * 0.3) * 20 * w.x + Math.sin(t * 0.18) * 8 * mag;
        var ringY = Math.cos(t * 0.25) * 14 * w.y + Math.sin(t * 0.15) * 5 * mag;
        var s60X = Math.sin(t * 0.18 + 1.0) * 28 * w.x + Math.cos(t * 0.12) * 10 * mag + driftIntX * 0.5;
        var s60Y = Math.cos(t * 0.14 + 0.7) * 20 * w.y + Math.sin(t * 0.09) * 8 * mag + driftIntY * 0.3;
        var s60nX = Math.sin(t * 0.7 + 2.2) * 10 * w.x + Math.sin(t * 0.45 + 1.5) * 6 * mag + driftIntX * 0.9;
        var s60nY = Math.cos(t * 0.55 + 1.8) * 8 * w.y + Math.cos(t * 0.38) * 5 * mag + driftIntY * 0.15;
        var s170X = Math.sin(t * 0.35 + 0.5) * 15 * w.x + Math.cos(t * 0.28 + 2.0) * 8 * mag + driftIntX * 0.65;
        var s170Y = Math.cos(t * 0.22 + 1.3) * 12 * w.y + Math.sin(t * 0.17) * 6 * mag + driftIntY * 0.45;
        var baseX = Math.sin(t * 0.15) * 5 * w.x;
        var baseY = Math.cos(t * 0.12) * 3 * w.y;

        document.body.style.backgroundPosition = [
            'calc(50% + ' + ringX.toFixed(1) + 'px) calc(100% + ' + ringY.toFixed(1) + 'px)',
            'calc(0% + ' + s60X.toFixed(1) + 'px) calc(0% + ' + s60Y.toFixed(1) + 'px)',
            'calc(0% + ' + s60nX.toFixed(1) + 'px) calc(0% + ' + s60nY.toFixed(1) + 'px)',
            'calc(0% + ' + s170X.toFixed(1) + 'px) calc(0% + ' + s170Y.toFixed(1) + 'px)',
            'calc(50% + ' + baseX.toFixed(1) + 'px) calc(50% + ' + baseY.toFixed(1) + 'px)'
        ].join(', ');
    }

    _swayId = requestAnimationFrame(loop);
}

// Pause the loop when the tab is hidden, restart when visible.
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (_swayId) { cancelAnimationFrame(_swayId); _swayId = null; }
    } else {
        startSwayLoop();
    }
});

/**
 * Trigger a visual ripple pulse from external code (e.g. bloom heartbeat).
 * @function window.pulseRipple
 */
window.pulseRipple = pulseRipple;

// Start on DOM ready or immediately if already loaded.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startSwayLoop);
} else {
    startSwayLoop();
}

})();
