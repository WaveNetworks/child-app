/**
 * tabdrop.js
 * Responsive tab overflow — tabs that don't fit collapse into a "More" dropdown.
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

        // Cache natural widths (measured once when all tabs are visible)
        var naturalWidths = null;

        function measureNaturalWidths() {
            // Temporarily allow overflow so clipped items report true width
            var savedOverflow = nav.style.overflow;
            nav.style.overflow = 'visible';
            items.forEach(function (li) { li.classList.remove('tabdrop-hidden'); li.style.flexShrink = '0'; });
            dropdown.style.display = 'none';
            naturalWidths = items.map(function (li) { return li.offsetWidth; });
            nav.style.overflow = savedOverflow;
        }

        function layout() {
            // Ensure we have natural widths
            if (!naturalWidths) measureNaturalWidths();

            // Reset: show all real tabs, hide dropdown
            items.forEach(function (li) { li.classList.remove('tabdrop-hidden'); });
            dropdown.style.display = 'none';
            menu.innerHTML = '';

            // Measure available inner width (subtract padding) and dropdown toggle width
            var cs = getComputedStyle(nav);
            var navWidth = nav.offsetWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
            dropdown.style.display = '';
            var dropdownWidth = dropdown.offsetWidth;
            dropdown.style.display = 'none';

            var usedWidth = 0;
            var totalWidth = 0;
            var overflowing = [];

            for (var i = 0; i < items.length; i++) {
                totalWidth += naturalWidths[i];
            }

            // If everything fits without the dropdown, nothing to do
            // Use 1px buffer to account for subpixel rounding
            if (totalWidth < navWidth - 1) return;

            // Tabs overflow — figure out which ones need to go into dropdown
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

            // Style the dropdown toggle
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

        // Debounced resize — remeasure natural widths on resize
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                naturalWidths = null;
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

    // Auto-init all [data-tabdrop] elements
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
