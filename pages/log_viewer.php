<?php
/**
 * System Administrator — Central Log Viewer
 * Controller: js/pages/log_viewer.js
 *
 * logcat-style viewer over the central JSON-lines log files
 * (logs/<env>/<category>.log). Colour-coded levels, category/level/search/
 * date filters, expandable request/response detail, and live tailing.
 */
?>
<div class="container-fluid py-4" id="logViewerPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Log Viewer</h2>
            <p class="text-muted mb-0">
                Trace who did what, on which route/session, from which machine,
                and at what time. All entries are read from the central
                JSON-lines log files.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-light border small fw-normal py-2 px-3"
                  id="logViewerEnvironment"
                  title="Active log environment directory">
                <i class="bi bi-folder2-open me-1"></i>Environment: development
            </span>
            <button type="button" class="btn btn-outline-secondary"
                    id="resetLogViewerFiltersBtn">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </button>
            <button type="button" class="btn btn-outline-secondary"
                    id="liveLogViewerBtn">
                <i class="bi bi-broadcast me-1"></i> Live
            </button>
            <div class="btn-group" role="group" aria-label="Download audit report">
                <button type="button" class="btn btn-outline-success" id="exportLogCsvBtn"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
                <button type="button" class="btn btn-outline-success" id="exportLogPdfBtn"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
            </div>
            <button type="button" class="btn btn-primary"
                    id="refreshLogViewerBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="logViewerSummary"></div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div><strong>Active pages</strong><div class="small text-muted">Authenticated browsers seen during the last 90 seconds</div></div>
            <span class="badge text-bg-success" id="logViewerActiveCount">0 active</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>User</th><th>Page / route</th><th>Last seen</th><th>IP</th></tr></thead>
                <tbody id="logViewerPresenceBody"><tr><td colspan="4" class="text-center text-muted py-3">No active authenticated pages.</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Most active users</strong><div class="small text-muted">Requests, actions and failures in the selected period</div></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>User</th><th>Events</th><th>Requests</th><th>Actions</th><th>Failures</th></tr></thead><tbody id="logViewerUsersBody"></tbody></table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Most accessed routes</strong><div class="small text-muted">Completed HTTP responses only; duplicate inbound traces are excluded</div></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Route</th><th>Requests</th><th>Failures</th><th>Average</th></tr></thead><tbody id="logViewerRoutesBody"></tbody></table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Audit actions</strong><div class="small text-muted">Business and security changes recorded by action type</div></div>
                <div class="card-body" id="logViewerActions"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><strong>Journal integrity</strong><div class="small text-muted">HMAC verification detects modified structured entries</div></div>
                <div class="card-body" id="logViewerIntegrity"></div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-none" id="logViewerState" role="status" aria-live="polite">
        No logs loaded.
    </div>

    <div class="log-workbench">
      <aside class="log-files-pane">
        <div class="log-pane-title"><span><i class="bi bi-folder2-open"></i> Journal files</span><button class="btn btn-sm btn-link text-light p-0" id="refreshLogFilesBtn" title="Refresh files"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="log-files-summary" id="logFilesSummary">Loading governed journals…</div>
        <div id="logFilesList" class="log-files-list"></div>
      </aside>
      <section class="log-console-pane">
        <div class="log-console-toolbar">
          <div><strong><i class="bi bi-terminal me-2"></i>Live journal</strong><span class="log-live-dot" id="logLiveDot"></span></div>
          <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-light" id="clearTerminalBtn"><i class="bi bi-eraser"></i> Clear screen</button><button class="btn btn-sm btn-outline-light" id="scrollTerminalBtn"><i class="bi bi-arrow-down"></i> Latest</button></div>
        </div>
        <div class="log-query-bar">
          <div class="log-query-input"><i class="bi bi-search"></i><input id="logViewerSearch" type="search" maxlength="200" placeholder="Search message, route, user, request ID…" autocomplete="off"></div>
          <div class="log-query-input"><span class="query-label include">INCLUDE</span><input id="logViewerIncludeRegex" type="text" maxlength="120" placeholder="regex"></div>
          <div class="log-query-input"><span class="query-label exclude">EXCLUDE</span><input id="logViewerExcludeRegex" type="text" maxlength="120" placeholder="regex"></div>
        </div>
        <div class="log-level-strip" id="logLevelLegend">
          <button data-level="" class="active">ALL</button><button data-level="debug">DEBUG</button><button data-level="info">INFO</button><button data-level="warning">WARN</button><button data-level="error">ERROR+</button><button data-level="audit">AUDIT</button>
          <span class="ms-auto"><label><input type="checkbox" id="logViewerArchivesFilter"> archives</label></span>
        </div>
        <div class="log-advanced-filters">
          <select id="logViewerCategoryFilter"><option value="">All streams</option></select>
          <input id="logViewerUserFilter" type="number" min="1" placeholder="User ID">
          <input id="logViewerActionFilter" type="search" maxlength="80" placeholder="Action / event">
          <input id="logViewerDateFrom" type="date" title="From date"><input id="logViewerDateTo" type="date" title="To date">
          <select id="logViewerPageSize"><option value="50">50 lines</option><option value="100" selected>100 lines</option><option value="200">200 lines</option></select>
          <select id="logViewerLevelFilter" class="d-none"><option value=""></option></select>
        </div>
        <div class="log-terminal" id="logViewerTableBody"><div class="terminal-empty">Waiting for journal data…</div></div>
        <div class="log-console-footer"><span id="logViewerCount">No entries</span><div><button id="logViewerPreviousPage"><i class="bi bi-chevron-left"></i></button><span id="logViewerPageIndicator">Page 1 of 1</span><button id="logViewerNextPage"><i class="bi bi-chevron-right"></i></button></div></div>
      </section>
    </div>

</div>

<style>
#logViewerPage { --console:#0d1722; --console2:#111f2d; --line:#263647; --muted:#8fa5b8; }
.log-workbench{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:650px;border-radius:14px;overflow:hidden;box-shadow:0 18px 45px rgba(15,32,24,.16);background:var(--console);border:1px solid #223546}
.log-files-pane{background:#142230;color:#d9e5ed;border-right:1px solid var(--line);min-width:0}
.log-pane-title,.log-console-toolbar{height:54px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:#192b3b;border-bottom:1px solid var(--line)}
.log-pane-title{font-size:.82rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700}.log-pane-title i{color:#e6b71e;margin-right:8px}
.log-files-summary{padding:12px 16px;color:var(--muted);font-size:.75rem;border-bottom:1px solid var(--line)}
.log-files-list{max-height:580px;overflow:auto;padding:8px}.log-file{border-radius:8px;padding:10px;margin-bottom:4px;color:#dce7ee;cursor:pointer;border:1px solid transparent}.log-file:hover,.log-file.active{background:#1d3447;border-color:#31516a}.log-file-head{display:flex;gap:8px;align-items:center;font:600 .78rem ui-monospace,monospace}.log-file-meta{font-size:.68rem;color:var(--muted);margin:4px 0 0 22px}.log-file-actions{display:flex;gap:8px;margin:7px 0 0 22px}.log-file-actions button{border:0;background:transparent;color:#8fcdb1;font-size:.7rem;padding:0}.integrity-ok{color:#5bd39a}.integrity-bad{color:#ff6b77}
.log-console-pane{min-width:0;background:var(--console);color:#d7e2ea}.log-console-toolbar{color:#f7fafb}.log-live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#607383;margin-left:10px}.log-live-dot.active{background:#44d48a;box-shadow:0 0 0 5px rgba(68,212,138,.12)}
.log-query-bar{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;padding:10px;background:#101c28;border-bottom:1px solid var(--line)}.log-query-input{height:38px;border:1px solid #30465a;border-radius:7px;display:flex;align-items:center;padding:0 10px;background:#0b1620}.log-query-input input{border:0;outline:0;background:transparent;color:#eef5f8;width:100%;font: .78rem ui-monospace,monospace}.log-query-input i{color:#7791a6;margin-right:8px}.query-label{font-size:.57rem;font-weight:800;padding:3px 5px;border-radius:3px;margin-right:7px}.query-label.include{color:#55d991;background:#153d2b}.query-label.exclude{color:#ff8290;background:#48222a}
.log-level-strip,.log-advanced-filters{display:flex;gap:7px;align-items:center;padding:8px 12px;background:#152433;border-bottom:1px solid var(--line);overflow:auto}.log-level-strip button{border:1px solid #34495b;background:transparent;color:#91a6b8;border-radius:12px;font:bold .61rem ui-monospace,monospace;padding:4px 9px}.log-level-strip button.active{color:#07150f;background:#e6b71e;border-color:#e6b71e}.log-level-strip label{font-size:.7rem;color:#9db0bf;white-space:nowrap}.log-advanced-filters>*{background:#0d1924;border:1px solid #30465a;color:#b9c8d3;border-radius:5px;padding:5px 7px;font-size:.7rem;min-width:110px;color-scheme:dark}
.log-terminal{height:470px;overflow:auto;font:12px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:6px 0;background:linear-gradient(180deg,#0c1721,#0b141d)}.terminal-empty{color:#718798;text-align:center;padding:90px 20px}.terminal-line{display:grid;grid-template-columns:154px 58px 86px minmax(220px,1fr);gap:10px;padding:3px 12px;border-left:3px solid transparent;white-space:nowrap;cursor:pointer}.terminal-line:hover,.terminal-line.expanded{background:#172838}.terminal-time{color:#6f8799}.terminal-category{color:#77a9ca}.terminal-message{overflow:hidden;text-overflow:ellipsis;color:#d3dde4}.terminal-line.level-warning{border-color:#f5bd3d;background:rgba(245,189,61,.035)}.terminal-line.level-error,.terminal-line.level-critical{border-color:#ff5364;background:rgba(255,83,100,.045)}.terminal-line.level-audit{border-color:#49bd86}.terminal-level{font-weight:800}.level-warning .terminal-level{color:#ffc857}.level-error .terminal-level,.level-critical .terminal-level{color:#ff6675}.level-info .terminal-level{color:#65b5e8}.level-audit .terminal-level{color:#52d69a}.terminal-detail{grid-column:1/-1;color:#a9bcc9;white-space:pre-wrap;padding:10px 0 8px 164px;max-height:280px;overflow:auto}.log-console-footer{height:42px;padding:0 12px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line);color:#8ca1b2;font-size:.7rem}.log-console-footer button{background:transparent;border:1px solid #34495b;color:#cbd7df;border-radius:4px}.log-console-footer span{margin:0 8px}
@media(max-width:992px){.log-workbench{grid-template-columns:1fr}.log-files-pane{max-height:260px;border-right:0;border-bottom:1px solid var(--line)}.log-files-list{max-height:180px}.log-query-bar{grid-template-columns:1fr}.log-terminal{height:500px}.terminal-line{grid-template-columns:125px 50px 70px minmax(180px,1fr)}}
</style>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/log_viewer.js?v=<?= asset_version('js/pages/log_viewer.js') ?>"></script>
