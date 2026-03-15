/**
 * page-nav.js
 * Responsive secondary/page-level navigation toggle.
 *
 * Desktop (lg+): nav visible by default, toggle collapses it inline.
 * Mobile (<lg):  nav hidden by default, toggle opens it as overlay drawer.
 * Toggle button always visible at all breakpoints (training affordance).
 *
 * Uses: #pageNavToggle, #pageNavWrapper, #pageNav, #pageNavBackdrop
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'wn_page_nav_state';
    var BP_LG = 992;

    var toggle  = document.getElementById('pageNavToggle');
    var wrapper = document.getElementById('pageNavWrapper');
    var nav     = document.getElementById('pageNav');
    var backdrop = document.getElementById('pageNavBackdrop');

    if (!toggle || !wrapper || !nav) return;

    function isDesktop() {
        return window.innerWidth >= BP_LG;
    }

    function setDesktopState(collapsed) {
        wrapper.classList.toggle('page-nav-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function setMobileState(open) {
        wrapper.classList.toggle('page-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function init() {
        wrapper.classList.remove('page-nav-collapsed', 'page-nav-open');

        if (isDesktop()) {
            var saved = localStorage.getItem(STORAGE_KEY);
            setDesktopState(saved === 'collapsed');
        } else {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    // Toggle click
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

    // Close mobile nav on link click
    nav.addEventListener('click', function (e) {
        if (!isDesktop() && e.target.closest('.nav-link')) {
            setMobileState(false);
        }
    });

    // Reset on breakpoint crossing
    var lastDesktop = isDesktop();
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
