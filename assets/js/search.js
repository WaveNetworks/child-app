/**
 * search.js — Live search in the top navigation bar.
 * Debounced input → apiPost('liveSearch') → renders dropdown results.
 * Works on both desktop (#navSearchInput) and mobile (#navSearchInputMobile).
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 300;
    var MIN_CHARS   = 2;

    // Desktop elements
    var input   = document.getElementById('navSearchInput');
    var results = document.getElementById('navSearchResults');

    // Mobile elements
    var inputMobile   = document.getElementById('navSearchInputMobile');
    var resultsMobile = document.getElementById('navSearchResultsMobile');
    var mobileBar     = document.getElementById('navSearchMobile');
    var mobileToggle  = document.getElementById('navSearchToggle');

    // Mobile toggle
    if (mobileToggle && mobileBar) {
        mobileToggle.addEventListener('click', function () {
            var visible = mobileBar.style.display !== 'none';
            mobileBar.style.display = visible ? 'none' : 'block';
            if (!visible && inputMobile) inputMobile.focus();
        });
    }

    function statusBadge(status) {
        var cls = {active: 'bg-success', draft: 'bg-warning text-dark', archived: 'bg-secondary'};
        return '<span class="badge ' + (cls[status] || 'bg-light text-dark') + ' ms-auto">' + escHtml(status) + '</span>';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

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

    function hideResults(container) {
        container.classList.remove('show');
    }

    function bindSearch(inputEl, resultsEl) {
        if (!inputEl || !resultsEl) return;

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

        // Reopen on focus if there's text
        inputEl.addEventListener('focus', function () {
            if (inputEl.value.trim().length >= MIN_CHARS && resultsEl.innerHTML) {
                resultsEl.classList.add('show');
            }
        });
    }

    bindSearch(input, results);
    bindSearch(inputMobile, resultsMobile);
})();
