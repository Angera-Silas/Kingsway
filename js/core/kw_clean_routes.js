/**
 * KingswayClean: the quiet ledger look (css/kw-clean.css) for every page in
 * the Learners, Staff and Finance menus.
 *
 * The page content area gets class="kw-clean" on these routes and loses it
 * on any other, so older pages pick up the clean layout without markup
 * changes. Student fee accounts has its own layout and is not listed.
 * Generated from config/role_sidebars.php groups: Students, My Students,
 * Staff, Teacher Management, My HR, Finance, Payroll, Accounts, Payments &
 * Vendors, Fee Structure, Student Billing, Expenditure, Accounts & Balances,
 * Budget & Planning, Controls & Audit, Finance Review, Payslips & P9.
 */
(function (window, document) {
  'use strict';

  const ROUTES = new Set([
    // Academic setup and calendar pages
    "manage_classes", "class_streams", "manage_holidays", "school_events",
    "academic_students", "activity_achievements", "activity_participants", "adjustments", "all_students", "all_teachers",
    "alumni_management", "assign_class_teachers", "assign_subjects_to_teachers", "audit_logs", "bank_accounts", "bank_transactions",
    "budget_actuals", "budget_overview", "catalog_orders_management", "catering_boarding_students", "class_records", "detailed_payslip",
    "discipline_cases", "discipline_students", "draft_fee_structure", "exception_reports", "finance_reports", "kcb_disbursement_reconciliation",
    "manage_admissions_payments", "manage_boarding_fee_structure", "manage_expenses", "manage_extra_charges", "manage_family_groups", "manage_fee_structure",
    "manage_job_applications", "manage_payments", "manage_payrolls", "manage_staff", "manage_students", "manage_transport_fee_structure",
    "manage_uniform_sales", "mark_attendance", "mpesa_reconciliation", "mpesa_settlements", "my_attendance", "parent_refunds",
    "payment_reconciliation", "payment_records", "payroll", "payslips", "petty_cash", "purchase_orders",
    "special_needs", "staff_appointments", "staff_attendance", "staff_id_cards", "staff_leave", "staff_performance",
    "staff_role_assignments", "statutory_remittances", "student_counseling", "student_fund_transfers", "student_health", "student_id_cards", "photo_capture",
    "student_leadership", "student_performance", "student_portfolio", "student_profiles", "student_promotion", "student_subject_performance",
    "student_welfare", "students_by_class", "students_overview", "students_with_balance", "subject_students_list", "supplier_payments",
    "teacher_performance_reviews", "teacher_workload", "transaction_approvals", "transport_fees", "transport_passengers", "uniform_discounts",
    "uniform_unit_tracking", "unmatched_payments", "vendors", "view_class_lists", "view_student_info", "welfare_records",
  ]);

  function route() {
    try {
      return window.REQUESTED_ROUTE || new URLSearchParams(window.location.search).get('route') || '';
    } catch (_) {
      return '';
    }
  }

  /**
   * Filters load on change (Hard Rule 6). Older pages still have an
   * "Apply" button (#applyFiltersBtn): hide it and press it whenever a
   * filter in its row changes, so the page's own loading logic runs
   * exactly as before, without the extra click.
   */
  function autoApplyFilters(host) {
    const button = host.querySelector('#applyFiltersBtn');
    if (!button || button.dataset.kwAutoApply) return;
    button.dataset.kwAutoApply = '1';
    const scope = button.closest('.row') || button.parentElement;
    const col = button.closest('[class*="col"]');
    if (col && col.parentElement === scope) col.classList.add('d-none'); else button.classList.add('d-none');
    let timer = null;
    const run = (delay) => { clearTimeout(timer); timer = setTimeout(() => button.click(), delay); };
    scope.querySelectorAll('select').forEach((el) => el.addEventListener('change', () => run(0)));
    scope.querySelectorAll('input:not([type=checkbox]):not([type=radio])').forEach((el) => el.addEventListener('input', () => run(400)));
  }

  function apply() {
    const host = document.getElementById('main-content-segment');
    if (!host) return;
    const on = ROUTES.has(route());
    host.classList.toggle('kw-clean', on);
    if (on) autoApplyFilters(host);
  }

  window.addEventListener('kingsway:page-loaded', apply);
  document.addEventListener('DOMContentLoaded', apply);
  apply();

  window.KingswayClean = Object.freeze({ routes: ROUTES, apply });
})(window, document);
