<?php
/**
 * views/template.php
 * Full HTML shell with sidebar + topnav + theme switcher.
 * References admin's shared assets. Child-app-specific CSS loaded if present.
 * $current_page_file is set by app/index.php.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
    (function(){var s=localStorage.getItem('wn_color_mode');
    var m=s||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-bs-theme',m);})();
    </script>
    <?php $b = get_branding(); ?>
    <title><?= h($page_title ?? 'App') ?> — <?= h($b['site_name']) ?></title>
    <meta name="theme-color" content="<?= h($b['theme_color']) ?>">
    <link rel="manifest" href="../../admin/manifest.php">
    <?php if (!empty($b['favicon_path'])) { ?>
    <link rel="icon" href="../../admin/uploads/<?= h($b['favicon_path']) ?>">
    <link rel="apple-touch-icon" href="../../admin/uploads/<?= h($b['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="../../admin/assets/css/style.css?v=20260316">
    <link rel="stylesheet" href="../../admin/assets/css/bs-theme-overrides.css">
    <link rel="stylesheet" href="<?= h(get_app_theme_css_url()) ?>" id="themeStylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<!-- REGION: canvas background -- structural layer for gamified apps -->
<canvas id="bgCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>

<div class="d-flex" id="wrapper" style="position:relative;z-index:1;">

    <!-- REGION: sidebar backdrop (real element — inside #wrapper stacking context so sidebar z-index works) -->
    <div id="sidebarBackdrop"></div>

    <!-- REGION: sidebar -->
    <div class="d-flex flex-column" id="sidebar">
        <div class="sidebar-brand px-3 py-2">
            <?php if (!empty($b['logo_path'])) { ?>
            <span class="sidebar-brand-icon"><img src="../../admin/uploads/<?= h($b['logo_path']) ?>" alt=""<?php if (!empty($b['logo_dark_path'])) { ?> data-logo-dark="../../admin/uploads/<?= h($b['logo_dark_path']) ?>" data-logo-light="../../admin/uploads/<?= h($b['logo_path']) ?>"<?php } ?>></span>
            <?php } else { ?>
            <span class="sidebar-brand-icon"><i class="bi bi-app-indicator"></i></span>
            <?php } ?>
            <span class="sidebar-brand-text"><?= h($b['site_name']) ?></span>
        </div>
        <hr class="text-secondary my-0">
        <nav class="nav flex-column p-2 flex-grow-1">
            <a class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=dashboard">
                <i class="bi bi-speedometer2 sidebar-icon"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'items' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=items">
                <i class="bi bi-collection sidebar-icon"></i>
                <span class="sidebar-text">Items</span>
                <span class="badge <?= ($page ?? '') === 'items' ? 'bg-light text-primary' : 'bg-primary' ?> sidebar-badge"><?= $item_count ?></span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'profile' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=profile">
                <i class="bi bi-person sidebar-icon"></i>
                <span class="sidebar-text">Profile</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'examples' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=examples">
                <i class="bi bi-grid sidebar-icon"></i>
                <span class="sidebar-text">Examples</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'ui-guide' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=ui-guide">
                <i class="bi bi-book sidebar-icon"></i>
                <span class="sidebar-text">UI Guide</span>
            </a>
            <!-- REMOVABLE: HTML5 Game demo — delete this link + views/game.php + assets/js/pong-game.js + route in app/index.php -->
            <a class="nav-link <?= ($page ?? '') === 'game' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=game">
                <i class="bi bi-controller sidebar-icon"></i>
                <span class="sidebar-text">HTML5 Game</span>
            </a>
        </nav>
        <div class="sidebar-toggle-area text-center d-none d-sm-block p-2 border-top border-secondary border-opacity-25">
            <button class="btn btn-sm btn-outline-light rounded-circle" id="sidebarToggle" type="button">
                <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
            </button>
        </div>
    </div>

    <!-- Main content -->
    <div class="flex-grow-1 d-flex flex-column min-vh-100">

        <!-- REGION: topnav -->
        <nav class="navbar navbar-expand-lg border-bottom border-secondary border-opacity-25 px-3 py-2 sticky-top">
            <button class="btn btn-sm btn-outline-light me-2 d-md-none" id="sidebarToggleTop" type="button">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand mb-0 h6">&nbsp;</span>

            <!-- Live search -->
            <div class="nav-search position-relative d-none d-md-block me-auto" id="navSearch">
                <div class="input-group input-group-sm">
                    <span class="input-group-text border-0 bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control form-control-sm border-0" id="navSearchInput"
                           placeholder="Search items..." autocomplete="off" aria-label="Search">
                </div>
                <div class="nav-search-results dropdown-menu shadow" id="navSearchResults"></div>
            </div>
            <!-- Mobile search toggle -->
            <button class="btn btn-sm btn-outline-light me-2 d-md-none" id="navSearchToggle" type="button" title="Search">
                <i class="bi bi-search"></i>
            </button>

            <div class="ms-auto d-flex align-items-center">
                <!-- Notification bell -->
                <div class="dropdown me-2" id="notificationBell">
                    <button class="btn btn-sm btn-outline-light position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadge">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow" style="width: 360px; max-height: 420px; overflow-y: auto;" id="notifDropdown">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong class="small">Notifications</strong>
                            <a href="#" id="markAllReadBtn" class="text-muted small text-decoration-none">Mark all read</a>
                        </div>
                        <div id="notifList"><div class="text-center py-3 text-muted small">Loading...</div></div>
                    </div>
                </div>

                <!-- Color mode toggle -->
                <button class="btn btn-sm btn-outline-light me-2" id="colorModeToggle" type="button" title="Toggle dark mode">
                    <i class="bi bi-moon-fill"></i>
                </button>

                <!-- Theme switcher (hidden on small screens to prevent topnav wrapping) -->
                <select class="form-select form-select-sm me-2 d-none d-lg-inline-block" id="themeSelector" style="width: auto;"
                        data-custom-css="../assets/css/custom.css"
                        data-has-custom="<?= has_custom_theme() ? '1' : '0' ?>"
                        data-registered-themes='<?= get_registered_themes_json() ?>'>
                    <option value="custom">Custom</option>
                </select>

                <!-- User dropdown -->
                <div class="dropdown me-2">
                    <a class="btn btn-sm btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <?= h($_SESSION['first_name'] ?? '') ?><span class="d-none d-md-inline"> <?= h($_SESSION['last_name'] ?? '') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?= h($_SESSION['email'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=profile"><i class="bi bi-person me-2"></i>Profile & Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>

                <!-- Right panel toggle (rightmost — closest to panel) -->
                <button class="btn btn-sm btn-outline-light" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#rightPanel" title="Settings">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
            </div>
        </nav>

        <!-- Mobile search bar (shown via toggle) -->
        <div class="nav-search-mobile d-md-none px-3 py-2 border-bottom border-secondary border-opacity-25" id="navSearchMobile" style="display:none;">
            <div class="position-relative">
                <div class="input-group input-group-sm">
                    <span class="input-group-text border-0 bg-transparent"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control form-control-sm border-0" id="navSearchInputMobile"
                           placeholder="Search items..." autocomplete="off" aria-label="Search">
                </div>
                <div class="nav-search-results dropdown-menu shadow" id="navSearchResultsMobile"></div>
            </div>
        </div>

        <div class="container-fluid p-4 flex-grow-1">
            <!-- REGION: toast data (read by toast.js on load, then session cleared) -->
            <?php
            $toast_success = $_SESSION['success'] ?? '';
            $toast_error   = $_SESSION['error']   ?? '';
            $toast_warning = $_SESSION['warning'] ?? '';
            $toast_info    = $_SESSION['info']     ?? '';
            ?>
            <div id="toast-data" class="d-none"
                 data-success="<?= h($toast_success) ?>"
                 data-error="<?= h($toast_error) ?>"
                 data-warning="<?= h($toast_warning) ?>"
                 data-info="<?= h($toast_info) ?>"></div>
            <?php
            // Clear session so feedback.php doesn't double-show as inline alerts
            $_SESSION['success'] = null;
            $_SESSION['error']   = null;
            $_SESSION['warning'] = null;
            $_SESSION['info']    = null;
            ?>

            <!-- REGION: content (replaced by spa-nav.js during SPA navigation) -->
            <div id="content-dynamic">
            <?php if (isset($current_page_file) && file_exists($current_page_file)) {
                include($current_page_file);
            } ?>
            </div>
        </div>

        <!-- REGION: footer -->
        <footer class="app-footer px-4 py-2 d-flex align-items-center border-top border-secondary border-opacity-25">
            <nav class="small">
                <a href="#" class="text-muted text-decoration-none me-3">Terms of Use</a>
                <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                <?php if (function_exists('has_role') && has_role('admin')) { ?>
                <a href="../../admin/app/" class="badge bg-danger bg-opacity-75 text-decoration-none"><i class="bi bi-shield-lock me-1"></i>Admin</a>
                <?php } ?>
            </nav>
            <span class="small text-muted ms-auto">Intended use only &copy; <?= date('Y') ?> <?= h($b['site_name']) ?></span>
        </footer>
    </div>
</div>

<!-- REGION: large modal (AJAX content via modalView()) -->
<div class="modal fade" id="largeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="modal-content">
            <!-- Dynamic content loaded by modalView() -->
        </div>
    </div>
</div>

<!-- REGION: right panel (offcanvas) -->
<div class="offcanvas offcanvas-end" id="rightPanel" tabindex="-1" aria-labelledby="rightPanelLabel">
    <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
        <h6 class="offcanvas-title" id="rightPanelLabel"><i class="bi bi-gear me-2"></i>Settings</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <ul class="nav nav-tabs px-3 pt-2" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#panelGeneral" type="button" role="tab">General</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#panelPrefs" type="button" role="tab">Preferences</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#panelAbout" type="button" role="tab">About</button>
            </li>
        </ul>
        <div class="tab-content p-3">
            <div class="tab-pane fade show active" id="panelGeneral" role="tabpanel">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Notification Settings</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="notifEmail" checked>
                    <label class="form-check-label small" for="notifEmail">Email notifications</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="notifPush">
                    <label class="form-check-label small" for="notifPush">Push notifications</label>
                </div>
                <hr class="border-secondary border-opacity-25">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Display</h6>
                <div class="mb-2">
                    <label class="form-label small">Items per page</label>
                    <select class="form-select form-select-sm">
                        <option>10</option>
                        <option selected>15</option>
                        <option>25</option>
                        <option>50</option>
                    </select>
                </div>
            </div>
            <div class="tab-pane fade" id="panelPrefs" role="tabpanel">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Layout</h6>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="layout" id="layoutDefault" checked>
                    <label class="form-check-label small" for="layoutDefault">Default sidebar</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="layout" id="layoutCompact">
                    <label class="form-check-label small" for="layoutCompact">Compact sidebar</label>
                </div>
                <hr class="border-secondary border-opacity-25">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Default View</h6>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="defaultView" id="viewDashboard" checked>
                    <label class="form-check-label small" for="viewDashboard">Dashboard</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="defaultView" id="viewItems">
                    <label class="form-check-label small" for="viewItems">Items</label>
                </div>
            </div>
            <div class="tab-pane fade" id="panelAbout" role="tabpanel">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Application</h6>
                <dl class="small mb-0">
                    <dt class="text-muted">Version</dt>
                    <dd>1.0.0</dd>
                    <dt class="text-muted">Environment</dt>
                    <dd>Development</dd>
                    <dt class="text-muted">Framework</dt>
                    <dd>Bootstrap 5.3 + PHP</dd>
                    <dt class="text-muted">Theme</dt>
                    <dd>Glassmorphism (Custom)</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- REGION: footer-scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../admin/assets/js/bs-init.js"></script>
<script src="../../admin/assets/js/error-reporter.js"></script>
<script src="../../admin/assets/js/sidebar.js"></script>
<script src="../../admin/assets/js/color-mode.js"></script>
<script src="../assets/js/modal.js"></script>
<script src="../assets/js/theme.js"></script>
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/search.js"></script>
<script src="../assets/js/page-nav.js"></script>
<script src="../../admin/assets/js/notifications.js"></script>
<script src="../assets/js/bg-canvas.js"></script>
<!-- REMOVABLE: Pong game — delete this line + assets/js/pong-game.js + views/game.php + sidebar link + route in app/index.php -->
<script src="../assets/js/pong-game.js"></script>
<script src="../assets/js/spa-nav.js"></script>
</body>
</html>
