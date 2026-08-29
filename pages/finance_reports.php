<?php
/**
 * Finance Reports Page
 * HTML structure only - logic will be in js/pages/finance_reports.js
 * Embedded in app_layout.php
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/reports/report-components.css">

<div class="card shadow-sm kw-report">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-graph-up"></i> Financial Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportReportBtn">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-light btn-sm" id="printReportBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <ul class="nav nav-tabs flex-nowrap overflow-auto mb-4" id="financeReportTabs" role="tablist" aria-label="Financial report views">
            <?php foreach ([
                'income_statement' => 'Income Statement', 'balance_sheet' => 'Balance Sheet',
                'cash_flow' => 'Cash Flow', 'fee_collection' => 'Fee Collection',
                'expense_summary' => 'Expense Summary', 'student_accounts' => 'Student Accounts'
            ] as $value => $label): ?>
                <li class="nav-item" role="presentation"><button class="nav-link <?= $value === 'income_statement' ? 'active' : '' ?> text-nowrap" type="button" role="tab" data-finance-report="<?= $value ?>"><?= $label ?></button></li>
            <?php endforeach; ?>
        </ul>
        <select class="d-none" id="reportType" aria-hidden="true" tabindex="-1">
                    <option value="income_statement">Income Statement</option>
                    <option value="balance_sheet">Balance Sheet</option>
                    <option value="cash_flow">Cash Flow Statement</option>
                    <option value="fee_collection">Fee Collection Report</option>
                    <option value="expense_summary">Expense Summary</option>
                    <option value="student_accounts">Student Account Status</option>
        </select>
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-lg-3">
                <select class="form-select" id="periodType">
                    <option value="term">By Term</option>
                    <option value="month">By Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-3">
                <input type="date" class="form-control" id="startDate">
            </div>
            <div class="col-md-4 col-lg-3">
                <input type="date" class="form-control" id="endDate">
            </div>
        </div>

        <div id="financeKpis" class="mb-4"></div>

        <div id="financeReportsError" class="alert alert-danger d-none" role="alert"></div>

        <!-- Report Content Area -->
        <div id="reportContent">
            <!-- Chart Area -->
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0" id="financePrimaryTitle">Income and expense movement</h5></div>
                <div class="card-body" style="min-height: 330px">
                    <canvas id="financeChart" height="80"></canvas>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-xl-7"><div class="kw-report-panel h-100"><div class="kw-report-panel__head"><div><div class="kw-report-eyebrow" id="financePivotEyebrow">Cross-tabulation</div><h5 class="mb-0" id="financePivotTitle">Monthly income and expenses</h5></div></div><div class="kw-report-panel__body" id="financePivot"></div></div></div>
                <div class="col-xl-5"><div class="kw-report-panel h-100"><div class="kw-report-panel__head"><div><div class="kw-report-eyebrow" id="financeCompositionEyebrow">Composition</div><h5 class="mb-0" id="financeCompositionTitle">Revenue source mix</h5></div></div><div class="kw-report-panel__body" id="financeComposition"></div></div></div>
            </div>

            <!-- Report Table -->
            <h5 class="mb-3" id="financeDetailTitle">Income statement entries</h5>
            <div class="table-responsive">
                <table class="table table-bordered" id="reportTable">
                    <thead class="table-light">
                        <tr id="reportTableHeader">
                            <!-- Dynamic headers -->
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr id="reportTableFooter">
                            <!-- Dynamic totals -->
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5" style="display: none;">
            <i class="bi bi-bar-chart fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No report data</h5>
            <p class="text-muted">Change a filter or report tab; the view refreshes automatically.</p>
        </div>
    </div>
</div>
<?php asset_script($appBase, 'js/reports/report_components.js'); ?>
<?php asset_script($appBase, 'js/pages/finance_reports.js'); ?>
