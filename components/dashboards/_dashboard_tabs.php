<?php
/**
 * Shared dashboard header: the term ribbon plus Overview / Reports /
 * Export and print tabs. Used by the School Administrator and Director
 * dashboards.
 *
 * Usage inside a dashboard template:
 *
 *   <?php require_once __DIR__ . '/_dashboard_tabs.php'; ?>
 *   <?php kw_dashboard_tabs_open('schooladmin'); ?>
 *       ...the dashboard's existing content (the Overview tab)...
 *   <?php kw_dashboard_tabs_close('schooladmin', $reports); ?>
 *
 * Every table that should be exportable carries data-export-title and
 * data-export-module (the permission module, for AuthContext.canExport /
 * canPrint). js/components/dashboard_tabs.js lists them in the Export tab.
 *
 * Report rows are links to existing pages. The script hides any the signed-in
 * user cannot open, so the list never offers a page that would be refused.
 */

if (!function_exists('kw_dashboard_tabs_open')) {
    function kw_dashboard_tabs_open(string $id): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id);
        ?>
<section class="kw-dash-head" data-kw-dash-tabs="<?= $id ?>">
    <div class="kw-term" data-kw-term hidden>
        <div class="kw-term-text">
            <span class="kw-term-name" data-kw-term-name></span>
            <span class="kw-term-week" data-kw-term-week></span>
            <span class="kw-term-year" data-kw-term-year></span>
        </div>
        <div class="kw-year" data-kw-year role="img"></div>
    </div>

    <div class="kw-dash-nav">
        <ul class="nav kw-dash-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="<?= $id ?>-tab-overview" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-overview" type="button" role="tab" aria-controls="<?= $id ?>-overview" aria-selected="true">Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="<?= $id ?>-tab-reports" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-reports" type="button" role="tab" aria-controls="<?= $id ?>-reports" aria-selected="false">Reports</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="<?= $id ?>-tab-export" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-export" type="button" role="tab" aria-controls="<?= $id ?>-export" aria-selected="false">Export and print</button>
            </li>
        </ul>
        <span class="kw-dash-updated">Updated <span id="last_refresh">—</span></span>
    </div>
</section>

<div class="tab-content kw-dash-panes">
    <div class="tab-pane fade show active" id="<?= $id ?>-overview" role="tabpanel" aria-labelledby="<?= $id ?>-tab-overview" tabindex="0">
        <?php
    }

    /**
     * @param array<int,array{route:string,label:string,about:string,icon:string}> $reports
     */
    function kw_dashboard_tabs_close(string $id, array $reports): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id);
        $base = function_exists('app_base_path') ? app_base_path() : '';
        ?>
    </div>

    <div class="tab-pane fade" id="<?= $id ?>-reports" role="tabpanel" aria-labelledby="<?= $id ?>-tab-reports" tabindex="0">
        <div class="kw-pane">
            <div class="kw-pane-intro">
                <h2 class="kw-pane-title">Reports</h2>
                <p class="kw-pane-lede">Open a report to filter it, then download or print it from there.</p>
            </div>
            <ul class="kw-report-list" data-kw-report-list>
                <?php foreach ($reports as $report): ?>
                    <li data-kw-report-route="<?= htmlspecialchars($report['route']) ?>">
                        <a class="kw-report" href="<?= htmlspecialchars($base) ?>/home.php?route=<?= rawurlencode($report['route']) ?>">
                            <i class="bi <?= htmlspecialchars($report['icon']) ?> kw-report-icon" aria-hidden="true"></i>
                            <span class="kw-report-body">
                                <span class="kw-report-name"><?= htmlspecialchars($report['label']) ?></span>
                                <span class="kw-report-about"><?= htmlspecialchars($report['about']) ?></span>
                            </span>
                            <i class="bi bi-chevron-right kw-report-go" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="kw-pane-empty" data-kw-report-empty hidden>Your account has no report pages yet. Ask the system administrator to grant report access.</p>
        </div>
    </div>

    <div class="tab-pane fade" id="<?= $id ?>-export" role="tabpanel" aria-labelledby="<?= $id ?>-tab-export" tabindex="0">
        <div class="kw-pane">
            <div class="kw-pane-intro">
                <h2 class="kw-pane-title">Export and print</h2>
                <p class="kw-pane-lede">Each table is exported as it appears on the overview, including any search you have applied.</p>
            </div>
            <ul class="kw-export-list" data-kw-export-list></ul>
            <div class="kw-export-all">
                <button type="button" class="btn btn-outline-secondary" data-kw-print-dashboard>
                    <i class="bi bi-printer me-2" aria-hidden="true"></i>Print this dashboard
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($base) ?>/js/components/dashboard_tabs.js?v=<?= function_exists('asset_version') ? asset_version('js/components/dashboard_tabs.js') : time() ?>"></script>
        <?php
    }
}
