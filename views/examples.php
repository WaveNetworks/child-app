<?php
/**
 * views/examples.php
 * UI component examples: modals via modalView() and full-page card with tabs.
 */
$page_title = 'Examples';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">UI Examples</h4>
</div>

<!-- ─── SECTION: Modals via modalView() ─────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-window-stack me-1"></i> Modal Examples
        <span class="text-muted small ms-2">Using <code>modalView()</code> + <code>modal_controller.php</code></span>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Modals are loaded dynamically from <code>modal_controller.php</code> via AJAX.
            Each modal maps to a snippet file in <code>snippets/</code>.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" onclick="modalView('modal=example_form')">
                <i class="bi bi-envelope me-1"></i> Form Modal
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="modalView('modal=item_detail&item_id=1')">
                <i class="bi bi-eye me-1"></i> Item Detail Modal
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="modalView('modal=confirm_delete&item_id=1')">
                <i class="bi bi-trash me-1"></i> Delete Confirmation Modal
            </button>
        </div>

        <hr>

        <h6>Usage</h6>
        <pre class="bg-dark text-light p-3 rounded small"><code>&lt;!-- Open a modal --&gt;
&lt;button onclick="modalView('modal=example_form')"&gt;Open&lt;/button&gt;

&lt;!-- Pass parameters --&gt;
&lt;button onclick="modalView('modal=item_detail&amp;item_id=5')"&gt;View Item&lt;/button&gt;

&lt;!-- Submit form inside modal (stays open, shows validation) --&gt;
&lt;button onclick="reloadModalThisForm('formId', 'modal=example_form&amp;action=sendFeedback')"&gt;Submit&lt;/button&gt;

&lt;!-- In modal_controller.php, map the modal name to a snippet: --&gt;
$modals = [
    'example_form' =&gt; __DIR__ . '/../snippets/modal_example_form.php',
];

&lt;!-- Snippet includes feedback.php for session alerts + is-invalid for field errors --&gt;</code></pre>
    </div>
</div>

<!-- ─── SECTION: DataTable ─────────────────────────────────────────────── -->
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.4/css/responsive.bootstrap5.min.css">
<div class="card mb-4">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-table me-1"></i> DataTable Example
        <span class="text-muted small ms-2">Sortable, searchable, paginated</span>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Powered by <a href="https://datatables.net/" target="_blank" rel="noopener">DataTables</a>
            with Bootstrap 5 styling. Includes search, column sorting, and pagination out of the box.
        </p>
            <table id="exampleDataTable" class="table table-striped table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Office</th>
                        <th>Age</th>
                        <th>Start date</th>
                        <th>Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Tiger Nixon</td><td>System Architect</td><td>Edinburgh</td><td>61</td><td>2011-04-25</td><td>$320,800</td></tr>
                    <tr><td>Garrett Winters</td><td>Accountant</td><td>Tokyo</td><td>63</td><td>2011-07-25</td><td>$170,750</td></tr>
                    <tr><td>Ashton Cox</td><td>Junior Technical Author</td><td>San Francisco</td><td>66</td><td>2009-01-12</td><td>$86,000</td></tr>
                    <tr><td>Cedric Kelly</td><td>Senior Javascript Developer</td><td>Edinburgh</td><td>22</td><td>2012-03-29</td><td>$433,060</td></tr>
                    <tr><td>Airi Satou</td><td>Accountant</td><td>Tokyo</td><td>33</td><td>2008-11-28</td><td>$162,700</td></tr>
                    <tr><td>Brielle Williamson</td><td>Integration Specialist</td><td>New York</td><td>61</td><td>2012-12-02</td><td>$372,000</td></tr>
                    <tr><td>Herrod Chandler</td><td>Sales Assistant</td><td>San Francisco</td><td>59</td><td>2012-08-06</td><td>$137,500</td></tr>
                    <tr><td>Rhona Davidson</td><td>Integration Specialist</td><td>Tokyo</td><td>55</td><td>2010-10-14</td><td>$327,900</td></tr>
                    <tr><td>Colleen Hurst</td><td>Javascript Developer</td><td>San Francisco</td><td>39</td><td>2009-09-15</td><td>$205,500</td></tr>
                    <tr><td>Sonya Frost</td><td>Software Engineer</td><td>Edinburgh</td><td>23</td><td>2008-12-13</td><td>$103,600</td></tr>
                    <tr><td>Jena Gaines</td><td>Office Manager</td><td>London</td><td>30</td><td>2008-12-19</td><td>$90,560</td></tr>
                    <tr><td>Quinn Flynn</td><td>Support Lead</td><td>Edinburgh</td><td>22</td><td>2013-03-03</td><td>$342,000</td></tr>
                    <tr><td>Charde Marshall</td><td>Regional Director</td><td>San Francisco</td><td>36</td><td>2008-10-16</td><td>$470,600</td></tr>
                    <tr><td>Haley Kennedy</td><td>Senior Marketing Designer</td><td>London</td><td>43</td><td>2012-12-18</td><td>$313,500</td></tr>
                    <tr><td>Tatyana Fitzpatrick</td><td>Regional Director</td><td>London</td><td>19</td><td>2010-03-17</td><td>$385,750</td></tr>
                    <tr><td>Michael Silva</td><td>Marketing Designer</td><td>London</td><td>66</td><td>2012-11-27</td><td>$198,500</td></tr>
                    <tr><td>Paul Byrd</td><td>Chief Financial Officer</td><td>New York</td><td>64</td><td>2010-06-09</td><td>$725,000</td></tr>
                    <tr><td>Gloria Little</td><td>Systems Administrator</td><td>New York</td><td>59</td><td>2009-04-10</td><td>$237,500</td></tr>
                    <tr><td>Bradley Greer</td><td>Software Engineer</td><td>London</td><td>41</td><td>2012-10-13</td><td>$132,000</td></tr>
                    <tr><td>Dai Rios</td><td>Personnel Lead</td><td>Edinburgh</td><td>35</td><td>2012-09-26</td><td>$217,500</td></tr>
                    <tr><td>Jenette Caldwell</td><td>Development Lead</td><td>New York</td><td>30</td><td>2011-09-03</td><td>$345,000</td></tr>
                    <tr><td>Yuri Berry</td><td>Chief Marketing Officer</td><td>New York</td><td>40</td><td>2009-06-25</td><td>$675,000</td></tr>
                    <tr><td>Caesar Vance</td><td>Pre-Sales Support</td><td>New York</td><td>21</td><td>2011-12-12</td><td>$106,450</td></tr>
                    <tr><td>Doris Wilder</td><td>Sales Assistant</td><td>Sydney</td><td>23</td><td>2010-09-20</td><td>$85,600</td></tr>
                    <tr><td>Angelica Ramos</td><td>Chief Executive Officer</td><td>London</td><td>47</td><td>2009-10-09</td><td>$1,200,000</td></tr>
                </tbody>
            </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.4/js/responsive.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#exampleDataTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        language: {
            search: '',
            searchPlaceholder: 'Filter records...'
        }
    });
});
</script>

<!-- ─── SECTION: Full-Page Card with Tabs ────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                    <i class="bi bi-house me-1"></i> Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="false">
                    <i class="bi bi-list-ul me-1"></i> Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">
                    <i class="bi bi-gear me-1"></i> Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                    <i class="bi bi-clock-history me-1"></i> Activity
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">

            <!-- Tab: Overview -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                <h5>Project Overview</h5>
                <p class="text-muted">This is the overview tab of a full-page card with Bootstrap nav-tabs in the card header.</p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-people fs-1 text-primary"></i>
                            <div class="fs-4 fw-bold mt-2">1,234</div>
                            <div class="text-muted small">Total Users</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-check-circle fs-1 text-success"></i>
                            <div class="fs-4 fw-bold mt-2">89%</div>
                            <div class="text-muted small">Completion Rate</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-graph-up fs-1 text-info"></i>
                            <div class="fs-4 fw-bold mt-2">+12%</div>
                            <div class="text-muted small">Growth</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Details -->
            <div class="tab-pane fade" id="details" role="tabpanel" aria-labelledby="details-tab">
                <h5>Details</h5>
                <p class="text-muted">A table inside a tab pane.</p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Alice Johnson</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>Admin</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="modalView('modal=example_form')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Bob Smith</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>Member</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="modalView('modal=example_form')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Carol Davis</td>
                                <td><span class="badge bg-secondary">Inactive</span></td>
                                <td>Member</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="modalView('modal=example_form')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Settings -->
            <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                <h5>Settings</h5>
                <p class="text-muted">A form inside a tab pane.</p>

                <form>
                    <div class="mb-3">
                        <label for="projectName" class="form-label">Project Name</label>
                        <input type="text" class="form-control" id="projectName" value="My Project">
                    </div>
                    <div class="mb-3">
                        <label for="projectDesc" class="form-label">Description</label>
                        <textarea class="form-control" id="projectDesc" rows="3">A sample project for demonstrating tabbed card layouts.</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="visPublic" checked>
                            <label class="form-check-label" for="visPublic">Public</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="visPrivate">
                            <label class="form-check-label" for="visPrivate">Private</label>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enableNotifs" checked>
                        <label class="form-check-label" for="enableNotifs">Enable notifications</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i> Save Settings
                    </button>
                </form>
            </div>

            <!-- Tab: Activity -->
            <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                <h5>Activity Log</h5>
                <p class="text-muted">A timeline-style list inside a tab pane.</p>

                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex align-items-start">
                        <i class="bi bi-plus-circle text-success fs-5 me-3 mt-1"></i>
                        <div>
                            <div><strong>Alice</strong> created the project</div>
                            <small class="text-muted">2 hours ago</small>
                        </div>
                    </div>
                    <div class="list-group-item d-flex align-items-start">
                        <i class="bi bi-pencil text-primary fs-5 me-3 mt-1"></i>
                        <div>
                            <div><strong>Bob</strong> updated the description</div>
                            <small class="text-muted">1 hour ago</small>
                        </div>
                    </div>
                    <div class="list-group-item d-flex align-items-start">
                        <i class="bi bi-person-plus text-info fs-5 me-3 mt-1"></i>
                        <div>
                            <div><strong>Carol</strong> was added as a member</div>
                            <small class="text-muted">45 minutes ago</small>
                        </div>
                    </div>
                    <div class="list-group-item d-flex align-items-start">
                        <i class="bi bi-gear text-secondary fs-5 me-3 mt-1"></i>
                        <div>
                            <div><strong>Alice</strong> changed visibility to Private</div>
                            <small class="text-muted">30 minutes ago</small>
                        </div>
                    </div>
                    <div class="list-group-item d-flex align-items-start">
                        <i class="bi bi-chat-dots text-warning fs-5 me-3 mt-1"></i>
                        <div>
                            <div><strong>Bob</strong> left a comment</div>
                            <small class="text-muted">15 minutes ago</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
