/**
 * @module tabdrop
 * @description Responsive tab overflow -- tabs that don't fit collapse into a "More" dropdown.
 *
 * Usage: add data-tabdrop to any .nav-tabs element.
 *   <ul class="nav nav-tabs" data-tabdrop role="tablist"> ... </ul>
 *
 * The script measures available width, hides tabs that overflow, and moves
 * them into a Bootstrap dropdown appended at the right end. On resize the
 * layout is recalculated. When a hidden tab is active, the dropdown toggle
 * shows that tab's label and gets the active styling.
 */
(function () {
    'use strict';

    /**
     * Initialize tabdrop behavior on a single nav element.
     * Builds the "More" dropdown, measures tab widths, and sets up
     * resize and tab-switch listeners.
     * @param {HTMLElement} nav - The .nav-tabs element with [data-tabdrop]
     */
    function initTabdrop(nav) {
        // Build the "More" dropdown (hidden until needed)
        var dropdown = document.createElement('li');
        dropdown.className = 'nav-item dropdown tabdrop-more';
        dropdown.style.flexShrink = '0';
        dropdown.innerHTML =
            '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">' +
            '<i class="bi bi-three-dots"></i> <span class="tabdrop-label">More</span></a>' +
            '<ul class="dropdown-menu dropdown-menu-end"></ul>';
        nav.appendChild(dropdown);

        var toggle = dropdown.querySelector('.nav-link');
        var menu = dropdown.querySelector('.dropdown-menu');
        var label = dropdown.querySelector('.tabdrop-label');
        var items = Array.from(nav.querySelectorAll(':scope > li.nav-item:not(.tabdrop-more)'));

        /** @type {number[]|null} Cached natural widths of each tab item */
        var naturalWidths = null;

        /** @type {number|null} Cached nav horizontal padding (left + right) */
        var cachedNavPadding = null;

        /**
         * Measure the natural (uncollapsed) width of each tab item.
         * Temporarily sets overflow visible and removes hidden state so
         * clipped items report their true width.
         * @private
         */
        function measureNaturalWidths() {
            var savedOverflow = nav.style.overflow;
            nav.style.overflow = 'visible';
            items.forEach(function (li) { li.classList.remove('tabdrop-hidden'); li.style.flexShrink = '0'; });
            dropdown.style.display = 'none';
            naturalWidths = items.map(function (li) { return li.offsetWidth; });
            nav.style.overflow = savedOverflow;
        }

        /**
         * Cache the nav element's horizontal padding from getComputedStyle.
         * Only recomputed when naturalWidths are invalidated (on resize).
         * @private
         */
        function cacheNavPadding() {
            var cs = getComputedStyle(nav);
            cachedNavPadding = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
        }

        /**
         * Recalculate which tabs fit and which overflow into the dropdown.
         * Uses cached natural widths and padding to avoid unnecessary reflows.
         * @private
         */
        function layout() {
            // Ensure we have natural widths and cached padding
            if (!naturalWidths) measureNaturalWidths();
            if (cachedNavPadding === null) cacheNavPadding();

            // Reset: show all real tabs, hide dropdown
            items.forEach(function (li) { li.classList.remove('tabdrop-hidden'); });
            dropdown.style.display = 'none';
            menu.innerHTML = '';

            // Measure available inner width (subtract cached padding)
            var navWidth = nav.offsetWidth - cachedNavPadding;

            // Briefly show dropdown to measure its toggle width
            dropdown.style.display = '';
            var dropdownWidth = dropdown.offsetWidth;
            dropdown.style.display = 'none';

            var totalWidth = 0;
            for (var i = 0; i < items.length; i++) {
                totalWidth += naturalWidths[i];
            }

            // If everything fits without the dropdown, nothing to do
            // Use 1px buffer to account for subpixel rounding
            if (totalWidth < navWidth - 1) return;

            // Tabs overflow -- figure out which ones go into the dropdown
            var usedWidth = 0;
            var overflowing = [];

            for (var i = 0; i < items.length; i++) {
                usedWidth += naturalWidths[i];
                if (usedWidth >= navWidth - dropdownWidth) {
                    for (var j = i; j < items.length; j++) {
                        overflowing.push(items[j]);
                    }
                    break;
                }
            }

            if (overflowing.length === 0) return;

            // Show dropdown, hide overflowing tabs
            dropdown.style.display = '';
            var activeInDropdown = false;

            overflowing.forEach(function (li) {
                li.classList.add('tabdrop-hidden');
                var link = li.querySelector('.nav-link');
                if (!link) return;

                var menuItem = document.createElement('li');
                var a = document.createElement('a');
                a.className = 'dropdown-item';
                a.href = link.getAttribute('href') || '#';
                a.setAttribute('data-bs-toggle', 'tab');
                a.setAttribute('data-bs-target', link.getAttribute('data-bs-target') || link.getAttribute('href'));
                a.innerHTML = link.innerHTML;

                if (link.classList.contains('active')) {
                    a.classList.add('active');
                    activeInDropdown = true;
                    label.textContent = link.textContent.trim();
                }

                // When a dropdown tab is clicked, sync the hidden real tab
                a.addEventListener('shown.bs.tab', function () {
                    // Deactivate all visible tab links
                    items.forEach(function (item) {
                        var l = item.querySelector('.nav-link');
                        if (l) l.classList.remove('active');
                    });
                    toggle.classList.add('active');
                    label.textContent = a.textContent.trim();
                });

                menuItem.appendChild(a);
                menu.appendChild(menuItem);
            });

            // Style the dropdown toggle based on whether the active tab is inside it
            if (activeInDropdown) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
                label.textContent = 'More';
            }
        }

        // When a visible tab is activated, reset dropdown active state
        items.forEach(function (li) {
            var link = li.querySelector('.nav-link');
            if (!link) return;
            link.addEventListener('shown.bs.tab', function () {
                toggle.classList.remove('active');
                label.textContent = 'More';
                layout();
            });
        });

        // Debounced resize -- invalidate caches and re-layout
        /** @type {number|undefined} */
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                naturalWidths = null;
                cachedNavPadding = null;
                layout();
            }, 100);
        });

        // Initial layout (after fonts/images settle)
        if (document.readyState === 'complete') {
            layout();
        } else {
            window.addEventListener('load', layout);
        }
        // Also run on next frame for SPA navigations
        requestAnimationFrame(function () { requestAnimationFrame(layout); });
    }

    /**
     * Auto-initialize all elements with the [data-tabdrop] attribute.
     * @private
     */
    function initAll() {
        document.querySelectorAll('[data-tabdrop]').forEach(initTabdrop);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose for SPA re-init after dynamic content load
    window.initTabdrop = initTabdrop;
})();
