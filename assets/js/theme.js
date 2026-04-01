/**
 * @module theme
 * @description ElevateHER always uses the DSWA theme (glass mode).
 * No theme switching is provided. This script forces the DSWA theme
 * cookie/localStorage value and removes opaque sidebar/navbar styles
 * so the glass (transparent) appearance is always active.
 */
(function() {
    'use strict';

    // Force DSWA theme cookie so PHP always resolves correctly
    document.cookie = 'wn_theme=dswa;path=/;max-age=31536000;SameSite=Lax';
    localStorage.setItem('wn_theme', 'dswa');

    // Always glass mode: transparent sidebar + navbar
    var sidebar = document.getElementById('sidebar');
    var navbar = document.querySelector('#wrapper > .flex-grow-1 > .navbar');

    if (sidebar) {
        sidebar.removeAttribute('data-bs-theme');
        sidebar.classList.remove('bg-dark', 'text-white');
    }
    if (navbar) {
        navbar.removeAttribute('data-bs-theme');
        navbar.classList.remove('bg-dark');
    }

    // Remove any leftover theme selector elements from the admin core
    var sel = document.getElementById('themeSelector');
    if (sel) {
        var wrap = sel.closest('.me-2, .dropdown');
        if (wrap) wrap.remove(); else sel.remove();
    }
})();
