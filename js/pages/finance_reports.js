/**
 * finance_reports.js
 * Live finance reports (payments, balances, expenses) with role-aware API access.
 */

const financeReportsController = (() => {
  const state = {
    chart: null,
    reportType: "income_statement",
    rows: [],
    footer: [],
  };

  function toNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
  }

  function esc(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatCurrency(value) {
    return `KES ${toNumber(value).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  function formatDate(value) {
    if (!value) return "—";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return esc(value);
    return d.toLocaleDateString();
  }

  function formatStatus(status) {
    const s = String(status || "unknown").toLowerCase();
    const map = {
      confirmed: "success",
      successful: "success",
      pending: "warning",
      partial: "warning",
      paid: "success",
      arrears: "danger",
      waived: "info",
      failed: "danger",
      reversed: "secondary",
    };
    const cls = map[s] || "secondary";
    return `<span class="badge bg-${cls}">${esc(s)}</span>`;
  }

  function getEl(id) {
    return document.getElementById(id);
  }

  function setText(id, value) {
    const el = getEl(id);
    if (el) el.textContent = value;
  }

  function showError(message) {
    const el = getEl("financeReportsError");
    if (!el) return;
    if (!message) {
      el.classList.add("d-none");
      el.textContent = "";
      return;
    }
    el.classList.remove("d-none");
    el.textContent = message;
  }

  async function safeCall(fn) {
    try {
      return { ok: true, data: await fn() };
    } catch (error) {
      return { ok: false, error };
    }
  }

  async function fetchStats(params = {}) {
    return window.API.apiCall("/payments/stats", "GET", null, params, { checkPermission: false });
  }

  async function fetchTrends(params = {}) {
    return window.API.apiCall("/payments/collection-trends", "GET", null, params, { checkPermission: false });
  }

  async function fetchRevenueSources(params = {}) {
    return window.API.apiCall("/payments/revenue-sources", "GET", null, params, { checkPermission: false });
  }

  async function fetchPaymentStatus(params = {}) {
    return window.API.finance.getStudentPaymentStatusList(params);
  }

  async function fetchPayments(params = {}) {
    return window.API.apiCall("/finance", "GET", null, { type: "payments", ...params }, { checkPermission: false });
  }

  async function fetchExpenses(params = {}) {
    return window.API.apiCall("/finance", "GET", null, { type: "expenses", ...params }, { checkPermission: false });
  }

  function getFilters() {
    return {
      reportType: getEl("reportType")?.value || "income_statement",
      periodType: getEl("periodType")?.value || "term",
      startDate: getEl("startDate")?.value || "",
      endDate: getEl("endDate")?.value || "",
    };
  }

  function setLoadingTable() {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (head) head.innerHTML = "";
    if (foot) foot.innerHTML = "";
    if (body) {
      body.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>
            Loading report data...
          </td>
        </tr>
      `;
    }
  }

  function setEmptyTable(message) {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (head) head.innerHTML = "";
    if (foot) foot.innerHTML = "";
    if (body) {
      body.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">${esc(message)}</td>
        </tr>
      `;
    }
  }

  function renderTable(columns, rows, footerCells = []) {
    const head = getEl("reportTableHeader");
    const body = getEl("reportTableBody");
    const foot = getEl("reportTableFooter");
    if (!head || !body || !foot) return;

    head.innerHTML = columns.map((c) => `<th>${esc(c)}</th>`).join("");
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="${columns.length}" class="text-center py-4 text-muted">No records found for this report.</td></tr>`;
      foot.innerHTML = "";
      state.rows = [];
      state.footer = [];
      return;
    }

    body.innerHTML = rows
      .map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join("")}</tr>`)
      .join("");

    if (footerCells.length) {
      foot.innerHTML = footerCells.map((cell) => `<th>${cell}</th>`).join("");
    } else {
      foot.innerHTML = "";
    }

    state.rows = rows.map((r) => r.map((c) => String(c).replace(/<[^>]+>/g, "").trim()));
    state.footer = footerCells.map((c) => String(c).replace(/<[^>]+>/g, "").trim());
  }

  const monthOf = (value) => String(value || "").slice(0, 7) || "Unknown";
  const group = (rows, keyFn, valueFn) => rows.reduce((out, row) => {
    const key = keyFn(row) || "Unknown";
    out[key] = (out[key] || 0) + toNumber(valueFn(row));
    return out;
  }, {});
  const classSummary = (items) => Object.values(items.reduce((out, item) => {
    const name = item.class_name || item.level_name || "Unassigned";
    out[name] ||= { class_name: name, due: 0, paid: 0, balance: 0, accounts: 0 };
    out[name].due += toNumber(item.total_due);
    out[name].paid += toNumber(item.total_paid);
    out[name].balance += toNumber(item.current_balance);
    out[name].accounts += 1;
    return out;
  }, {}));

  function headings(primary, pivot, composition, detail, pivotEyebrow = "Cross-tabulation", compositionEyebrow = "Composition") {
    setText("financePrimaryTitle", primary); setText("financePivotTitle", pivot);
    setText("financeCompositionTitle", composition); setText("financeDetailTitle", detail);
    setText("financePivotEyebrow", pivotEyebrow); setText("financeCompositionEyebrow", compositionEyebrow);
  }

  function renderReportView(type, data) {
    const RC = window.ReportComponents;
    if (!RC) return;
    const { payments, expenses, students, summary, trends, sources, stats } = data;
    const income = payments.reduce((sum, row) => sum + toNumber(row.amount), 0)
      || toNumber(summary.total_paid) || toNumber(stats?.amount);
    const expenseTotal = expenses.reduce((sum, row) => sum + toNumber(row.amount), 0);
    const due = toNumber(summary.total_due), paid = toNumber(summary.total_paid) || income;
    const balance = toNumber(summary.total_balance), rate = toNumber(summary.collection_rate);
    const classes = classSummary(students);
    const expenseGroups = group(expenses, r => r.expense_category || "Uncategorized", r => r.amount);
    const transactionRows = payments.map(r => ({ month: monthOf(r.transaction_date), type: "Income", amount: toNumber(r.amount) }))
      .concat(expenses.map(r => ({ month: monthOf(r.expense_date), type: "Expense", amount: toNumber(r.amount) })));
    const chart = getEl("financeChart");

    if (type === "income_statement") {
      const margin = income ? ((income - expenseTotal) / income) * 100 : 0;
      headings("Income statement waterfall", "Income and expenses by month", "Revenue sources", "Income statement movements");
      RC.kpis("#financeKpis", [{label:"Revenue",value:income,format:"currency"},{label:"Expenses",value:expenseTotal,format:"currency",color:"#b94356"},{label:"Surplus / deficit",value:income-expenseTotal,format:"currency",color:"#3677b5"},{label:"Operating margin",value:margin,format:"percent",color:"#e0a52b"}]);
      RC.chart(chart, "waterfall", {labels:["Revenue","Expenses"], changes:[income,-expenseTotal], label:"Operating result"});
      RC.pivot("#financePivot", transactionRows, {row:"type",column:"month",value:"amount",aggregate:"sum",format:"currency",rowLabel:"Statement line"});
      RC.treemap("#financeComposition", (sources.length ? sources.map(r=>({label:r.source||"Other",value:toNumber(r.total),format:"currency"})) : [{label:"Fees collected",value:income,format:"currency"}]));
      const model = buildIncomeCashFlowReport(payments, expenses); renderTable(model.columns, model.rows, ["<strong>Operating result</strong>","","","",`<strong>${formatCurrency(income-expenseTotal)}</strong>`,""]); return;
    }
    if (type === "balance_sheet") {
      headings("Receivables position", "Class financial position", "Collected versus receivable", "Statement of fee position", "Financial position", "Composition");
      RC.kpis("#financeKpis", [{label:"Fees billed",value:due,format:"currency"},{label:"Cash collected",value:paid,format:"currency"},{label:"Receivables",value:balance,format:"currency",color:"#b94356"},{label:"Collection ratio",value:rate,format:"percent",color:"#e0a52b"}]);
      RC.chart(chart,"doughnut",{labels:["Collected","Receivable"],datasets:[{label:"Position",data:[paid,balance]}]});
      const positionRows=classes.flatMap(r=>[{class:r.class_name,line:"Collected",amount:r.paid},{class:r.class_name,line:"Receivable",amount:r.balance}]);
      RC.pivot("#financePivot",positionRows,{row:"class",column:"line",value:"amount",aggregate:"sum",format:"currency",rowLabel:"Class"});
      RC.heatmapTable("#financeComposition",[{key:"class_name",label:"Class"},{key:"balance",label:"Receivable",format:"currency"}],classes,["balance"]);
      const model=buildBalanceSheet(summary); renderTable(model.columns,model.rows); return;
    }
    if (type === "cash_flow") {
      headings("Cash movement over time", "Monthly cash-flow pivot", "Cash movement sources", "Cash-flow transactions", "Trend", "Source concentration");
      RC.kpis("#financeKpis",[{label:"Cash inflow",value:income,format:"currency"},{label:"Cash outflow",value:expenseTotal,format:"currency",color:"#b94356"},{label:"Net cash movement",value:income-expenseTotal,format:"currency",color:"#3677b5"},{label:"Transactions",value:payments.length+expenses.length,format:"integer",color:"#8b5fbf"}]);
      const months=[...new Set(transactionRows.map(r=>r.month))].sort();
      RC.chart(chart,"area",{labels:months,datasets:[{label:"Inflow",data:months.map(m=>transactionRows.filter(r=>r.month===m&&r.type==="Income").reduce((s,r)=>s+r.amount,0))},{label:"Outflow",data:months.map(m=>transactionRows.filter(r=>r.month===m&&r.type==="Expense").reduce((s,r)=>s+r.amount,0))}]});
      RC.pivot("#financePivot",transactionRows,{row:"month",column:"type",value:"amount",aggregate:"sum",format:"currency",rowLabel:"Month"});
      RC.treemap("#financeComposition",[{label:"Fee inflows",value:income,format:"currency"},...Object.entries(expenseGroups).map(([label,value])=>({label:`${label} outflow`,value,format:"currency"}))]);
      const model=buildIncomeCashFlowReport(payments,expenses); renderTable(model.columns,model.rows,["<strong>Net movement</strong>","","","",`<strong>${formatCurrency(income-expenseTotal)}</strong>`,""]); return;
    }
    if (type === "fee_collection") {
      headings("Collection performance by class", "Class fee collection pivot", "Class arrears heatmap", "Fee collection by class", "Target comparison", "Risk concentration");
      RC.kpis("#financeKpis",[{label:"Fees due",value:due,format:"currency"},{label:"Fees collected",value:paid,format:"currency"},{label:"Outstanding",value:balance,format:"currency",color:"#b94356"},{label:"Collection rate",value:rate,format:"percent",color:"#e0a52b"}]);
      RC.chart(chart,"bullet",{labels:classes.map(r=>r.class_name),current:classes.map(r=>r.paid),target:classes.map(r=>r.due)});
      const rows=classes.flatMap(r=>[{class:r.class_name,measure:"Due",amount:r.due},{class:r.class_name,measure:"Paid",amount:r.paid},{class:r.class_name,measure:"Outstanding",amount:r.balance}]);
      RC.pivot("#financePivot",rows,{row:"class",column:"measure",value:"amount",aggregate:"sum",format:"currency",rowLabel:"Class"});
      RC.heatmapTable("#financeComposition",[{key:"class_name",label:"Class"},{key:"balance",label:"Outstanding",format:"currency"}],classes,["balance"]);
      renderTable(["Class","Accounts","Due","Paid","Outstanding","Rate"],classes.map(r=>[esc(r.class_name),r.accounts,formatCurrency(r.due),formatCurrency(r.paid),formatCurrency(r.balance),`${r.due?(r.paid/r.due*100).toFixed(1):"0.0"}%`]),["<strong>Totals</strong>",students.length,`<strong>${formatCurrency(due)}</strong>`,`<strong>${formatCurrency(paid)}</strong>`,`<strong>${formatCurrency(balance)}</strong>`,`<strong>${rate.toFixed(1)}%</strong>`]); return;
    }
    if (type === "expense_summary") {
      headings("Expense categories", "Expenses by category and month", "Expense category share", "Expense register", "Distribution", "Composition");
      const approved=expenses.filter(r=>["approved","paid","confirmed"].includes(String(r.status).toLowerCase())).reduce((s,r)=>s+toNumber(r.amount),0);
      RC.kpis("#financeKpis",[{label:"Total expenses",value:expenseTotal,format:"currency",color:"#b94356"},{label:"Categories",value:Object.keys(expenseGroups).length,format:"integer"},{label:"Approved / paid",value:approved,format:"currency"},{label:"Pending",value:expenseTotal-approved,format:"currency",color:"#e0a52b"}]);
      RC.chart(chart,"pie",{labels:Object.keys(expenseGroups),datasets:[{label:"Expenses",data:Object.values(expenseGroups)}]});
      const rows=expenses.map(r=>({category:r.expense_category||"Uncategorized",month:monthOf(r.expense_date),amount:toNumber(r.amount)}));
      RC.pivot("#financePivot",rows,{row:"category",column:"month",value:"amount",aggregate:"sum",format:"currency",rowLabel:"Category"});
      RC.treemap("#financeComposition",Object.entries(expenseGroups).map(([label,value])=>({label,value,format:"currency"})));
      const model=buildExpenseReport(expenses); renderTable(model.columns,model.rows,["<strong>Total</strong>","","","",`<strong>${formatCurrency(expenseTotal)}</strong>`,""]); return;
    }
    headings("Student balance distribution", "Accounts by class and status", "Arrears concentration by class", "Individual student accounts", "Distribution", "Risk concentration");
    const clear=students.filter(r=>toNumber(r.current_balance)<=0).length;
    RC.kpis("#financeKpis",[{label:"Student accounts",value:students.length,format:"integer"},{label:"Clear accounts",value:clear,format:"integer"},{label:"Accounts in arrears",value:students.length-clear,format:"integer",color:"#b94356"},{label:"Total arrears",value:balance,format:"currency",color:"#e0a52b"}]);
    RC.chart(chart,"histogram",{values:students.map(r=>toNumber(r.current_balance)),bins:8,label:"Student accounts"});
    RC.pivot("#financePivot",students.map(r=>({class:r.class_name||"Unassigned",status:r.payment_status||"Unknown",balance:toNumber(r.current_balance)})),{row:"class",column:"status",value:"balance",aggregate:"sum",format:"currency",rowLabel:"Class"});
    RC.heatmapTable("#financeComposition",[{key:"class_name",label:"Class"},{key:"balance",label:"Arrears",format:"currency"}],classes,["balance"]);
    const model=buildStudentAccountsReport(students); renderTable(model.columns,model.rows,["<strong>Totals</strong>","","","",`<strong>${formatCurrency(due)}</strong>`,`<strong>${formatCurrency(paid)}</strong>`,`<strong>${formatCurrency(balance)}</strong>`,`<strong>${rate.toFixed(1)}%</strong>`]);
  }

  function buildStudentAccountsReport(items) {
    const columns = ["Student", "Admission No", "Class", "Year/Term", "Due", "Paid", "Balance", "Status"];
    const rows = items.map((item) => [
      esc(item.student_name || "—"),
      esc(item.admission_no || "—"),
      esc(item.class_name || item.level_name || "—"),
      `${esc(item.academic_year || "—")} / T${esc(item.term_number || "—")}`,
      formatCurrency(item.total_due),
      formatCurrency(item.total_paid),
      formatCurrency(item.current_balance),
      formatStatus(item.payment_status),
    ]);
    return { columns, rows };
  }

  function buildExpenseReport(items) {
    const columns = ["Date", "Category", "Description", "Vendor", "Amount", "Status"];
    const rows = items.map((item) => [
      formatDate(item.expense_date),
      esc(item.expense_category || "—"),
      esc(item.description || "—"),
      esc(item.vendor_name || "—"),
      formatCurrency(item.amount),
      formatStatus(item.status),
    ]);
    return { columns, rows };
  }

  function buildIncomeCashFlowReport(payments, expenses) {
    const columns = ["Date", "Reference", "Type", "Description", "Amount", "Status"];
    const paymentRows = payments.map((item) => ({
      date: item.transaction_date,
      reference: item.receipt_no || item.transaction_ref || "—",
      type: "Income",
      description: item.student_name || "Student Payment",
      amount: toNumber(item.amount),
      status: item.status || "confirmed",
    }));
    const expenseRows = expenses.map((item) => ({
      date: item.expense_date,
      reference: item.receipt_number || item.id || "—",
      type: "Expense",
      description: item.description || item.expense_category || "Expense",
      amount: -Math.abs(toNumber(item.amount)),
      status: item.status || "pending",
    }));

    const combined = paymentRows
      .concat(expenseRows)
      .sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));

    const rows = combined.map((item) => [
      formatDate(item.date),
      esc(item.reference),
      esc(item.type),
      esc(item.description),
      formatCurrency(item.amount),
      formatStatus(item.status),
    ]);

    return { columns, rows };
  }

  function buildBalanceSheet(summary) {
    const columns = ["Account", "Amount"];
    const rows = [
      ["Total Fees Due", formatCurrency(summary.total_due)],
      ["Total Fees Collected", formatCurrency(summary.total_paid)],
      ["Outstanding Balance", formatCurrency(summary.total_balance)],
      ["Collection Rate", `${toNumber(summary.collection_rate).toFixed(2)}%`],
    ];
    return { columns, rows };
  }

  async function generateReport() {
    showError(null);
    const filters = getFilters();
    state.reportType = filters.reportType;
    setLoadingTable();

    const apiDateFilters = {};
    if (filters.startDate) apiDateFilters.date_from = `${filters.startDate} 00:00:00`;
    if (filters.endDate) apiDateFilters.date_to = `${filters.endDate} 23:59:59`;
    const statusFilters = { page: 1, limit: 200 };
    if (filters.startDate && filters.endDate && filters.startDate.slice(0, 4) === filters.endDate.slice(0, 4)) {
      statusFilters.academic_year = filters.startDate.slice(0, 4);
    }

    const [statsRes, trendsRes, sourcesRes, statusRes, paymentsRes, expensesRes] = await Promise.all([
      safeCall(() => fetchStats(apiDateFilters)),
      safeCall(() => fetchTrends(apiDateFilters)),
      safeCall(() => fetchRevenueSources(apiDateFilters)),
      safeCall(() => fetchPaymentStatus(statusFilters)),
      safeCall(() => fetchPayments({ page: 1, limit: 200, ...apiDateFilters })),
      safeCall(() => fetchExpenses({ page: 1, limit: 200, ...apiDateFilters })),
    ]);

    const stats = statsRes.ok ? statsRes.data || {} : {};
    const trendsData = trendsRes.ok ? trendsRes.data || {} : {};
    const revenueData = sourcesRes.ok ? sourcesRes.data || {} : {};
    const statusData = statusRes.ok ? statusRes.data || {} : {};
    const paymentsData = paymentsRes.ok ? paymentsRes.data || {} : {};
    const expensesData = expensesRes.ok ? expensesRes.data || {} : {};

    const studentItems = Array.isArray(statusData.items) ? statusData.items : [];
    const payments = Array.isArray(paymentsData.payments) ? paymentsData.payments : [];
    const expenses = Array.isArray(expensesData.expenses) ? expensesData.expenses : [];

    const summary = statusData.summary || {};
    if (
      !statusRes.ok &&
      !paymentsRes.ok &&
      !expensesRes.ok &&
      !statsRes.ok &&
      !trendsRes.ok
    ) {
      showError("Failed to load finance report data. Please refresh and try again.");
      setEmptyTable("Unable to load report data.");
      return;
    }

    renderReportView(filters.reportType, {
      payments, expenses, students: studentItems, summary,
      stats, trends: Array.isArray(trendsData.chart_data) ? trendsData.chart_data : [],
      sources: Array.isArray(revenueData.sources) ? revenueData.sources : [],
    });
  }

  function getPrintableModel() {
    const labels = Array.from(
      getEl("reportTableHeader")?.children || [],
    ).map((th) => th.textContent.trim());

    const keys = labels.map((label, index) =>
      `${label.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "") || "column"}_${index}`,
    );

    const currencyPattern = /amount|due|paid|balance|income|expense|movement/i;
    const percentagePattern = /rate|percentage/i;

    const columns = labels.map((label, index) => ({
      key: keys[index],
      label,
      type: currencyPattern.test(label)
        ? "currency"
        : percentagePattern.test(label)
          ? "percentage"
          : "text",
      width: labels.length >= 7 ? "14%" : "",
    }));

    const rows = state.rows.map((row) =>
      Object.fromEntries(keys.map((key, index) => [key, row[index] ?? ""])),
    );

    return { columns, rows };
  }

  function exportReport() {
    if (!state.rows.length) {
      showError("No report data available to export.");
      return;
    }

    showError(null);
    const model = getPrintableModel();

    window.PrintManager.exportToCSV({
      columns: model.columns,
      rows: model.rows,
      filename: `finance_report_${state.reportType}_${new Date()
        .toISOString()
        .slice(0, 10)}`,
    });
  }

  function printReport() {
    if (!state.rows.length) {
      showError("No report data available to print.");
      return;
    }

    const reportType = getEl("reportType")?.value || "income_statement";
    const reportLabel = reportType
      .replace(/_/g, " ")
      .replace(/\b\w/g, (character) => character.toUpperCase());
    const startDate = getEl("startDate")?.value || "";
    const endDate = getEl("endDate")?.value || "";
    const model = getPrintableModel();

    const filters = {
      "Report Type": reportLabel,
      "Date From": startDate || "Not specified",
      "Date To": endDate || "Not specified",
    };

    const summary = {
      "Records Included": state.rows.length,
    };

    if (state.footer.length) {
      const footerLabel = state.footer.find((value) => value) || "Total";
      const footerValue = [...state.footer].reverse().find((value) => value) || "—";
      summary[footerLabel] = footerValue;
    }

    window.PrintManager.printTable({
      title: "Financial Report",
      subtitle: reportLabel,
      description:
        "Official financial activity, balances and transaction summary.",
      columns: model.columns,
      rows: model.rows,
      summary,
      filters,
      orientation: model.columns.length > 5 ? "landscape" : "portrait",
      paperSize: "A4",
      filename: `finance_${reportType}_${new Date().toISOString().slice(0, 10)}`,
      reportCode: `FIN-${reportType.slice(0, 4).toUpperCase()}-${new Date()
        .toISOString()
        .slice(0, 10)
        .replace(/-/g, "")}`,
      signatureSection: [
        { label: "Accountant", dateLine: true },
        { label: "Headteacher", dateLine: true },
      ],
    });
  }

  function bindEvents() {
    getEl("exportReportBtn")?.addEventListener("click", exportReport);
    getEl("printReportBtn")?.addEventListener("click", printReport);
    let refreshTimer;
    const scheduleRefresh = () => {
      window.clearTimeout(refreshTimer);
      refreshTimer = window.setTimeout(generateReport, 250);
    };
    getEl("periodType")?.addEventListener("change", async () => {
      await applyPeriodPreset();
      scheduleRefresh();
    });
    ["startDate", "endDate"].forEach((id) => getEl(id)?.addEventListener("change", scheduleRefresh));
    document.getElementById("financeReportTabs")?.addEventListener("click", (event) => {
      const tab = event.target.closest("[data-finance-report]");
      if (!tab) return;
      const reportType = getEl("reportType");
      if (reportType) reportType.value = tab.dataset.financeReport;
      document.querySelectorAll("[data-finance-report]").forEach((button) => {
        const active = button === tab;
        button.classList.toggle("active", active);
        button.setAttribute("aria-selected", active ? "true" : "false");
      });
      scheduleRefresh();
    });
  }

  function setDefaultDates() {
    const startDateEl = getEl("startDate");
    const endDateEl = getEl("endDate");
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    if (startDateEl && !startDateEl.value) {
      startDateEl.value = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, "0")}-${String(
        start.getDate()
      ).padStart(2, "0")}`;
    }
    if (endDateEl && !endDateEl.value) {
      endDateEl.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(
        today.getDate()
      ).padStart(2, "0")}`;
    }
  }

  async function applyPeriodPreset() {
    const period = getEl("periodType")?.value || "custom";
    const start = getEl("startDate");
    const end = getEl("endDate");
    if (!start || !end || period === "custom") return;
    const today = new Date();
    if (period === "month") {
      start.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-01`;
      end.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
      return;
    }
    const result = await safeCall(() => window.API.apiCall("/academic/terms-list", "GET", null, {}, { checkPermission: false }));
    const terms = result.ok && Array.isArray(result.data) ? result.data : [];
    const current = terms.find(term => term.status === "current" || term.is_current);
    if (current?.start_date && current?.end_date) {
      start.value = String(current.start_date).slice(0, 10);
      end.value = String(current.end_date).slice(0, 10);
    }
  }

  async function init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated?.()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    bindEvents();
    setDefaultDates();
    await applyPeriodPreset();
    await generateReport();
  }

  return { init, generateReport, exportReport, printReport };
})();

document.addEventListener("DOMContentLoaded", financeReportsController.init);

window.financeReportsController = financeReportsController;
