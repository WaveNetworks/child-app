<?php
/**
 * views/ui-guide.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Bootstrap 5 UI Component Reference
 *
 * PURPOSE: This file serves as a living style guide and component reference
 * for the Wave Networks child-app template. When this repo is forked for a
 * new project, developers (and Claude Code) can reference this page to see
 * every Bootstrap 5 component rendered with the active theme.
 *
 * SECTIONS: Typography · Buttons · Cards · Forms · Tables · Modals · Tabs
 *           Alerts · Badges · Breadcrumbs · Dropdowns · List Groups
 *           Pagination · Progress · Spinners · Toasts · Accordions
 *           Offcanvas · Tooltips & Popovers · Nav Variants
 * ─────────────────────────────────────────────────────────────────────────────
 */
$page_title = 'UI Guide';
?>

<!-- ─── Page Header + Secondary Nav Toggle ────────────────────────────── -->
<div class="d-flex align-items-center mb-3">
    <button class="btn btn-sm btn-outline-light me-3" id="pageNavToggle"
            aria-expanded="true" aria-controls="pageNav" title="Toggle section navigation">
        <i class="bi bi-layout-sidebar"></i>
    </button>
    <h4 class="mb-0"><i class="bi bi-book me-2"></i>Bootstrap 5 UI Guide</h4>
    <span class="badge bg-secondary ms-auto">v5.3</span>
</div>

<div class="d-flex position-relative" id="pageNavWrapper">

    <!-- ─── Secondary Page Nav ─────────────────────────────────────────────── -->
    <div class="page-nav" id="pageNav">
        <!-- Profile / branding box (placeholder — swap for real content per app) -->
        <div class="page-nav-profile text-center mb-3 p-3 rounded-3">
            <div class="mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: rgba(99,102,241,0.15); border: 2px solid rgba(99,102,241,0.3);">
                <i class="bi bi-person-fill" style="font-size: 1.75rem; color: rgba(99,102,241,0.8);"></i>
            </div>
            <div class="fw-semibold small"><?= h(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></div>
            <div class="text-muted" style="font-size: 0.7rem;"><?= h($_SESSION['email'] ?? '') ?></div>
        </div>

        <nav class="page-nav-inner">
            <div class="small fw-bold text-muted text-uppercase mb-2">Components</div>
            <nav class="nav flex-column small" id="guideNav">
                <a class="nav-link px-0 py-1" href="#typography">Typography</a>
                <a class="nav-link px-0 py-1" href="#buttons">Buttons</a>
                <a class="nav-link px-0 py-1" href="#cards">Cards</a>
                <a class="nav-link px-0 py-1" href="#forms">Forms</a>
                <a class="nav-link px-0 py-1" href="#tables">Tables</a>
                <a class="nav-link px-0 py-1" href="#modals">Modals</a>
                <a class="nav-link px-0 py-1" href="#tabs">Tabs</a>
                <a class="nav-link px-0 py-1" href="#alerts">Alerts</a>
                <a class="nav-link px-0 py-1" href="#badges">Badges</a>
                <a class="nav-link px-0 py-1" href="#breadcrumbs">Breadcrumbs</a>
                <a class="nav-link px-0 py-1" href="#dropdowns">Dropdowns</a>
                <a class="nav-link px-0 py-1" href="#listgroups">List Groups</a>
                <a class="nav-link px-0 py-1" href="#pagination">Pagination</a>
                <a class="nav-link px-0 py-1" href="#progress">Progress</a>
                <a class="nav-link px-0 py-1" href="#spinners">Spinners</a>
                <a class="nav-link px-0 py-1" href="#toasts">Toasts</a>
                <a class="nav-link px-0 py-1" href="#accordions">Accordions</a>
                <a class="nav-link px-0 py-1" href="#offcanvas">Offcanvas</a>
                <a class="nav-link px-0 py-1" href="#tooltips">Tooltips &amp; Popovers</a>
                <a class="nav-link px-0 py-1" href="#navvariants">Nav Variants</a>
            </nav>
        </nav>
    </div>

    <!-- ─── Backdrop (mobile overlay) ──────────────────────────────────────── -->
    <div class="page-nav-backdrop" id="pageNavBackdrop"></div>

    <!-- ─── Main Content ───────────────────────────────────────────────────── -->
    <div class="page-nav-content flex-grow-1">


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- TYPOGRAPHY                                                         -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="typography" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-type me-2"></i>Typography</h5>
            <div class="card mb-3">
                <div class="card-body">
                    <h1>h1. Heading</h1>
                    <h2>h2. Heading</h2>
                    <h3>h3. Heading</h3>
                    <h4>h4. Heading</h4>
                    <h5>h5. Heading</h5>
                    <h6>h6. Heading</h6>
                    <hr>
                    <p class="display-1">Display 1</p>
                    <p class="display-4">Display 4</p>
                    <p class="display-6">Display 6</p>
                    <hr>
                    <p class="lead">This is a lead paragraph — it stands out from regular paragraphs.</p>
                    <p>This is a regular paragraph with <strong>bold</strong>, <em>italic</em>, <u>underline</u>, <mark>highlighted</mark>, <del>deleted</del>, and <small>small</small> text.</p>
                    <p><abbr title="Hypertext Markup Language">HTML</abbr> abbreviation with a dotted underline.</p>
                    <blockquote class="blockquote">
                        <p>A well-known quote, contained in a blockquote element.</p>
                    </blockquote>
                    <figcaption class="blockquote-footer">Someone famous in <cite title="Source Title">Source Title</cite></figcaption>
                    <hr>
                    <p class="text-muted">text-muted</p>
                    <p class="text-primary">text-primary</p>
                    <p class="text-success">text-success</p>
                    <p class="text-danger">text-danger</p>
                    <p class="text-warning">text-warning</p>
                    <p class="text-info">text-info</p>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- BUTTONS                                                            -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="buttons" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-hand-index me-2"></i>Buttons</h5>

            <div class="card mb-3">
                <div class="card-header">Solid Buttons</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary">Primary</button>
                        <button class="btn btn-secondary">Secondary</button>
                        <button class="btn btn-success">Success</button>
                        <button class="btn btn-danger">Danger</button>
                        <button class="btn btn-warning">Warning</button>
                        <button class="btn btn-info">Info</button>
                        <button class="btn btn-light">Light</button>
                        <button class="btn btn-dark">Dark</button>
                        <button class="btn btn-link">Link</button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Outline Buttons</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-primary">Primary</button>
                        <button class="btn btn-outline-secondary">Secondary</button>
                        <button class="btn btn-outline-success">Success</button>
                        <button class="btn btn-outline-danger">Danger</button>
                        <button class="btn btn-outline-warning">Warning</button>
                        <button class="btn btn-outline-info">Info</button>
                        <button class="btn btn-outline-light">Light</button>
                        <button class="btn btn-outline-dark">Dark</button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Sizes &amp; States</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <button class="btn btn-primary btn-lg">Large</button>
                        <button class="btn btn-primary">Default</button>
                        <button class="btn btn-primary btn-sm">Small</button>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" disabled>Disabled</button>
                        <button class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm me-1"></span> Loading…
                        </button>
                        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> With Icon</button>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Button Groups</div>
                <div class="card-body">
                    <div class="btn-group mb-2" role="group">
                        <button class="btn btn-primary">Left</button>
                        <button class="btn btn-primary">Middle</button>
                        <button class="btn btn-primary">Right</button>
                    </div>
                    <br>
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary active">Day</button>
                        <button class="btn btn-outline-primary">Week</button>
                        <button class="btn btn-outline-primary">Month</button>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- CARDS                                                              -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="cards" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-card-heading me-2"></i>Cards</h5>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Simple Card</h5>
                            <p class="card-text text-muted">A basic card with just a body.</p>
                            <a href="#" class="btn btn-primary btn-sm">Action</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Card Header</div>
                        <div class="card-body">
                            <h5 class="card-title">With Header</h5>
                            <p class="card-text text-muted">Header and footer variants.</p>
                        </div>
                        <div class="card-footer text-muted small">Last updated 3 mins ago</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-primary">
                            <h5 class="card-title">Border Color</h5>
                            <p class="card-text">Using border-primary utility.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card text-bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">text-bg-primary</h5>
                            <p class="card-text">Solid background card.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-bg-dark">
                        <div class="card-body">
                            <h5 class="card-title">text-bg-dark</h5>
                            <p class="card-text">Dark background card.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- FORMS                                                              -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="forms" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-input-cursor-text me-2"></i>Forms</h5>

            <div class="card mb-3">
                <div class="card-header">Input Types</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Text Input</label>
                            <input type="text" class="form-control" placeholder="Enter text">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Input</label>
                            <input type="email" class="form-control" placeholder="name@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" placeholder="Password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Number</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" value="#6610f2">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Textarea</label>
                            <textarea class="form-control" rows="3" placeholder="Write something…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">File Input</label>
                            <input type="file" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Range</label>
                            <input type="range" class="form-range mt-2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Select &amp; Multi-select</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Select</label>
                            <select class="form-select">
                                <option selected>Choose an option…</option>
                                <option value="1">Option One</option>
                                <option value="2">Option Two</option>
                                <option value="3">Option Three</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Multi-select</label>
                            <select class="form-select" multiple size="3">
                                <option>Alpha</option>
                                <option>Beta</option>
                                <option>Gamma</option>
                                <option>Delta</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Checks, Radios &amp; Switches</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chk1" checked>
                                <label class="form-check-label" for="chk1">Checked</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chk2">
                                <label class="form-check-label" for="chk2">Unchecked</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chk3" disabled>
                                <label class="form-check-label" for="chk3">Disabled</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="radioDemo" id="rad1" checked>
                                <label class="form-check-label" for="rad1">Selected</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="radioDemo" id="rad2">
                                <label class="form-check-label" for="rad2">Not selected</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sw1" checked>
                                <label class="form-check-label" for="sw1">On</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sw2">
                                <label class="form-check-label" for="sw2">Off</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Validation States</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Valid input</label>
                            <input type="text" class="form-control is-valid" value="Looks good!">
                            <div class="valid-feedback">Great, that's valid.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Invalid input</label>
                            <input type="text" class="form-control is-invalid" value="">
                            <div class="invalid-feedback">This field is required.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Input Groups</div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text">@</span>
                        <input type="text" class="form-control" placeholder="Username">
                    </div>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Search…">
                        <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" placeholder="0.00">
                        <span class="input-group-text">.00</span>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Floating Labels</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="floatName" placeholder="Name">
                                <label for="floatName">Full Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="floatSelect">
                                    <option selected>Choose…</option>
                                    <option value="1">One</option>
                                    <option value="2">Two</option>
                                </select>
                                <label for="floatSelect">Category</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- TABLES                                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="tables" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-table me-2"></i>Tables</h5>

            <div class="card mb-3">
                <div class="card-header">Standard Table</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Role</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Alice Johnson</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>Admin</td>
                                    <td class="text-end">
                                        <a href="#" class="text-primary text-decoration-none me-2"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger text-decoration-none"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Bob Smith</td>
                                    <td><span class="badge bg-warning text-dark">Pending</span></td>
                                    <td>Editor</td>
                                    <td class="text-end">
                                        <a href="#" class="text-primary text-decoration-none me-2"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger text-decoration-none"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Carol Davis</td>
                                    <td><span class="badge bg-secondary">Inactive</span></td>
                                    <td>Viewer</td>
                                    <td class="text-end">
                                        <a href="#" class="text-primary text-decoration-none me-2"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger text-decoration-none"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Striped &amp; Bordered</div>
                <div class="card-body p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead class="table-dark">
                            <tr><th>#</th><th>Item</th><th>Qty</th><th>Price</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>1</td><td>Widget A</td><td>10</td><td>$12.00</td></tr>
                            <tr><td>2</td><td>Widget B</td><td>5</td><td>$24.50</td></tr>
                            <tr><td>3</td><td>Widget C</td><td>20</td><td>$8.75</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- MODALS                                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="modals" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-window-stack me-2"></i>Modals</h5>
            <div class="card">
                <div class="card-body">
                    <p class="text-muted small">Modals use the <code>modalView()</code> AJAX pattern. See the <a href="index.php?page=examples">Examples</a> page for live demos and code.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-sm" onclick="modalView('modal=example_form')"><i class="bi bi-envelope me-1"></i> Form Modal</button>
                        <button class="btn btn-outline-primary btn-sm" onclick="modalView('modal=item_detail&item_id=1')"><i class="bi bi-eye me-1"></i> Detail Modal</button>
                        <button class="btn btn-outline-danger btn-sm" onclick="modalView('modal=confirm_delete&item_id=1')"><i class="bi bi-trash me-1"></i> Confirm Delete</button>
                    </div>

                    <hr>

                    <!-- Standard Bootstrap modal (not AJAX) -->
                    <h6>Standard Bootstrap Modal</h6>
                    <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#demoModal">
                        <i class="bi bi-window me-1"></i> Open Standard Modal
                    </button>
                </div>
            </div>
        </section>

        <!-- Standard demo modal -->
        <div class="modal fade" id="demoModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Standard Modal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>This is a standard Bootstrap modal — no AJAX, just <code>data-bs-toggle</code>.</p>
                        <p>Use this pattern for simple confirmations or static content.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- TABS                                                               -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="tabs" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-segmented-nav me-2"></i>Tabs</h5>

            <div class="card mb-3">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabHome">Home</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProfile">Profile</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabContact">Contact</button></li>
                        <li class="nav-item"><button class="nav-link" disabled>Disabled</button></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabHome">
                            <h6>Home Tab</h6>
                            <p class="text-muted">Card-header tabs using <code>nav-tabs</code> + <code>card-header-tabs</code>.</p>
                        </div>
                        <div class="tab-pane fade" id="tabProfile">
                            <h6>Profile Tab</h6>
                            <p class="text-muted">Second tab content here.</p>
                        </div>
                        <div class="tab-pane fade" id="tabContact">
                            <h6>Contact Tab</h6>
                            <p class="text-muted">Third tab content here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Pills Variant</div>
                <div class="card-body">
                    <ul class="nav nav-pills mb-3">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pillOne">Tab One</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pillTwo">Tab Two</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pillThree">Tab Three</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pillOne"><p class="text-muted">Pills are a rounder tab variant using <code>nav-pills</code>.</p></div>
                        <div class="tab-pane fade" id="pillTwo"><p class="text-muted">Second pill panel.</p></div>
                        <div class="tab-pane fade" id="pillThree"><p class="text-muted">Third pill panel.</p></div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- ALERTS                                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="alerts" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Alerts</h5>
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-primary" role="alert"><i class="bi bi-info-circle me-1"></i> A <strong>primary</strong> alert.</div>
                    <div class="alert alert-success" role="alert"><i class="bi bi-check-circle me-1"></i> A <strong>success</strong> alert.</div>
                    <div class="alert alert-danger" role="alert"><i class="bi bi-x-circle me-1"></i> A <strong>danger</strong> alert.</div>
                    <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle me-1"></i> A <strong>warning</strong> alert.</div>
                    <div class="alert alert-info" role="alert"><i class="bi bi-lightbulb me-1"></i> An <strong>info</strong> alert.</div>
                    <div class="alert alert-secondary" role="alert">A <strong>secondary</strong> alert.</div>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> Dismissible alert with close button.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- BADGES                                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="badges" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-tag me-2"></i>Badges</h5>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary">Primary</span>
                        <span class="badge bg-secondary">Secondary</span>
                        <span class="badge bg-success">Success</span>
                        <span class="badge bg-danger">Danger</span>
                        <span class="badge bg-warning text-dark">Warning</span>
                        <span class="badge bg-info">Info</span>
                        <span class="badge bg-light text-dark">Light</span>
                        <span class="badge bg-dark">Dark</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill bg-primary">Pill Primary</span>
                        <span class="badge rounded-pill bg-success">Pill Success</span>
                        <span class="badge rounded-pill bg-danger">Pill Danger</span>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm">Notifications <span class="badge bg-light text-dark">4</span></button>
                        <button class="btn btn-outline-secondary btn-sm">Messages <span class="badge bg-danger">99+</span></button>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- BREADCRUMBS                                                        -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="breadcrumbs" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-signpost me-2"></i>Breadcrumbs</h5>
            <div class="card">
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Library</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Data</li>
                        </ol>
                    </nav>
                    <nav aria-label="breadcrumb" style="--bs-breadcrumb-divider: '>';">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Settings</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- DROPDOWNS                                                          -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="dropdowns" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-caret-down me-2"></i>Dropdowns</h5>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">Dropdown</button>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Header</h6></li>
                                <li><a class="dropdown-item" href="#">Action</a></li>
                                <li><a class="dropdown-item" href="#">Another action</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#">Delete</a></li>
                            </ul>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-success">Split</button>
                            <button class="btn btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Option A</a></li>
                                <li><a class="dropdown-item" href="#">Option B</a></li>
                            </ul>
                        </div>
                        <div class="dropup">
                            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Dropup</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Up item 1</a></li>
                                <li><a class="dropdown-item" href="#">Up item 2</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- LIST GROUPS                                                        -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="listgroups" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-list-ul me-2"></i>List Groups</h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Basic &amp; Active</div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item active">Active item</li>
                            <li class="list-group-item">Second item</li>
                            <li class="list-group-item">Third item</li>
                            <li class="list-group-item disabled">Disabled item</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">With Badges</div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Inbox <span class="badge bg-primary rounded-pill">14</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Drafts <span class="badge bg-secondary rounded-pill">2</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Spam <span class="badge bg-danger rounded-pill">99</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- PAGINATION                                                         -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="pagination" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-three-dots me-2"></i>Pagination</h5>
            <div class="card">
                <div class="card-body">
                    <nav>
                        <ul class="pagination mb-3">
                            <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">&raquo;</a></li>
                        </ul>
                    </nav>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">4</a></li>
                            <li class="page-item"><a class="page-link" href="#">5</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- PROGRESS                                                           -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="progress" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-bar-chart me-2"></i>Progress Bars</h5>
            <div class="card">
                <div class="card-body">
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar" style="width: 25%">25%</div>
                    </div>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: 50%">50%</div>
                    </div>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-warning" style="width: 75%">75%</div>
                    </div>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width: 100%">100% animated</div>
                    </div>
                    <h6 class="mt-3">Stacked</h6>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: 15%">15%</div>
                        <div class="progress-bar bg-success" style="width: 30%">30%</div>
                        <div class="progress-bar bg-info" style="width: 20%">20%</div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- SPINNERS                                                           -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="spinners" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-arrow-repeat me-2"></i>Spinners</h5>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                        <div class="spinner-border text-success" role="status"></div>
                        <div class="spinner-border text-danger" role="status"></div>
                        <div class="spinner-border text-warning" role="status"></div>
                        <div class="spinner-border text-info" role="status"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                        <div class="spinner-grow text-primary" role="status"></div>
                        <div class="spinner-grow text-success" role="status"></div>
                        <div class="spinner-grow text-danger" role="status"></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <div class="spinner-grow spinner-grow-sm text-primary"></div>
                        <span class="text-muted small">Small variants</span>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- TOASTS                                                             -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="toasts" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-chat-square me-2"></i>Toasts</h5>
            <div class="card mb-3">
                <div class="card-header">Live Toast via <code>showToast()</code></div>
                <div class="card-body">
                    <p class="text-muted small">Session flash messages auto-fire as toasts on page load. You can also trigger them from JS:</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button class="btn btn-success btn-sm" onclick="showToast('success', 'Item saved successfully!')">
                            <i class="bi bi-check-circle me-1"></i> Success Toast
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="showToast('danger', 'Something went wrong.')">
                            <i class="bi bi-x-circle me-1"></i> Error Toast
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="showToast('warning', 'Please check your input.')">
                            <i class="bi bi-exclamation-triangle me-1"></i> Warning Toast
                        </button>
                        <button class="btn btn-info btn-sm" onclick="showToast('info', 'New updates available.')">
                            <i class="bi bi-info-circle me-1"></i> Info Toast
                        </button>
                    </div>
                    <pre class="bg-dark text-light p-3 rounded small mb-0"><code>// From JS / AJAX callbacks:
showToast('success', 'Item saved!');
showToast('danger', 'Something went wrong.');

// Session flash (auto-fires on page load):
$_SESSION['success'] = 'Done!';  // → green toast</code></pre>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Static Toast Preview</div>
                <div class="card-body">
                    <div class="toast show" role="alert" style="position: relative;">
                        <div class="toast-header">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong class="me-auto">Success</strong>
                            <small class="text-muted">just now</small>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">This is a static toast preview rendered inline.</div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- ACCORDIONS                                                         -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="accordions" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-chevron-bar-expand me-2"></i>Accordions</h5>

            <div class="card mb-3">
                <div class="card-header">Standard Accordion</div>
                <div class="card-body p-0">
                    <div class="accordion" id="accordionDemo">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseOne">Section One</button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionDemo">
                                <div class="accordion-body">First section content. Accordions collapse other items when one is opened.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTwo">Section Two</button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionDemo">
                                <div class="accordion-body">Second section content goes here.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree">Section Three</button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionDemo">
                                <div class="accordion-body">Third section content goes here.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Flush Accordion (no outer border)</div>
                <div class="card-body p-0">
                    <div class="accordion accordion-flush" id="accordionFlush">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#flushOne">Flush Item One</button>
                            </h2>
                            <div id="flushOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                <div class="accordion-body">Flush variant removes outer borders and rounded corners.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#flushTwo">Flush Item Two</button>
                            </h2>
                            <div id="flushTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                <div class="accordion-body">Works well inside cards with <code>p-0</code> on card-body.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- OFFCANVAS                                                          -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="offcanvas" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-layout-sidebar me-2"></i>Offcanvas</h5>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#offcanvasStart">
                            <i class="bi bi-arrow-bar-right me-1"></i> Start (Left)
                        </button>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEnd">
                            <i class="bi bi-arrow-bar-left me-1"></i> End (Right)
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTop">
                            <i class="bi bi-arrow-bar-down me-1"></i> Top
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="offcanvas offcanvas-start" id="offcanvasStart" tabindex="-1">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Left Panel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <p>Offcanvas from the left. Good for filters or secondary navigation.</p>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action active" href="#">Dashboard</a>
                    <a class="list-group-item list-group-item-action" href="#">Orders</a>
                    <a class="list-group-item list-group-item-action" href="#">Products</a>
                    <a class="list-group-item list-group-item-action" href="#">Customers</a>
                </div>
            </div>
        </div>
        <div class="offcanvas offcanvas-end" id="offcanvasEnd" tabindex="-1">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Right Panel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <p>Offcanvas from the right. Good for detail panels or settings.</p>
            </div>
        </div>
        <div class="offcanvas offcanvas-top" id="offcanvasTop" tabindex="-1" style="height: 200px;">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Top Panel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <p>Offcanvas from the top. Good for search bars or announcements.</p>
            </div>
        </div>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- TOOLTIPS & POPOVERS                                                -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="tooltips" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-chat-dots me-2"></i>Tooltips &amp; Popovers</h5>
            <div class="card">
                <div class="card-body">
                    <h6>Tooltips</h6>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" data-bs-placement="top" title="Tooltip on top">Top</button>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" data-bs-placement="right" title="Tooltip on right">Right</button>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Tooltip on bottom">Bottom</button>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Tooltip on left">Left</button>
                    </div>
                    <h6>Popovers</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="popover" data-bs-title="Popover Title" data-bs-content="Some content inside the popover." data-bs-placement="top">Popover Top</button>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="popover" data-bs-title="Click Again" data-bs-content="Click the button again to dismiss." data-bs-placement="right">Popover Right</button>
                    </div>
                </div>
            </div>
        </section>


        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- NAV VARIANTS                                                       -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <section id="navvariants" class="mb-5">
            <h5 class="border-bottom pb-2 mb-3"><i class="bi bi-signpost-2 me-2"></i>Nav Variants</h5>

            <div class="card mb-3">
                <div class="card-header">Horizontal Nav</div>
                <div class="card-body">
                    <ul class="nav">
                        <li class="nav-item"><a class="nav-link active" href="#">Active</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Link</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Link</a></li>
                        <li class="nav-item"><a class="nav-link disabled" href="#">Disabled</a></li>
                    </ul>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Justified Tabs</div>
                <div class="card-body">
                    <ul class="nav nav-tabs nav-justified">
                        <li class="nav-item"><a class="nav-link active" href="#">Active</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Longer Link</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Link</a></li>
                    </ul>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Vertical Pills</div>
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="nav flex-column nav-pills me-3" role="tablist">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#vpill1">Home</button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#vpill2">Profile</button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#vpill3">Messages</button>
                        </div>
                        <div class="tab-content flex-grow-1">
                            <div class="tab-pane fade show active" id="vpill1">Vertical pills with tab content. Good for settings pages.</div>
                            <div class="tab-pane fade" id="vpill2">Profile tab content.</div>
                            <div class="tab-pane fade" id="vpill3">Messages tab content.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- Back to top -->
        <div class="text-center mb-4">
            <a href="#typography" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-up me-1"></i> Back to Top</a>
        </div>

    </div><!-- /page-nav-content -->
</div><!-- /pageNavWrapper -->

<script>
// Initialize tooltips and popovers
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
    // Popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
        new bootstrap.Popover(el);
    });
    // Toast triggers now use showToast() from toast.js — no manual wiring needed
});
</script>
