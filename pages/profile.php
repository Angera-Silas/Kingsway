<div class="container-fluid py-3" id="profilePage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-0" id="profileGreeting">Welcome</h2>
            <p class="text-muted mb-0">Your profile and staff information</p>
        </div>
    </div>

    <div class="row g-3">
        <!-- ── Profile Summary Card ────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="app-user-avatar app-user-avatar-xl mx-auto mb-3"
                         style="width:80px;height:80px;font-size:2rem;
                                background:var(--kw-primary);color:#fff;
                                border-radius:50%;display:flex;align-items:center;
                                justify-content:center"
                         id="profileAvatar">U</div>
                    <h5 class="card-title mb-1" id="profileName">User</h5>
                    <p class="text-muted small mb-2" id="profileEmail">—</p>
                    <span class="badge bg-success mb-3" id="profileRole">User</span>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Staff Number</span>
                            <strong id="profileEmployeeId">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Phone</span>
                            <strong id="profilePhone">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Department</span>
                            <strong id="profileDepartment">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Academic Year</span>
                            <strong id="currentAcademicYear">—</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">With Kingsway</span>
                            <strong id="profileTenure">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted">Last Login</span>
                            <strong id="profileLastLogin">—</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Employee Information ────────────────────────────────── -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Personal Information</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" class="form-control" id="firstName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" class="form-control" id="lastName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" class="form-control" id="email" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="tel" class="form-control" id="phone" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Gender</label>
                            <input type="text" class="form-control" id="gender" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Date of Birth</label>
                            <input type="text" class="form-control" id="dob" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Marital Status</label>
                            <input type="text" class="form-control" id="maritalStatus" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Address</label>
                            <input type="text" class="form-control" id="address" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Employment Details ──────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Employment Details</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Position / Job Title</label>
                            <input type="text" class="form-control" id="position" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Department</label>
                            <input type="text" class="form-control" id="department" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Contract Type</label>
                            <input type="text" class="form-control" id="contractType" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Employment Date</label>
                            <input type="text" class="form-control" id="employmentDate" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Supervisor</label>
                            <input type="text" class="form-control" id="supervisor" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Financial & Statutory ────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Financial & Statutory Information</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bank Name</label>
                            <input type="text" class="form-control" id="bankName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bank Account</label>
                            <input type="text" class="form-control" id="bankAccount" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">KRA PIN</label>
                            <input type="text" class="form-control" id="kraPin" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">NSSF No.</label>
                            <input type="text" class="form-control" id="nssfNo" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">NHIF No.</label>
                            <input type="text" class="form-control" id="nhifNo" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">TSC No.</label>
                            <input type="text" class="form-control" id="tscNo" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Roles ────────────────────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Roles</strong>
                </div>
                <div class="card-body">
                    <div id="rolesList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── KPI Summary ────────────────────────────────────────────── -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Performance Summary</strong>
                </div>
                <div class="card-body" id="kpiSummary">
                    <p class="text-muted small mb-0">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Login History ──────────────────────────────────────────── -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Login History</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="loginHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/me.js?v=<?= asset_version('js/pages/me.js') ?>"></script>
