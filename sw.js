/**
 * Service Worker — Proxy for child-app
 * Imports admin's service worker so push notifications work from child-app context.
 * notifications.js registers '../sw.js' which resolves to /child-app/sw.js
 */
importScripts('/admin/sw.js');
