<?php
/**
 * Alumni Management Page
 * Purpose: View, manage, and communicate with graduated students (alumni)
 * Features:
 * - List all alumni with graduation details
 * - Filter by graduation year, class, or search by name/admission
 * - Edit contact info, career interest, notes
 * - Deactivate alumni records
 * - Export data
 */
?>
<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-award me-2"></i>Alumni Management</h4>
                    <p class="text-muted mb-0">View and manage graduated student records</p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" id="exportAlumni">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalAlumni">--</h2>
                    <p class="mb-0">Total Alumni</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="recentAlumni">--</h2>
                    <p class="mb-0">This Year</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="activeAlumni">--</h2>
                    <p class="mb-0">Contactable</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchAlumni" placeholder="Search name or admission no...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterYear">
                        <option value="">All Graduation Years</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filterActive">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="alumniTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Graduated Year</th>
                            <th>Class</th>
                            <th>Stream</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="9" class="text-center">Loading alumni...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/alumni_management.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/alumni_management.js") ?>"></script>
