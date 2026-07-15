<?php
/**
 * view_map.php — page name → view file. THE single source of the mobile screen list
 * (child-app spec 05). Shared by app/index.php's ?page= router and core's build scripts.
 *
 * `mobile` marks screens the mobile shell ships. `tab` → bottom-bar/primary label,
 * `menu` → slide-out drawer label, `icon` → a REAL Bootstrap Icons name (the build fails
 * on a made-up one). Edit this list to add/remove mobile screens for your app.
 */
return [
    'dashboard' => ['file' => 'dashboard.php', 'mobile' => true,  'tab' => 'Home',    'menu' => 'Dashboard',        'icon' => 'house'],
    'items'     => ['file' => 'items.php',     'mobile' => true,  'tab' => 'Items',   'menu' => 'Items',            'icon' => 'grid'],
    'profile'   => ['file' => 'profile.php',   'mobile' => true,  'tab' => 'You',     'menu' => 'Profile & Settings','icon' => 'person'],
    'feedback'  => ['file' => 'feedback.php',  'mobile' => true,                      'menu' => 'Feedback',         'icon' => 'chat-left-text'],

    // Desktop-only meta pages — long-form reading, not phone surfaces.
    'privacy'   => ['file' => 'privacy.php',   'mobile' => false],
    'examples'  => ['file' => 'examples.php',  'mobile' => false],
    'ui-guide'  => ['file' => 'ui-guide.php',  'mobile' => false],
];
