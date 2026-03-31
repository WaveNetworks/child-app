/**
 * water-ripples.js
 * Animated water ripple system from DSWADemo.
 * Drives body background-image, background-position, and background-size
 * to create living water with radial ripple rings, caustic stripe shimmer,
 * and parallax sway tied to the lotus wind vector.
 */
(function() {
'use strict';

// ─── Ripple Config ──────────────────────────────────────────────────────
var _rippleConfig = { topHue: 223, litTop: 28 };
var _staticBgLayers = 'linear-gradient(180deg, hsla(223,65%,37%,1) 0%, hsla(169,60%,33%,1) 100%)';
var _ripplePulseAlpha = 1.0;
var _ripplePulseTimer = null;
var _lastQPhase = -1;
var _cachedBgImage = '';
var _swayId = null;
var _windVector = { x: 0.3, y: 0.15 };  // default gentle wind

// ─── Ripple Gradient Builder ────────────────────────────────────────────
function buildRippleGradient(topHue, litTop, time, lotusEdge) {
    var edge  = lotusEdge || 210;
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

        stops.push('transparent ' + inner.toFixed(1) + 'px');
        stops.push(c + ',' + shoulder.toFixed(4) + ') ' + (r - hw * 1.2).toFixed(1) + 'px');
        stops.push(c + ',' + mid.toFixed(4) + ') ' + (r - hw * 0.5).toFixed(1) + 'px');
        stops.push(c + ',' + peak.toFixed(4) + ') ' + r.toFixed(1) + 'px');
        stops.push(c + ',' + mid.toFixed(4) + ') ' + (r + hw * 0.5).toFixed(1) + 'px');
        stops.push(c + ',' + shoulder.toFixed(4) + ') ' + (r + hw * 1.2).toFixed(1) + 'px');
        stops.push('transparent ' + (r + hw * 2.5).toFixed(1) + 'px');
        prevEnd = r + hw * 2.5;
    }

    stops.push('transparent ' + maxR + 'px');
    // Center ripples in content area (offset by sidebar)
    var sidebar = document.getElementById('sidebar');
    var sW = sidebar ? sidebar.offsetWidth : 0;
    var cx = sW + (window.innerWidth - sW) / 2;
    return 'radial-gradient(circle at ' + cx + 'px 100vh, ' + stops.join(', ') + ')';
}

// ─── Dynamic Stripe Builder (caustic shimmer) ───────────────────────────
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

// ─── Pulse Ripple ───────────────────────────────────────────────────────
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

// ─── Main Sway Loop ────────────────────────────────────────────────────
function startSwayLoop() {
    if (_swayId) return;

    var lps = window.lps || null;

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

        // Get lotus edge for ripple start distance
        var lotusEdge = 210;
        if (lps && lps.canvas && lps.canvas.R && typeof maxPetalExtent === 'function') {
            lotusEdge = maxPetalExtent(lps.canvas.R) * 0.63;
        }

        // Rebuild ripple gradient at ~30fps
        var phase = (t * 0.12) % 1.0;
        var qPhase = (phase * 240) | 0;
        var pulseActive = _ripplePulseAlpha !== 1.0;
        if (qPhase !== _lastQPhase || pulseActive) {
            _lastQPhase = qPhase;
            var stripes = buildDynamicStripes(_rippleConfig.topHue, _rippleConfig.litTop, t);
            _cachedBgImage = buildRippleGradient(
                _rippleConfig.topHue, _rippleConfig.litTop, t, lotusEdge
            ) + ',' + stripes + ',' + _staticBgLayers;
            document.body.style.backgroundImage = _cachedBgImage;
        }

        // Background-size breathing
        var sz1 = 100 + 4 * Math.sin(t * 0.19);
        var sz2 = 100 + 3.5 * Math.sin(t * 0.27 + 1.2);
        var sz3 = 100 + 3 * Math.sin(t * 0.15 + 2.5);
        document.body.style.backgroundSize = 'auto, ' + sz1 + '% ' + sz1 + '%, ' + sz2 + '% ' + sz2 + '%, ' + sz3 + '% ' + sz3 + '%, 100% 100%';

        // Parallax sway
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

// ─── Visibility Handling ────────────────────────────────────────────────
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (_swayId) { cancelAnimationFrame(_swayId); _swayId = null; }
    } else {
        startSwayLoop();
    }
});

// ─── Expose pulseRipple globally for lotus pulse integration ────────────
window.pulseRipple = pulseRipple;

// ─── Start ──────────────────────────────────────────────────────────────
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startSwayLoop);
} else {
    startSwayLoop();
}

})();
