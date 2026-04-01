/**
 * @module search
 * @description Live search in the top navigation bar.
 * Debounced input fires apiPost('liveSearch') and renders dropdown results.
 * Works on both desktop (#navSearchInput) and mobile (#navSearchInputMobile).
 */
(function () {
    'use strict';

    /** @constant {number} Debounce delay in milliseconds */
    var DEBOUNCE_MS = 300;

    /** @constant {number} Minimum characters before triggering a search */
    var MIN_CHARS   = 2;

    // Desktop elements
    var input   = document.getElementById('navSearchInput');
    var results = document.getElementById('navSearchResults');

    // Mobile elements
    var inputMobile   = document.getElementById('navSearchInputMobile');
    var resultsMobile = document.getElementById('navSearchResultsMobile');
    var mobileBar     = document.getElementById('navSearchMobile');
    var mobileToggle  = document.getElementById('navSearchToggle');

    // Mobile toggle — show/hide the mobile search bar
    if (mobileToggle && mobileBar) {
        mobileToggle.addEventListener('click', function () {
            var visible = mobileBar.style.display !== 'none';
            mobileBar.style.display = visible ? 'none' : 'block';
            if (!visible && inputMobile) inputMobile.focus();
        });
    }

    /**
     * Build an HTML status badge for an item's status.
     * @param {string} status - The item status ('active', 'draft', 'archived')
     * @returns {string} HTML string for the Bootstrap badge
     */
    function statusBadge(status) {
        var cls = {active: 'bg-success', draft: 'bg-warning text-dark', archived: 'bg-secondary'};
        return '<span class="badge ' + (cls[status] || 'bg-light text-dark') + ' ms-auto">' + escHtml(status) + '</span>';
    }

    /**
     * Escape a string for safe HTML insertion using textContent assignment.
     * Uses a single reusable element to avoid DOM allocation on every call.
     * @param {string} str - The raw string to escape
     * @returns {string} The HTML-escaped string
     */
    var _escDiv = document.createElement('div');
    function escHtml(str) {
        _escDiv.textContent = str;
        return _escDiv.innerHTML;
    }

    /**
     * Render search results into the dropdown container.
     * @param {HTMLElement} container - The dropdown element to populate
     * @param {Object} data - Response object with a results array
     * @param {Array} [data.results] - Array of result items
     */
    function renderResults(container, data) {
        if (!data.results || data.results.length === 0) {
            container.innerHTML = '<div class="px-3 py-2 text-muted small">No results found</div>';
            container.classList.add('show');
            return;
        }

        var html = '';
        data.results.forEach(function (item) {
            html += '<a class="dropdown-item d-flex align-items-center py-2" href="index.php?page=items&edit=' + item.item_id + '">'
                  + '<div class="flex-grow-1">'
                  + '<div class="small fw-semibold">' + escHtml(item.title) + '</div>'
                  + '<div class="text-muted" style="font-size:0.7rem">' + escHtml(item.updated) + '</div>'
                  + '</div>'
                  + statusBadge(item.status)
                  + '</a>';
        });

        container.innerHTML = html;
        container.classList.add('show');
    }

    /**
     * Hide the results dropdown.
     * @param {HTMLElement} container - The dropdown element to hide
     */
    function hideResults(container) {
        container.classList.remove('show');
    }

    /**
     * Bind live search behavior to an input/results pair.
     * Attaches input, click-outside, escape, and focus handlers.
     * @param {HTMLInputElement|null} inputEl - The search input element
     * @param {HTMLElement|null} resultsEl - The results dropdown element
     */
    function bindSearch(inputEl, resultsEl) {
        if (!inputEl || !resultsEl) return;

        /** @type {number|null} Debounce timer ID */
        var timer = null;

        inputEl.addEventListener('input', function () {
            clearTimeout(timer);
            var q = inputEl.value.trim();

            if (q.length < MIN_CHARS) {
                hideResults(resultsEl);
                return;
            }

            timer = setTimeout(function () {
                apiPost('liveSearch', {q: q}, function (resp) {
                    renderResults(resultsEl, resp.results || {});
                });
            }, DEBOUNCE_MS);
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!inputEl.contains(e.target) && !resultsEl.contains(e.target)) {
                hideResults(resultsEl);
            }
        });

        // Close on Escape
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideResults(resultsEl);
                inputEl.blur();
            }
        });

        // Reopen on focus if there are existing results
        inputEl.addEventListener('focus', function () {
            if (inputEl.value.trim().length >= MIN_CHARS && resultsEl.innerHTML) {
                resultsEl.classList.add('show');
            }
        });
    }

    bindSearch(input, results);
    bindSearch(inputMobile, resultsMobile);
})();
