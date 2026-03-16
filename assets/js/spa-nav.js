/**
 * spa-nav.js
 * SPA-style navigation for child-app.
 * Intercepts sidebar and internal nav link clicks, fetches view content via AJAX,
 * and replaces #content-dynamic without reloading the page.
 * This keeps the canvas background, sidebar, topnav, and all JS state alive.
 *
 * How it works:
 *   1. Sidebar links with [data-spa-nav] are intercepted on click
 *   2. Fetches index.php?page=X&reloadView=true (returns JSON with HTML + metadata)
 *   3. Replaces #content-dynamic innerHTML
 *   4. Updates URL via history.pushState
 *   5. Updates document title, sidebar active state, badge counts
 *   6. Fires any toast notifications from session flash
 *   7. Handles browser back/forward via popstate
 *
 * To opt a link INTO SPA nav: add data-spa-nav attribute (auto-added to sidebar links)
 * To opt a link OUT: add data-no-spa attribute
 * Forms still do full page reloads (by design — action files need the POST cycle)
 */
(function () {
    'use strict';

    var contentEl = document.getElementById('content-dynamic');
    if (!contentEl) return; // Not on the main app template

    var BASE_URL = 'index.php';

    // ---- Loading indicator ----
    var loadingEl = null;

    function showLoading() {
        if (loadingEl) return;
        loadingEl = document.createElement('div');
        loadingEl.id = 'spa-loading';
        loadingEl.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:3px;z-index:9999;background:var(--bs-primary);animation:spa-loading-pulse 1s ease-in-out infinite;';
        document.body.appendChild(loadingEl);
    }

    function hideLoading() {
        if (loadingEl) {
            loadingEl.remove();
            loadingEl = null;
        }
    }

    // Add loading animation
    var style = document.createElement('style');
    style.textContent = '@keyframes spa-loading-pulse{0%{opacity:.3;transform:scaleX(0.3);transform-origin:left}50%{opacity:1;transform:scaleX(0.7)}100%{opacity:.3;transform:scaleX(1)}}';
    document.head.appendChild(style);

    // ---- Core navigation function ----

    function navigateTo(page, pushState) {
        if (pushState === undefined) pushState = true;

        showLoading();

        var url = BASE_URL + '?page=' + encodeURIComponent(page) + '&reloadView=true';

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            // Replace content
            contentEl.innerHTML = data.html;

            // Update URL
            if (pushState) {
                var newUrl = BASE_URL + '?page=' + encodeURIComponent(data.page);
                history.pushState({ page: data.page }, data.title, newUrl);
            }

            // Update document title
            if (data.title) document.title = data.title;

            // Update sidebar active state
            updateSidebarActive(data.page);

            // Update badge counts
            if (data.item_count !== undefined) {
                updateBadge(data.item_count);
            }

            // Fire toast notifications
            if (data.toast) {
                fireToasts(data.toast);
            }

            // Re-run any initialization needed for the new content
            reinitContent();

            // Scroll to top of content
            contentEl.scrollIntoView({ behavior: 'instant', block: 'start' });
        })
        .catch(function (err) {
            console.error('[spa-nav] Navigation failed:', err);
            // Fallback: do a full page load
            window.location.href = BASE_URL + '?page=' + encodeURIComponent(page);
        })
        .finally(function () {
            hideLoading();
        });
    }

    // ---- Sidebar active state ----

    function updateSidebarActive(page) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            var linkPage = extractPage(link.getAttribute('href'));
            if (linkPage === page) {
                link.classList.add('active', 'bg-primary', 'rounded');
                // Update badge style for active
                var badge = link.querySelector('.sidebar-badge');
                if (badge) {
                    badge.classList.remove('bg-primary');
                    badge.classList.add('bg-light', 'text-primary');
                }
            } else {
                link.classList.remove('active', 'bg-primary', 'rounded');
                var badge = link.querySelector('.sidebar-badge');
                if (badge) {
                    badge.classList.remove('bg-light', 'text-primary');
                    badge.classList.add('bg-primary');
                }
            }
        });
    }

    // ---- Badge update ----

    function updateBadge(count) {
        var badges = document.querySelectorAll('.sidebar-badge');
        badges.forEach(function (badge) {
            // Only update the items badge (inside the Items nav link)
            var parent = badge.closest('.nav-link');
            if (parent && extractPage(parent.getAttribute('href')) === 'items') {
                badge.textContent = count;
            }
        });
    }

    // ---- Toast notifications ----

    function fireToasts(toast) {
        if (typeof showToast !== 'function') return;
        if (toast.success) showToast('success', toast.success);
        if (toast.error) showToast('danger', toast.error);
        if (toast.warning) showToast('warning', toast.warning);
        if (toast.info) showToast('info', toast.info);
    }

    // ---- Content re-initialization ----

    function reinitContent() {
        // Re-initialize Bootstrap tooltips if any
        var tooltips = contentEl.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        // Re-initialize Bootstrap popovers if any
        var popovers = contentEl.querySelectorAll('[data-bs-toggle="popover"]');
        popovers.forEach(function (el) {
            new bootstrap.Popover(el);
        });

        // Execute inline and external <script> tags in the new content.
        // innerHTML doesn't execute scripts, so we clone them into live elements.
        // External scripts (src) load first in order, then inline scripts run.
        var scripts = contentEl.querySelectorAll('script');
        var externalScripts = [];
        var inlineScripts = [];

        scripts.forEach(function (oldScript) {
            if (oldScript.src) {
                externalScripts.push(oldScript);
            } else {
                inlineScripts.push(oldScript);
            }
        });

        function runInlineScripts() {
            inlineScripts.forEach(function (oldScript) {
                var newScript = document.createElement('script');
                // Copy attributes
                Array.from(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }

        if (externalScripts.length > 0) {
            // Load external scripts in sequence (order matters for dependencies),
            // then run all inline scripts after externals finish.
            // Browser cache means repeat CDN loads are instant.
            function loadNextExternal(index) {
                if (index >= externalScripts.length) {
                    runInlineScripts();
                    return;
                }
                var oldScript = externalScripts[index];
                var newScript = document.createElement('script');
                newScript.src = oldScript.src;
                Array.from(oldScript.attributes).forEach(function (attr) {
                    if (attr.name !== 'src') newScript.setAttribute(attr.name, attr.value);
                });
                newScript.onload = function () { loadNextExternal(index + 1); };
                newScript.onerror = function () { loadNextExternal(index + 1); };
                oldScript.parentNode.replaceChild(newScript, oldScript);
            }
            loadNextExternal(0);
        } else {
            runInlineScripts();
        }

        // Dispatch custom event so page-specific JS can hook in
        document.dispatchEvent(new CustomEvent('spa:contentLoaded', {
            detail: { container: contentEl }
        }));
    }

    // ---- Helper: extract page param from URL ----

    function extractPage(href) {
        if (!href) return null;
        var match = href.match(/[?&]page=([^&#]*)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    // ---- Event delegation: intercept nav clicks ----

    document.addEventListener('click', function (e) {
        // Find closest <a> ancestor
        var link = e.target.closest('a[href]');
        if (!link) return;

        // Skip if opted out
        if (link.hasAttribute('data-no-spa')) return;

        // Skip external links, hash-only links, different origins
        var href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('http') || href.startsWith('//')) return;
        if (href.startsWith('mailto:') || href.startsWith('tel:')) return;

        // Skip if modifier keys (open in new tab, etc.)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

        // Only intercept links to index.php?page=X (sidebar nav + internal links)
        var page = extractPage(href);
        if (!page) return;

        // Skip if the link goes to a different path (not index.php)
        if (href.indexOf('index.php') === -1) return;

        // SPA navigate
        e.preventDefault();
        navigateTo(page);
    });

    // ---- Browser back/forward ----

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.page) {
            navigateTo(e.state.page, false);
        } else {
            // Parse page from current URL
            var page = extractPage(window.location.search) || 'dashboard';
            navigateTo(page, false);
        }
    });

    // ---- Set initial state ----
    // Store current page in history state so popstate works on first back
    var initialPage = extractPage(window.location.search) || 'dashboard';
    history.replaceState({ page: initialPage }, document.title, window.location.href);

    // ---- Public API ----
    // Expose reloadView globally so action handlers can call it
    window.reloadView = function (page) {
        if (!page) {
            page = extractPage(window.location.search) || 'dashboard';
        }
        navigateTo(page, false);
    };

})();
