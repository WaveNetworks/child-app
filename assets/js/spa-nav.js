/**
 * @module spa-nav
 * @description SPA-style navigation for the ElevateHER child-app.
 * Intercepts sidebar and internal nav link clicks, fetches view content
 * via AJAX, and replaces `#content-dynamic` without reloading the page.
 * This keeps the canvas background, sidebar, topnav, and all JS state alive.
 *
 * **How it works:**
 * 1. Sidebar links with `[data-spa-nav]` are intercepted on click.
 * 2. Fetches `index.php?page=X&reloadView=true` (returns JSON with HTML + metadata).
 * 3. Replaces `#content-dynamic` innerHTML.
 * 4. Updates URL via `history.pushState`.
 * 5. Updates document title, sidebar active state, badge counts.
 * 6. Fires any toast notifications from session flash.
 * 7. Handles browser back/forward via `popstate`.
 *
 * **Opt-in / Opt-out:**
 * - Add `data-spa-nav` to opt a link IN (auto-added to sidebar links).
 * - Add `data-no-spa` to opt a link OUT.
 * - Forms still do full page reloads (action files need the POST cycle).
 *
 * **Public API:**
 * - `window.reloadView(page?)` -- re-fetch the current (or specified) page via SPA nav.
 */
(function () {
    'use strict';

    var contentEl = document.getElementById('content-dynamic');
    if (!contentEl) return; // Not on the main app template

    /** @type {string} Base path for all SPA fetch URLs. */
    var BASE_URL = 'index.php';

    // ---- Loading indicator ----

    /** @type {HTMLDivElement|null} The thin progress bar element, if currently visible. */
    var loadingEl = null;

    /**
     * Shows a thin animated loading bar at the top of the viewport.
     * No-ops if already visible.
     */
    function showLoading() {
        if (loadingEl) return;
        loadingEl = document.createElement('div');
        loadingEl.id = 'spa-loading';
        loadingEl.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:3px;z-index:9999;background:var(--bs-primary);animation:spa-loading-pulse 1s ease-in-out infinite;';
        document.body.appendChild(loadingEl);
    }

    /**
     * Removes the loading bar from the DOM.
     */
    function hideLoading() {
        if (loadingEl) {
            loadingEl.remove();
            loadingEl = null;
        }
    }

    // Inject the loading-bar keyframe animation once.
    var style = document.createElement('style');
    style.textContent = '@keyframes spa-loading-pulse{0%{opacity:.3;transform:scaleX(0.3);transform-origin:left}50%{opacity:1;transform:scaleX(0.7)}100%{opacity:.3;transform:scaleX(1)}}';
    document.head.appendChild(style);

    // ---- Core navigation function ----

    /**
     * Fetches a view via AJAX and swaps `#content-dynamic` innerHTML.
     * On success it also updates the URL, document title, sidebar state,
     * badge counts, fires toasts, re-initialises Bootstrap widgets, and
     * executes any `<script>` tags in the new content.
     *
     * On failure it falls back to a full page load.
     *
     * @param {string}  page        - The `page` query-string value to fetch.
     * @param {boolean} [pushState=true] - Whether to push a new history entry.
     * @param {string}  [extraParams=''] - Additional `&key=val` pairs to append.
     */
    function navigateTo(page, pushState, extraParams) {
        if (pushState === undefined) pushState = true;
        if (!extraParams) extraParams = '';

        showLoading();

        var url = BASE_URL + '?page=' + encodeURIComponent(page) + extraParams + '&reloadView=true';

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            // Replace content
            contentEl.innerHTML = data.html;

            // Update URL -- include extra params so tabs/filters are reflected
            if (pushState) {
                var newUrl = BASE_URL + '?page=' + encodeURIComponent(data.page) + extraParams;
                history.pushState({ page: data.page, extraParams: extraParams }, data.title, newUrl);
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

    /**
     * Highlights the matching sidebar link and de-highlights all others.
     * Also swaps badge colour classes for the active/inactive states.
     * @param {string} page - The current page identifier.
     */
    function updateSidebarActive(page) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('.nav-link').forEach(function (link) {
            var linkPage = extractPage(link.getAttribute('href'));
            if (linkPage === page) {
                link.classList.add('active', 'bg-primary', 'rounded');
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

    /**
     * Updates the item-count badge text inside the "Items" sidebar link.
     * @param {number|string} count - New badge value.
     */
    function updateBadge(count) {
        var badges = document.querySelectorAll('.sidebar-badge');
        badges.forEach(function (badge) {
            var parent = badge.closest('.nav-link');
            if (parent && extractPage(parent.getAttribute('href')) === 'items') {
                badge.textContent = count;
            }
        });
    }

    // ---- Toast notifications ----

    /**
     * Fires toast notifications from the server-side session flash payload.
     * Delegates to the global `showToast()` function if available.
     * @param {Object} toast - Hash with optional `success`, `error`, `warning`, `info` keys.
     */
    function fireToasts(toast) {
        if (typeof showToast !== 'function') return;
        if (toast.success) showToast('success', toast.success);
        if (toast.error) showToast('danger', toast.error);
        if (toast.warning) showToast('warning', toast.warning);
        if (toast.info) showToast('info', toast.info);
    }

    // ---- Content re-initialization ----

    /**
     * Re-initialises Bootstrap widgets and executes `<script>` tags
     * inside the freshly swapped `#content-dynamic`.
     *
     * **Script execution ordering guarantee:**
     * `innerHTML` does not execute `<script>` tags.  This function
     * clones each script into a live element.  External scripts (those
     * with a `src` attribute) are loaded **sequentially** via an
     * `onload` chain so that dependency ordering is preserved (e.g.
     * a CDN library must finish loading before an inline script that
     * references it).  Once all external scripts have loaded, **all**
     * inline scripts are executed in document order.  Browser caching
     * means repeat CDN loads are effectively instant.
     */
    function reinitContent() {
        // Re-initialize Bootstrap tooltips
        var tooltips = contentEl.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function (el) {
            new bootstrap.Tooltip(el);
        });

        // Re-initialize Bootstrap popovers
        var popovers = contentEl.querySelectorAll('[data-bs-toggle="popover"]');
        popovers.forEach(function (el) {
            new bootstrap.Popover(el);
        });

        // Partition scripts into external (src) and inline groups.
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

        /**
         * Replaces all inline `<script>` elements with fresh clones so
         * their code actually executes in the DOM.
         */
        function runInlineScripts() {
            inlineScripts.forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }

        if (externalScripts.length > 0) {
            // Load external scripts one-at-a-time in document order.
            // Each script's `onload` (or `onerror`) triggers the next,
            // guaranteeing that script N is fully loaded before script N+1
            // begins.  After the last external script, all inline scripts run.
            /**
             * Recursively loads the external script at `index`, then proceeds
             * to the next.  When all are done, runs inline scripts.
             * @param {number} index - Current position in externalScripts[].
             */
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

    /**
     * Extracts the `page` query-string parameter from an href string.
     * @param {string|null} href - URL or partial href to parse.
     * @returns {string|null} The decoded page value, or null if not found.
     */
    function extractPage(href) {
        if (!href) return null;
        var match = href.match(/[?&]page=([^&#]*)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    // ---- Helper: extract all query params except page as &key=val string ----

    /**
     * Parses all query-string parameters from `href` and returns them as
     * a concatenated `&key=val` string, **excluding** `page` and `reloadView`.
     *
     * Uses `URLSearchParams` for robust parsing.  The result is safe to
     * append directly to a URL that already has `?page=X`.
     *
     * @param {string|null} href - URL or query string to parse.
     * @returns {string} Encoded `&key=val` pairs (may be empty string).
     */
    function extractExtraParams(href) {
        if (!href) return '';
        var extra = '';
        try {
            // Build URLSearchParams from the query portion of the href.
            var params = new URLSearchParams(href.indexOf('?') !== -1 ? href.substring(href.indexOf('?')) : href);
            // Iterate all params, keeping everything except page and reloadView.
            params.forEach(function (val, key) {
                if (key !== 'page' && key !== 'reloadView') {
                    extra += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
                }
            });
        } catch (ignored) {}
        return extra;
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

        // Only intercept links to index.php?page=X
        var page = extractPage(href);
        if (!page) return;

        // Skip if the link goes to a different path (not index.php)
        if (href.indexOf('index.php') === -1) return;

        // Extract extra query params (tab, id, etc.) -- everything except page
        var extra = '';
        try {
            var linkUrl = new URL(link.href, window.location.origin);
            linkUrl.searchParams.forEach(function (val, key) {
                if (key !== 'page') {
                    extra += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
                }
            });
        } catch (ignored) {}

        // SPA navigate
        e.preventDefault();
        navigateTo(page, true, extra);
    });

    // ---- Browser back/forward ----

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.page) {
            navigateTo(e.state.page, false, e.state.extraParams || '');
        } else {
            // Parse page and extra params from current URL
            var page = extractPage(window.location.search) || 'dashboard';
            var extra = extractExtraParams(window.location.search);
            navigateTo(page, false, extra);
        }
    });

    // ---- Handle bfcache restoration ----
    // When browser restores page from back-forward cache (bfcache), force a real reload
    // so stale SPA state doesn't persist.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            window.location.reload();
        }
    });

    // ---- Set initial state ----
    // Store current page in history state so popstate works on first back.
    var initialPage = extractPage(window.location.search) || 'dashboard';
    var initialExtra = extractExtraParams(window.location.search);
    history.replaceState({ page: initialPage, extraParams: initialExtra }, document.title, window.location.href);

    // ---- Public API ----

    /**
     * Re-fetch the current (or specified) view via SPA navigation.
     * Useful for action handlers that need to refresh the page content
     * after a successful POST without a full reload.
     *
     * @function window.reloadView
     * @param {string} [page] - Page to reload.  Defaults to the current page from the URL.
     */
    window.reloadView = function (page) {
        if (!page) {
            page = extractPage(window.location.search) || 'dashboard';
        }
        var extra = extractExtraParams(window.location.search);
        navigateTo(page, false, extra);
    };

})();
