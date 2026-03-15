/**
 * theme.js — Child app theme switcher
 * Extends admin's Bootswatch theme system with a "Custom" option
 * that loads the locally compiled custom.css, plus registered themes
 * from sibling apps.
 *
 * The theme selector dropdown has data attributes:
 *   data-has-custom="1|0"            — whether custom.css exists on the server
 *   data-custom-css="..."            — relative path to custom.css
 *   data-registered-themes='[...]'   — JSON array of registered themes from DB
 */

(function() {
    var selector = document.getElementById('themeSelector');
    if (!selector) return;

    var link = document.getElementById('themeStylesheet');
    if (!link) return;

    var BOOTSWATCH_API = 'https://bootswatch.com/api/5.json';
    var STORAGE_KEY = 'wn_theme';
    var hasCustom = selector.getAttribute('data-has-custom') === '1';
    var customCssUrl = selector.getAttribute('data-custom-css') || '../assets/css/custom.css';

    // Parse registered custom themes from data attribute
    var registeredThemes = [];
    try {
        registeredThemes = JSON.parse(selector.getAttribute('data-registered-themes') || '[]');
    } catch (e) {}

    // Default to "custom" if custom.css exists, otherwise "sandstone"
    var defaultTheme = hasCustom ? 'custom' : 'sandstone';
    var savedTheme = localStorage.getItem(STORAGE_KEY) || defaultTheme;

    // If custom.css was removed but user had "custom" selected, fall back
    if (savedTheme === 'custom' && !hasCustom) {
        savedTheme = 'sandstone';
    }

    function saveTheme(name) {
        localStorage.setItem(STORAGE_KEY, name);
        document.cookie = STORAGE_KEY + '=' + encodeURIComponent(name) + ';path=/;max-age=31536000;SameSite=Lax';
    }

    // Sync cookie
    var cookieMatch = document.cookie.match(new RegExp('(?:^|; )' + STORAGE_KEY + '=([^;]*)'));
    var cookieTheme = cookieMatch ? decodeURIComponent(cookieMatch[1]) : null;
    if (cookieTheme !== savedTheme) {
        saveTheme(savedTheme);
    }

    // Build dropdown options
    function buildDropdown(bootswatchThemes) {
        selector.innerHTML = '';

        // "Custom" option first (only if custom.css exists)
        if (hasCustom) {
            var customOpt = document.createElement('option');
            customOpt.value = 'custom';
            customOpt.textContent = 'Custom (default)';
            if (savedTheme === 'custom') customOpt.selected = true;
            selector.appendChild(customOpt);
        }

        // Bootswatch themes
        bootswatchThemes.forEach(function(theme) {
            var opt = document.createElement('option');
            opt.value = theme.name.toLowerCase();
            opt.textContent = theme.name;
            if (opt.value === savedTheme) opt.selected = true;
            selector.appendChild(opt);
        });

        // Registered custom themes (skip 'custom' slug to avoid duplicate)
        var filtered = registeredThemes.filter(function(t) { return t.slug !== 'custom'; });
        if (filtered.length > 0) {
            var sep = document.createElement('option');
            sep.disabled = true;
            sep.textContent = '── Registered ──';
            selector.appendChild(sep);

            filtered.forEach(function(theme) {
                var opt = document.createElement('option');
                opt.value = theme.slug;
                opt.textContent = theme.name;
                if (opt.value === savedTheme) opt.selected = true;
                selector.appendChild(opt);
            });
        }
    }

    // Fetch Bootswatch themes
    fetch(BOOTSWATCH_API)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            buildDropdown(data.themes);
        })
        .catch(function() {
            // Fallback if API is unreachable
            buildDropdown([{ name: 'Sandstone' }]);
        });

    // Theme change handler
    selector.addEventListener('change', function() {
        var theme = this.value;
        saveTheme(theme);
        applyTheme(theme);
    });

    // Determine sidebar mode for a theme name
    function getSidebarMode(name) {
        if (name === 'custom' && hasCustom) return 'glass';
        var reg = registeredThemes.find(function(t) { return t.slug === name; });
        if (reg) return reg.sidebar_mode;
        return 'dark';
    }

    // Toggle sidebar/navbar between glass (transparent) and dark (standard)
    function setSidebarMode(mode) {
        var sidebar = document.getElementById('sidebar');
        var navbar = document.querySelector('#wrapper > .flex-grow-1 > .navbar');
        var navLinks = sidebar ? sidebar.querySelectorAll('.nav-link') : [];
        var canvas = document.getElementById('bgCanvas');

        if (mode === 'glass') {
            // Glass theme: transparent sidebar, no forced dark
            if (sidebar) {
                sidebar.removeAttribute('data-bs-theme');
                sidebar.classList.remove('bg-dark', 'text-white');
            }
            if (navbar) {
                navbar.removeAttribute('data-bs-theme');
                navbar.classList.remove('bg-dark');
            }
            navLinks.forEach(function(l) { l.classList.remove('text-white'); });
            if (canvas) canvas.style.display = '';
        } else {
            // Dark mode: match admin template exactly
            if (sidebar) {
                sidebar.setAttribute('data-bs-theme', 'dark');
                sidebar.classList.add('bg-dark', 'text-white');
            }
            if (navbar) {
                navbar.setAttribute('data-bs-theme', 'dark');
                navbar.classList.add('bg-dark');
            }
            navLinks.forEach(function(l) { l.classList.add('text-white'); });
            if (canvas) canvas.style.display = 'none';
        }
    }

    function applyTheme(name) {
        var mode = getSidebarMode(name);
        setSidebarMode(mode);

        // Local custom theme
        if (name === 'custom' && hasCustom) {
            link.href = customCssUrl;
            return;
        }

        // Registered custom theme
        var reg = registeredThemes.find(function(t) { return t.slug === name; });
        if (reg) {
            var newLink = document.createElement('link');
            newLink.rel = 'stylesheet';
            newLink.href = '../../' + reg.css_path;
            newLink.onerror = function() {
                saveTheme(defaultTheme);
                link.href = hasCustom ? customCssUrl : '../../admin/assets/bootstrap/css/bootstrap.min.css';
                setSidebarMode(getSidebarMode(defaultTheme));
                newLink.remove();
            };
            newLink.onload = function() {
                link.href = newLink.href;
                newLink.remove();
            };
            document.head.appendChild(newLink);
            return;
        }

        if (name === 'default' || name === 'bootstrap') {
            link.href = '../../admin/assets/bootstrap/css/bootstrap.min.css';
            return;
        }

        // Load Bootswatch theme from CDN
        var newLink = document.createElement('link');
        newLink.rel = 'stylesheet';
        newLink.href = 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/' + name + '/bootstrap.min.css';
        newLink.onerror = function() {
            saveTheme(defaultTheme);
            link.href = hasCustom ? customCssUrl : '../../admin/assets/bootstrap/css/bootstrap.min.css';
            setSidebarMode(getSidebarMode(defaultTheme));
            newLink.remove();
        };
        newLink.onload = function() {
            link.href = newLink.href;
            newLink.remove();
        };
        document.head.appendChild(newLink);
    }

    // Apply correct mode on page load
    setSidebarMode(getSidebarMode(savedTheme));
})();
