/**
 * @module toast
 * @description Toast notification system for child-app.
 * Vanilla JS, no dependencies beyond Bootstrap 5.
 *
 * Two modes:
 *   1. Auto-converts session flash messages (data-toast attributes on #toast-data)
 *   2. Manual: showToast('success', 'Item saved!') from JS/AJAX callbacks
 *
 * Toast types: success, danger, warning, info
 */

(function () {
    'use strict';

    var CONTAINER_ID = 'toastContainer';
    var AUTO_HIDE_MS = 2000;
    var counter = 0;

    // Icon map for toast types
    var icons = {
        success: 'bi-check-circle-fill',
        danger:  'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill'
    };

    var labels = {
        success: 'Success',
        danger:  'Error',
        warning: 'Warning',
        info:    'Info'
    };

    /**
     * Ensure the fixed toast container exists in the DOM.
     * @returns {HTMLElement} The toast container element
     */
    function getContainer() {
        var el = document.getElementById(CONTAINER_ID);
        if (!el) {
            el = document.createElement('div');
            el.id = CONTAINER_ID;
            el.className = 'toast-container position-fixed top-0 end-0 p-3';
            el.style.zIndex = '1090';
            document.body.appendChild(el);
        }
        return el;
    }

    /**
     * Show a toast notification.
     *
     * @param {string} type    - 'success' | 'danger' | 'warning' | 'info'
     * @param {string} message - HTML-safe message text
     * @param {object} opts    - Optional: { delay: ms, title: string }
     */
    window.showToast = function (type, message, opts) {
        opts = opts || {};
        var delay = opts.delay || AUTO_HIDE_MS;
        var title = opts.title || labels[type] || 'Notice';
        var icon  = icons[type] || icons.info;
        var id    = 'toast-' + (++counter);

        var html =
            '<div id="' + id + '" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="' + delay + '">' +
                '<div class="toast-header">' +
                    '<i class="bi ' + icon + ' text-' + type + ' me-2"></i>' +
                    '<strong class="me-auto">' + title + '</strong>' +
                    '<small class="text-muted">just now</small>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
                '</div>' +
                '<div class="toast-body">' + message + '</div>' +
            '</div>';

        var container = getContainer();
        container.insertAdjacentHTML('beforeend', html);

        var toastEl = document.getElementById(id);
        var toast   = new bootstrap.Toast(toastEl);
        toast.show();

        // Clean up DOM after hidden
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastEl.remove();
        });
    };

    /**
     * On page load: read session flash data from hidden element and fire toasts.
     * The template outputs: <div id="toast-data" data-success="..." data-error="...">
     */
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('toast-data');
        if (!el) return;

        var map = {
            success: el.getAttribute('data-success'),
            danger:  el.getAttribute('data-error'),
            warning: el.getAttribute('data-warning'),
            info:    el.getAttribute('data-info')
        };

        Object.keys(map).forEach(function (type) {
            if (map[type]) {
                showToast(type, map[type]);
            }
        });
    });
})();
