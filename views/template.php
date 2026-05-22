<?php
/**
 * views/template.php
 * Child app — full HTML shell with sidebar + topnav.
 * $current_page_file is set by app/index.php.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>document.documentElement.setAttribute('data-bs-theme','dark');</script>
    <?php $b = get_branding(); ?>
    <title><?= h($page_title ?? 'App') ?> — <?= h($b['site_name']) ?></title>
    <meta name="theme-color" content="<?= h($b['theme_color']) ?>">
    <!-- manifest omitted — admin manifest.php not accessible from Docker context -->
    <?php if (!empty($b['favicon_path'])) { ?>
    <link rel="icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <link rel="apple-touch-icon" href="../../admin/branding/<?= h($b['favicon_path']) ?>">
    <?php } ?>
    <link rel="stylesheet" href="../../admin/assets/css/style.css?v=20260316">
    <link rel="stylesheet" href="../../admin/assets/css/bs-theme-overrides.css">
    <link rel="stylesheet" href="<?= h(get_app_theme_css_url()) ?>" id="themeStylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/elevateher.css?v=7">
</head>
<body data-is-admin="<?= (function_exists('has_role') && has_role('admin')) ? '1' : '0' ?>">

<!-- Lotus canvas (persists across all pages, behind all UI) -->
<canvas id="lotus-canvas" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:0;pointer-events:none;"></canvas>
<div id="lps-viewport" style="position:fixed;inset:0;width:100vw;height:100vh;overflow:hidden;pointer-events:none;z-index:1;"></div>

<div class="d-flex" id="wrapper" style="position:relative;">

    <div id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <div class="d-flex flex-column" id="sidebar">
        <div class="sidebar-brand px-3 py-2">
            <?php if (!empty($b['logo_path'])) { ?>
            <span class="sidebar-brand-icon"><img src="../../admin/branding/<?= h($b['logo_path']) ?>" alt=""<?php if (!empty($b['logo_dark_path'])) { ?> data-logo-dark="../../admin/branding/<?= h($b['logo_dark_path']) ?>" data-logo-light="../../admin/branding/<?= h($b['logo_path']) ?>"<?php } ?>></span>
            <?php } else { ?>
            <span class="sidebar-brand-icon"><i class="bi bi-flower1"></i></span>
            <?php } ?>
            <span class="sidebar-brand-text"><?= h($b['site_name']) ?></span>
        </div>
        <hr class="text-secondary my-0">
        <nav class="nav flex-column p-2 flex-grow-1">
            <!-- All Users -->
            <a class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=dashboard">
                <i class="bi bi-speedometer2 sidebar-icon"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a class="nav-link <?= in_array($page ?? '', ['assessment', 'results']) ? 'active bg-primary rounded' : '' ?>" href="index.php?page=assessment">
                <i class="bi bi-chat-dots sidebar-icon"></i>
                <span class="sidebar-text">My Assessment</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'content' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=content">
                <i class="bi bi-book sidebar-icon"></i>
                <span class="sidebar-text">My Content</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'coaches' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=coaches">
                <i class="bi bi-people sidebar-icon"></i>
                <span class="sidebar-text">My Coaches</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'profile' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=profile">
                <i class="bi bi-person sidebar-icon"></i>
                <span class="sidebar-text">Profile</span>
            </a>

            <hr class="text-secondary my-2">
            <?php
                $alliance_active = in_array($page ?? '', ['team', 'invite']);
                $alliance_unlocked = $is_pro_or_above ?? false;
            ?>
            <?php if ($alliance_unlocked) { ?>
            <a class="nav-link sidebar-alliance-pro <?= $alliance_active ? 'active' : '' ?>" data-bs-toggle="collapse" href="#allianceProMenu" role="button" aria-expanded="<?= $alliance_active ? 'true' : 'false' ?>">
                <i class="bi bi-rocket-takeoff sidebar-icon"></i>
                <span class="sidebar-text">Alliance Pro</span>
                <i class="bi bi-chevron-down sidebar-text sidebar-chevron ms-auto"></i>
            </a>
            <div class="collapse <?= $alliance_active ? 'show' : '' ?>" id="allianceProMenu">
                <nav class="nav flex-column sidebar-submenu">
                    <a class="nav-link <?= ($page ?? '') === 'team' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=team">
                        <i class="bi bi-diagram-3 sidebar-icon"></i>
                        <span class="sidebar-text">My Team</span>
                    </a>
                    <a class="nav-link <?= ($page ?? '') === 'invite' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=invite">
                        <i class="bi bi-link-45deg sidebar-icon"></i>
                        <span class="sidebar-text">Invite Sellers</span>
                    </a>
                </nav>
            </div>
            <?php } else { ?>
            <a class="nav-link sidebar-alliance-pro sidebar-locked" href="#" onclick="event.preventDefault();" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Reach Stage 4 (Pro) to unlock Alliance Pro features">
                <i class="bi bi-rocket-takeoff sidebar-icon"></i>
                <span class="sidebar-text">Alliance Pro</span>
                <i class="bi bi-lock-fill sidebar-lock-icon sidebar-text"></i>
            </a>
            <?php } ?>

            <?php if ($is_elite ?? false) { ?>
            <a class="nav-link sidebar-alliance-elite" href="#" onclick="event.preventDefault();">
                <i class="bi bi-star-fill sidebar-icon"></i>
                <span class="sidebar-text">Alliance Elite</span>
            </a>
            <?php } else { ?>
            <a class="nav-link sidebar-alliance-elite sidebar-locked" href="#" onclick="event.preventDefault();" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Reach Stage 7 (Elite) to unlock Alliance Elite features">
                <i class="bi bi-star-fill sidebar-icon"></i>
                <span class="sidebar-text">Alliance Elite</span>
                <i class="bi bi-lock-fill sidebar-lock-icon sidebar-text"></i>
            </a>
            <?php } ?>

            <?php if ($is_org_lead_user) { ?>
            <hr class="text-secondary my-2">
            <a class="nav-link <?= ($page ?? '') === 'cohort' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=cohort">
                <i class="bi bi-graph-up sidebar-icon"></i>
                <span class="sidebar-text">Cohort Dashboard</span>
            </a>
            <?php } ?>

            <?php if (function_exists('has_role') && has_role('admin')) { ?>
            <hr class="text-secondary my-2">
            <small class="text-muted text-uppercase px-3 sidebar-text" style="font-size:.65rem; letter-spacing:.05em;">Admin</small>
            <a class="nav-link <?= ($page ?? '') === 'admin-stages' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=admin-stages">
                <i class="bi bi-diagram-3 sidebar-icon"></i>
                <span class="sidebar-text">Stages</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'admin-content' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=admin-content">
                <i class="bi bi-journal-text sidebar-icon"></i>
                <span class="sidebar-text">Content Catalog</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'admin-proposals' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=admin-proposals">
                <i class="bi bi-chat-left-text sidebar-icon"></i>
                <span class="sidebar-text">Proposals</span>
            </a>
            <a class="nav-link <?= ($page ?? '') === 'admin-framework' ? 'active bg-primary rounded' : '' ?>" href="index.php?page=admin-framework">
                <i class="bi bi-database sidebar-icon"></i>
                <span class="sidebar-text">Framework Sync</span>
            </a>
            <?php } ?>
        </nav>
        <div class="sidebar-toggle-area text-center d-none d-sm-block p-2 border-top border-secondary border-opacity-25">
            <button class="btn btn-sm btn-outline-light rounded-circle" id="sidebarToggle" type="button">
                <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
            </button>
        </div>
    </div>

    <!-- Main content -->
    <div class="flex-grow-1 d-flex flex-column min-vh-100" style="min-width:0">

        <!-- Topnav -->
        <nav class="navbar navbar-expand-lg border-bottom border-secondary border-opacity-25 px-3 py-2 sticky-top">
            <button class="btn btn-sm btn-outline-light me-2 d-sm-none" id="sidebarToggleTop" type="button">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand mb-0 h6">&nbsp;</span>

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

                <!-- User dropdown (first name only) -->
                <div class="dropdown me-2">
                    <a class="btn btn-sm btn-outline-light dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <?= h($_SESSION['first_name'] ?? '') ?>
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

                <!-- Right panel toggle -->
                <button class="btn btn-sm btn-outline-light" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#rightPanel" title="Settings">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
            </div>
        </nav>

        <div class="container-fluid p-4 flex-grow-1">
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
            $_SESSION['success'] = null;
            $_SESSION['error']   = null;
            $_SESSION['warning'] = null;
            $_SESSION['info']    = null;
            ?>

            <div id="content-dynamic">
            <?php if (isset($current_page_file) && file_exists($current_page_file)) {
                include($current_page_file);
            } ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="app-footer px-4 py-2 d-flex align-items-center border-top border-secondary border-opacity-25">
            <nav class="small">
                <a href="#" class="text-muted text-decoration-none me-3">Terms of Use</a>
                <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                <?php if (function_exists('has_role') && has_role('admin')) { ?>
                <a href="../../admin/app/" class="badge bg-danger bg-opacity-75 text-decoration-none"><i class="bi bi-shield-lock me-1"></i>Admin</a>
                <?php } ?>
            </nav>
            <span class="small text-muted ms-auto">&copy; <?= date('Y') ?> <?= h(CHILD_APP_NAME) ?></span>
        </footer>
    </div>
</div>

<!-- Large modal -->
<div class="modal fade" id="largeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="modal-content"></div>
    </div>
</div>

<!-- Right panel -->
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
            </div>
            <div class="tab-pane fade" id="panelAbout" role="tabpanel">
                <h6 class="small fw-bold text-uppercase text-muted mb-3">Application</h6>
                <dl class="small mb-0">
                    <dt class="text-muted">App</dt>
                    <dd><?= h(CHILD_APP_NAME) ?></dd>
                    <dt class="text-muted">Version</dt>
                    <dd><?= defined('WN_CHILD_APP_VERSION') ? WN_CHILD_APP_VERSION : '1.0.0' ?></dd>
                    <dt class="text-muted">Framework</dt>
                    <dd>Bootstrap 5.3 + PHP 8.2</dd>
                    <dt class="text-muted">Theme</dt>
                    <dd>DSWA</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../admin/assets/js/bs-init.js?v=2"></script>
<script src="../../admin/assets/js/error-reporter.js"></script>
<script src="../../admin/assets/js/sidebar.js"></script>
<script src="../../admin/assets/js/color-mode.js"></script>
<script src="../assets/js/modal.js"></script>
<script src="../assets/js/theme.js"></script>
<script src="../assets/js/toast.js"></script>
<script src="../assets/js/page-nav.js"></script>
<script src="../../admin/assets/js/notifications.js"></script>
<script src="../assets/js/lotus-canvas.js"></script>
<script src="../assets/js/water-ripples.js"></script>
<!-- chat-overlay.js loaded inside assessment.php for SPA compat -->
<script src="../assets/js/explorer-view.js?v=5"></script>
<script src="../assets/js/spa-nav.js"></script>
<script>
// Collapse sidebar by default for immersive lotus experience
(function() {
    if (!localStorage.getItem('wn_sidebar_state')) {
        localStorage.setItem('wn_sidebar_state', 'collapsed');
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.add('collapsed');
    }
})();
</script>
<?php if (file_exists(__DIR__ . '/../snippets/feedback_tab.php')) { include(__DIR__ . '/../snippets/feedback_tab.php'); } ?>
</body>
</html>
