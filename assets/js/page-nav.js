/**
 * @module page-nav
 * @description Responsive secondary/page-level navigation toggle.
 *
 * Desktop (lg+): nav visible by default, toggle collapses it inline.
 * Mobile (<lg):  nav hidden by default, toggle opens it as overlay drawer.
 * Toggle button always visible at all breakpoints (training affordance).
 *
 * Uses: #pageNavToggle, #pageNavWrapper, #pageNav, #pageNavBackdrop
 */
(function () {
    'use strict';

    /** @private @constant {string} localStorage key for persisting desktop nav state */
    var STORAGE_KEY = 'wn_page_nav_state';

    /** @private @constant {number} Bootstrap lg breakpoint in pixels */
    var BP_LG = 992;

    var toggle  = document.getElementById('pageNavToggle');
    var wrapper = document.getElementById('pageNavWrapper');
    var nav     = document.getElementById('pageNav');
    var backdrop = document.getElementById('pageNavBackdrop');

    if (!toggle || !wrapper || !nav) return;

    /**
     * Check whether the viewport is at or above the desktop breakpoint.
     * @private
     * @returns {boolean} True if viewport width >= BP_LG
     */
    function isDesktop() {
        return window.innerWidth >= BP_LG;
    }

    /**
     * Apply the collapsed/expanded state for desktop layout.
     * @private
     * @param {boolean} collapsed - Whether the nav should be collapsed
     */
    function setDesktopState(collapsed) {
        wrapper.classList.toggle('page-nav-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    /**
     * Apply the open/closed state for mobile drawer layout.
     * @private
     * @param {boolean} open - Whether the mobile drawer should be open
     */
    function setMobileState(open) {
        wrapper.classList.toggle('page-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    /**
     * Initialize nav state based on current viewport and saved preferences.
     * Clears both mobile and desktop classes, then applies the correct one.
     * @private
     */
    function init() {
        wrapper.classList.remove('page-nav-collapsed', 'page-nav-open');

        if (isDesktop()) {
            var saved = localStorage.getItem(STORAGE_KEY);
            setDesktopState(saved === 'collapsed');
        } else {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    // Toggle click — switches between collapsed/expanded (desktop) or open/closed (mobile)
    toggle.addEventListener('click', function (e) {
        e.preventDefault();

        if (isDesktop()) {
            var isCollapsed = wrapper.classList.contains('page-nav-collapsed');
            setDesktopState(!isCollapsed);
            localStorage.setItem(STORAGE_KEY, isCollapsed ? 'expanded' : 'collapsed');
        } else {
            var isOpen = wrapper.classList.contains('page-nav-open');
            setMobileState(!isOpen);
        }
    });

    // Backdrop click closes mobile nav
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setMobileState(false);
        });
    }

    // Close mobile nav when a nav link is clicked (page will navigate)
    nav.addEventListener('click', function (e) {
        if (!isDesktop() && e.target.closest('.nav-link')) {
            setMobileState(false);
        }
    });

    // Reset layout on breakpoint crossing (debounced)
    var lastDesktop = isDesktop();
    /** @private @type {number|undefined} Resize debounce timer */
    var resizeTimer;

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            var nowDesktop = isDesktop();
            if (nowDesktop !== lastDesktop) {
                init();
                lastDesktop = nowDesktop;
            }
        }, 150);
    });

    init();
})();
