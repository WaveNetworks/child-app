/**
 * @module onboarding
 * @description Pre-auth first-time user onboarding panel navigation.
 * Manages swipeable panels, dot navigation, touch gestures, keyboard
 * navigation, and localStorage gating to show the flow only once.
 */
(function() {
    'use strict';

    /** @constant {string} localStorage key to track onboarding completion.
     *  Injected per-app by the page (window.WN_ONBOARD_KEY = "<slug>_onboarded"). */
    var STORAGE_KEY = window.WN_ONBOARD_KEY || 'wn_onboarded';

    var track = document.getElementById('onboardingTrack');
    var panels = document.querySelectorAll('.onboarding-panel');
    var dots = document.querySelectorAll('.onboarding-dot');
    var btnPrev = document.getElementById('obPrev');
    var btnNext = document.getElementById('obNext');
    var btnSkip = document.getElementById('obSkip');

    /** @type {number} Currently visible panel index */
    var current = 0;

    /** @type {number} Total number of onboarding panels */
    var total = panels.length;

    if (!track || total === 0) return;

    /**
     * Navigate to a specific panel by index.
     * Updates the track transform, active classes on panels and dots,
     * and button visibility/text.
     * @param {number} index - Zero-based panel index to display
     */
    function goTo(index) {
        if (index < 0 || index >= total) return;
        current = index;
        // Each panel occupies 25% of the track width (4 panels)
        track.style.transform = 'translateX(-' + (current * 25) + '%)';

        for (var i = 0; i < panels.length; i++) {
            panels[i].classList.toggle('active', i === current);
        }
        for (var j = 0; j < dots.length; j++) {
            dots[j].classList.toggle('active', j === current);
        }

        // Hide prev button on first panel
        btnPrev.style.display = current === 0 ? 'none' : '';

        // Change next button to "Get Started" on final panel
        btnNext.textContent = (current === total - 1) ? 'Get Started' : 'Next';
    }

    /**
     * Mark onboarding as complete and redirect to login.
     * Stores a flag in localStorage so the onboarding is not shown again.
     */
    function complete() {
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch(e) { /* storage unavailable */ }
        window.location.href = 'login.php';
    }

    // Button handlers
    btnNext.addEventListener('click', function() {
        if (current === total - 1) {
            complete();
        } else {
            goTo(current + 1);
        }
    });

    btnPrev.addEventListener('click', function() {
        goTo(current - 1);
    });

    btnSkip.addEventListener('click', function() {
        complete();
    });

    // Dot navigation — each dot jumps to its corresponding panel
    for (var d = 0; d < dots.length; d++) {
        (function(idx) {
            dots[idx].addEventListener('click', function() { goTo(idx); });
        })(d);
    }

    // Touch/swipe support
    /** @type {number} X coordinate where touch started */
    var touchStartX = 0;
    /** @type {number} X coordinate where touch ended */
    var touchEndX = 0;
    /** @constant {number} Minimum swipe distance in pixels to trigger navigation */
    var MIN_SWIPE = 50;

    track.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    track.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        var diff = touchStartX - touchEndX;
        if (Math.abs(diff) > MIN_SWIPE) {
            if (diff > 0) {
                // Swipe left -- advance to next panel
                if (current < total - 1) goTo(current + 1);
            } else {
                // Swipe right -- go to previous panel
                if (current > 0) goTo(current - 1);
            }
        }
    }, { passive: true });

    // Keyboard navigation (arrows and Escape)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            if (current < total - 1) goTo(current + 1);
            else complete();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (current > 0) goTo(current - 1);
        } else if (e.key === 'Escape') {
            complete();
        }
    });

    // Initialize first panel
    goTo(0);
})();
