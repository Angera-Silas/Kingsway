<?php
/** System Administrator dashboard — System Domain infrastructure only. */
$rootId = 'systemAdministratorDashboardPage';
?>
<section
  class="container-fluid py-4 role-dashboard dashboard-surface dashboard-system-grid"
  id="systemAdministratorDashboardPage"
  data-dashboard-layout="system-operations-grid"
  aria-labelledby="systemAdministratorDashboardTitle"
  aria-busy="true"
>

  <nav class="dashboard-action-strip mb-4" aria-label="System administration quick actions">
    <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=active_sessions"><i class="bi bi-person-check"></i><span>Active sessions</span></a>
    <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=activity_audit_logs"><i class="bi bi-journal-check"></i><span>Audit activity</span></a>
    <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=api_metrics"><i class="bi bi-activity"></i><span>API metrics</span></a>
    <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=error_logs"><i class="bi bi-terminal"></i><span>Error logs</span></a>
  </nav>
  <div
    class="alert alert-info"
    id="systemAdministratorDashboardState"
    role="status"
    aria-live="polite"
  >
    Loading system metrics...
  </div>

  <div class="row g-3 mb-4" id="systemAdministratorMetricCards">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Enabled users</div>
          <div class="fs-3 fw-bold" id="metricEnabledUsers">—</div>
          <small class="text-muted" id="metricEnabledUsersNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Active sessions</div>
          <div class="fs-3 fw-bold" id="metricActiveSessions">—</div>
          <small class="text-muted" id="metricActiveSessionsNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Failed logins · 24h</div>
          <div class="fs-3 fw-bold" id="metricFailedLogins">—</div>
          <small class="text-muted" id="metricFailedLoginsNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Open incidents</div>
          <div class="fs-3 fw-bold" id="metricOpenIncidents">—</div>
          <small class="text-muted" id="metricOpenIncidentsNote"></small>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Recent authentication activity</strong>
          <small class="text-muted" id="systemAdministratorActivityCount"></small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Time</th>
                <th scope="col">Actor</th>
                <th scope="col">Action</th>
                <th scope="col">Status</th>
                <th scope="col">IP address</th>
              </tr>
            </thead>
            <tbody id="systemAdministratorActivityBody">
              <tr>
                <td colspan="5" class="text-center py-4">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="d-flex flex-column gap-3 h-100">
        <div class="row g-3 system-operations-mini">
          <div class="col-6">
            <div class="card border-0 shadow-sm h-100 bg-success text-white">
              <div class="card-body">
                <div class="small opacity-75">Pending jobs</div>
                <div class="fs-3 fw-bold" id="metricPendingJobs">—</div>
                <small class="opacity-75" id="metricPendingJobsNote"></small>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card border-0 shadow-sm h-100 bg-warning text-dark">
              <div class="card-body">
                <div class="small opacity-75">API errors · 24h</div>
                <div class="fs-3 fw-bold" id="metricApiErrors">—</div>
                <small class="opacity-75" id="metricApiErrorsNote"></small>
              </div>
            </div>
          </div>
        </div>
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white">
            <strong>Technical checks</strong>
          </div>
          <div
            class="list-group list-group-flush"
            id="systemAdministratorTechnicalChecks"
          >
            <div class="list-group-item text-muted">Loading...</div>
          </div>
        </div>

        <div class="card border-0 shadow-sm flex-grow-1">
          <div class="card-header bg-white">
            <strong>Current system attention</strong>
          </div>
          <div class="list-group list-group-flush" id="systemAdministratorAlerts">
            <div class="list-group-item text-muted">Loading...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/dashboards/system_administrator_dashboard.js?v=<?= asset_version('js/dashboards/system_administrator_dashboard.js') ?>"></script>
