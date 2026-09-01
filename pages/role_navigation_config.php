<?php
/** Legacy route retained as an effective role navigation preview. */
?>
<div class="container-fluid py-4"
     data-system-admin-page
     data-resource="role-navigation"
     data-mode="readonly"
     data-title="Role Navigation">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Role Navigation</h2>
            <p class="text-muted mb-0">Effective menus generated from <code>config/role_sidebars.php</code> — exactly what users receive at login.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-system-refresh>
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" data-system-summary></div>

    <div class="alert alert-info" data-system-state role="status">
        Loading effective role navigation...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Effective navigation</strong>
            <div class="input-group" style="max-width: 360px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" data-system-search placeholder="Search records">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead data-system-head>
                    <tr><th scope="col">Loading</th></tr>
                </thead>
                <tbody data-system-body>
                    <tr><td class="text-center py-5 text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small" data-system-count></div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_admin_console.js?v=<?= asset_version('js/pages/system/system_admin_console.js') ?>"></script>
