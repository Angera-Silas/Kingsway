/**
 * Headteacher Dashboard Controller
 * 
 * Purpose: ACADEMIC OVERSIGHT & ADMINISTRATION
 * - Monitor all classes and student progress
 * - Manage timetables and schedules
 * - Handle admissions and discipline
 * - Track parent communications
 * 
 * Role: Headteacher (Role ID: 5), Deputy Head (Role ID: 6), HOD (Role ID: 63)
 * Update Frequency: 30-minute refresh
 * 
 * Data Isolation: Academic data only, department-level (no finance, staff salary, system data)
 * 
 * Summary Cards (8):
 * 1. Total Students - Enrolled in department/year level
 * 2. Attendance Today - Student presence percentage
 * 3. Class Schedules - Active classes this week
 * 4. Pending Admissions - Applications waiting review
 * 5. Discipline Cases - Open discipline issues
 * 6. Parent Communications - Messages sent this week
 * 7. Student Assessments - Recent test results summary
 * 8. Class Performance - Academic results trend
 * 
 * Charts (2):
 * 1. Weekly Class Attendance Trend
 * 2. Academic Performance by Class
 * 
 * Tables (2):
 * 1. Pending Admissions
 * 2. Open Discipline Cases
 */

const headteacherDashboardController = {
  runtimeConfig: window.headteacherDashboardConfig || {},
  period: 'today',
  state: {
    summaryCards: {},
    chartData: {},
    tableData: {},
    lastRefresh: null,
    isLoading: false,
    errorMessage: null,
  },

  charts: {},

  config: {
    refreshInterval: 300000,
  },

  init: async function () {
    console.log("🚀 Headteacher Dashboard initializing...");

    // Ensure authentication is settled before making API calls.
    // Without this guard the DOMContentLoaded handler can fire before the
    // auth-state bootstrap promise has resolved, causing apiCall to see a
    // null in-memory token and throw a premature "not authenticated" error.
    if (window.AuthContext?.ready) {
      try {
        await AuthContext.ready();
      } catch (_) {
        // ready() can throw if bootstrap fails — continue with fallback.
      }
    }

    // Check API availability
    if (
      typeof window.API === "undefined" ||
      typeof window.API.dashboard === "undefined"
    ) {
      console.error("❌ API module not available");
      this.showErrorState("API module not loaded. Please refresh the page.");
      // Still try to load with fallback data
      this.loadFallbackData();
      this.renderDashboard();
      return;
    }

    this.loadDashboardData();
    this.setupEventListeners();
    this.setupPeriodListeners();
    this.setupAutoRefresh();

    console.log("✓ Headteacher Dashboard initialized");
  },

  loadDashboardData: async function () {
    if (this.state.isLoading) return;

    this.state.isLoading = true;
    this.state.errorMessage = null;
    this.showLoading(true);
    this.hideErrorState();
    const startTime = performance.now();

    try {
      const loader =
        this.runtimeConfig.loader || window.API.dashboard.getHeadteacherFull;
      console.log("📡 Fetching dashboard data via API...");

      // Use the configured loader (defaults to Headteacher endpoint)
      const data = await loader({ period: this.period });

      console.log("📦 Received dashboard data:", data);

      if (!data) {
        throw new Error("No data received from API");
      }

      // Extract nested data object if API returns {data: {...}}
      const dashboardData = data.data || data;

      // Process cards data
      if (dashboardData.cards) {
        this.processCardsData(dashboardData.cards);
      }

      // Process charts data
      if (dashboardData.charts) {
        this.state.chartData = dashboardData.charts;
      }

      // Process tables data
      if (dashboardData.tables) {
        this.state.tableData = dashboardData.tables;
      }

      // Render dashboard
      this.renderDashboard();

      this.state.lastRefresh = new Date();
      const duration = (performance.now() - startTime).toFixed(2);
      console.log(`✓ Dashboard loaded in ${duration}ms`);
    } catch (error) {
      console.error("❌ Error loading dashboard:", error);
      this.state.errorMessage = error.message;
      this.showErrorState(error.message);

      // Load fallback data
      this.loadFallbackData();
      this.renderDashboard();
    } finally {
      this.state.isLoading = false;
      this.showLoading(false);
    }
  },

  processCardsData: function (cards) {
    console.log("[HeadteacherDashboard] processCardsData called with:", cards);
    if (!cards) {
      console.warn(
        "[HeadteacherDashboard] processCardsData: cards is null/undefined"
      );
      return;
    }

    // Card 1: Total Students - matches backend card_type: 'total_students'
    const totalStudents = cards.total_students;
    if (totalStudents) {
      this.state.summaryCards.students = {
        title: "Total Students",
        value: totalStudents.total_students || "0",
        subtitle: "Enrolled",
        secondary: "All active students",
        color: "primary",
        icon: "bi-people",
      };
    }

    // Card 2: Attendance Today - matches backend card_type: 'attendance_today'
    const attendanceToday = cards.attendance_today;
    if (attendanceToday) {
      this.state.summaryCards.attendance = {
        title: "Attendance Today",
        value: (attendanceToday.percentage || "0") + "%",
        subtitle: "Present",
        secondary: `Present: ${attendanceToday.present || 0} | Absent: ${
          attendanceToday.absent || 0
        }`,
        color: "success",
        icon: "bi-check-circle",
      };
    }

    // Card 3: Class Schedules - matches backend card_type: 'schedules'
    const schedules = cards.class_schedules;
    if (schedules) {
      this.state.summaryCards.schedules = {
        title: "Class Schedules",
        value: schedules.total_sessions || "0",
        subtitle: "This week",
        secondary: (schedules.upcoming || "0") + " upcoming",
        color: "info",
        icon: "bi-calendar3",
      };
    }

    // Card 4: Pending Admissions - matches backend card_type: 'admissions'
    const admissions = cards.pending_admissions;
    if (admissions) {
      this.state.summaryCards.admissions = {
        title: "Pending Admissions",
        value: admissions.pending_applications || "0",
        subtitle: "To review",
        secondary: (admissions.documents_verified || "0") + " verified",
        color: "warning",
        icon: "bi-inbox",
      };
    }

    // Card 5: Discipline Cases - matches backend card_type: 'discipline'
    const discipline = cards.discipline_cases;
    if (discipline) {
      this.state.summaryCards.discipline = {
        title: "Discipline Cases",
        value: discipline.open_cases || "0",
        subtitle: "Open",
        secondary:
          (discipline.resolved_this_month || "0") + " resolved this month",
        color: "danger",
        icon: "bi-exclamation-triangle",
      };
    }

    // Card 6: Parent Communications - matches backend card_type: 'communications'
    const communications = cards.parent_communications;
    if (communications) {
      this.state.summaryCards.communications = {
        title: "Communications",
        value: communications.sent_this_week || "0",
        subtitle: "Sent this week",
        secondary: (communications.drafts || "0") + " drafts",
        color: "secondary",
        icon: "bi-chat-dots",
      };
    }

    // Card 7: Student Assessments - matches backend card_type: 'assessments'
    const assessments = cards.student_assessments;
    if (assessments) {
      this.state.summaryCards.assessments = {
        title: "Assessments",
        value: assessments.total_assessments || "0",
        subtitle: "Total",
        secondary: (assessments.pending_approval || "0") + " pending approval",
        color: "success",
        icon: "bi-graph-up",
      };
    }

    // Card 8: Class Performance - matches backend card_type: 'performance'
    const performance = cards.class_performance;
    if (performance) {
      this.state.summaryCards.performance = {
        title: "Class Performance",
        value: (performance.average_performance || "0") + "%",
        subtitle: "Average",
        secondary: (performance.high_performers || "0") + " high performers",
        color: "primary",
        icon: "bi-bar-chart",
      };
    }
  },

  loadFallbackData: function () {
    console.warn(
      "⚠️ Loading FALLBACK demo data - API unavailable or returned error"
    );
    console.warn("⚠️ The values displayed below are NOT from the database!");
    this.state.usingFallbackData = true;

    // Fallback cards - SAMPLE DATA ONLY (not from database)
    this.state.summaryCards = {
      students: {
        title: "Total Students",
        value: "--",
        subtitle: "Enrolled",
        secondary: "No data",
        color: "primary",
        icon: "bi-people",
      },
      attendance: {
        title: "Attendance Today",
        value: "--%",
        subtitle: "Present",
        secondary: "No data",
        color: "success",
        icon: "bi-check-circle",
      },
      schedules: {
        title: "Class Schedules",
        value: "--",
        subtitle: "This week",
        secondary: "No data",
        color: "info",
        icon: "bi-calendar3",
      },
      admissions: {
        title: "Pending Admissions",
        value: "--",
        subtitle: "To review",
        secondary: "No data",
        color: "warning",
        icon: "bi-inbox",
      },
      discipline: {
        title: "Discipline Cases",
        value: "--",
        subtitle: "Open",
        secondary: "No data",
        color: "danger",
        icon: "bi-exclamation-triangle",
      },
      communications: {
        title: "Communications",
        value: "--",
        subtitle: "Sent",
        secondary: "No data",
        color: "secondary",
        icon: "bi-chat-dots",
      },
      assessments: {
        title: "Assessments",
        value: "--",
        subtitle: "Total",
        secondary: "No data",
        color: "success",
        icon: "bi-graph-up",
      },
      performance: {
        title: "Class Performance",
        value: "--%",
        subtitle: "Average",
        secondary: "No data",
        color: "primary",
        icon: "bi-bar-chart",
      },
    };

    // Fallback charts - empty
    this.state.chartData = {
      attendance_trend: { labels: [], data: [] },
      class_performance: { labels: [], data: [] },
    };

    // Fallback tables - empty
    this.state.tableData = {
      pending_admissions: { data: [], total: 0 },
      discipline_cases: { data: [], total: 0 },
      upcoming_events: { data: [], total: 0 },
    };
  },

  renderDashboard: function () {
    console.log("🎨 Rendering dashboard...");

    this.renderSummaryCards();
    this.renderCharts();
    this.renderTables();

    // Update last refresh time
    const refreshTime = document.getElementById("lastRefreshTime");
    if (refreshTime && this.state.lastRefresh) {
      refreshTime.textContent = this.state.lastRefresh.toLocaleTimeString();
    }

    console.log("✓ Dashboard rendered");
  },

  renderSummaryCards: function () {
    // Update individual card elements (matching the PHP stat-card structure)
    const cards = this.state.summaryCards;

    // Card 1: Total Students
    if (cards.students) {
      this.updateElement("totalStudents", cards.students.value);
      this.updateElement(
        "studentGrowth",
        cards.students.secondary || "Enrolled this term"
      );
    }

    // Card 2: Attendance Today
    if (cards.attendance) {
      this.updateElement("attendanceToday", cards.attendance.value);
      this.updateElement(
        "attendanceDetails",
        cards.attendance.secondary || "Present: -- | Absent: --"
      );
    }

    // Card 3: Class Schedules
    if (cards.schedules) {
      this.updateElement("classSchedules", cards.schedules.value);
    }

    // Card 4: Pending Admissions
    if (cards.admissions) {
      this.updateElement("pendingAdmissions", cards.admissions.value);
      this.updateElement(
        "admissionDetails",
        cards.admissions.secondary || "Applications awaiting review"
      );
    }

    // Card 5: Discipline Cases
    if (cards.discipline) {
      this.updateElement("disciplineCases", cards.discipline.value);
      this.updateElement(
        "disciplineDetails",
        cards.discipline.secondary || "Open cases requiring attention"
      );
    }

    // Card 6: Parent Communications
    if (cards.communications) {
      this.updateElement("parentComms", cards.communications.value);
    }

    // Card 7: Student Assessments
    if (cards.assessments) {
      this.updateElement("assessments", cards.assessments.value);
      this.updateElement(
        "assessmentDetails",
        cards.assessments.secondary || "Recent tests & exams"
      );
    }

    // Card 8: Class Performance
    if (cards.performance) {
      this.updateElement("classPerformance", cards.performance.value);
    }

    // Update last updated time
    this.updateElement("lastUpdated", new Date().toLocaleTimeString());
  },

  updateElement: function (id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  },

  renderCharts: function () {
    // Destroy existing charts first
    this.destroyCharts();

    // Attendance Trend Chart
    const attendanceCtx = document.getElementById("attendanceChart");
    if (attendanceCtx && this.state.chartData.attendance_trend) {
      const chartData = this.state.chartData.attendance_trend;
      const attendanceLabels = chartData.labels || chartData.days || [];
      const attendanceValues = chartData.data || [];
      if (!attendanceLabels.length || !attendanceValues.length) {
        this.showChartEmpty(attendanceCtx);
      } else {
      this.clearChartEmpty(attendanceCtx);
      this.charts.attendance = new Chart(attendanceCtx, {
        type: "line",
        data: {
          labels: attendanceLabels,
          datasets: [
            {
              label: "Attendance %",
              data: attendanceValues,
              borderColor: "#0d6efd",
              backgroundColor: "rgba(13, 110, 253, 0.1)",
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: "#0d6efd",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            y: { min: 0, max: 100, ticks: { callback: (v) => v + "%" } },
          },
        },
      });
      }
    }

    // Performance Chart
    const performanceCtx = document.getElementById("performanceChart");
    if (performanceCtx && this.state.chartData.class_performance) {
      const chartData = this.state.chartData.class_performance;
      const performanceLabels = chartData.labels || [];
      const performanceValues = chartData.data || [];
      if (!performanceLabels.length || !performanceValues.length) {
        this.showChartEmpty(performanceCtx);
      } else {
      this.clearChartEmpty(performanceCtx);
      this.charts.performance = new Chart(performanceCtx, {
        type: "bar",
        data: {
          labels: performanceLabels,
          datasets: [
            {
              label: "Average Score",
              data: performanceValues,
              backgroundColor: "#198754",
              borderColor: "#198754",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            y: { min: 0, max: 100, ticks: { callback: (v) => v + "%" } },
          },
        },
      });
      }
    }
  },

  showChartEmpty: function (canvas) {
    canvas.hidden = true;
    if (!canvas.parentElement?.querySelector('[data-headteacher-chart-empty]')) {
      const empty = document.createElement('div');
      empty.dataset.headteacherChartEmpty = 'true';
      empty.className = 'h-100 d-flex align-items-center justify-content-center text-muted text-center';
      empty.textContent = 'No chart data is recorded for this period.';
      canvas.parentElement?.appendChild(empty);
    }
  },

  clearChartEmpty: function (canvas) {
    canvas.hidden = false;
    canvas.parentElement?.querySelector('[data-headteacher-chart-empty]')?.remove();
  },

  renderTables: function () {
    // Pending Admissions Table (matches PHP id="admissionsTableBody")
    const admissionsTable = document.getElementById("admissionsTableBody");
    if (admissionsTable && this.state.tableData.pending_admissions) {
      const data =
        this.state.tableData.pending_admissions.data ||
        this.state.tableData.pending_admissions ||
        [];
      if (data.length === 0) {
        admissionsTable.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No pending admissions</td></tr>';
      } else {
        admissionsTable.innerHTML = data
          .slice(0, 5)
          .map(
            (row) => `
                    <tr>
                        <td>${this.escapeHtml(row.student_name || row.name || "-")}</td>
                        <td>${this.escapeHtml(row.class_applied || row.form || "-")}</td>
                        <td><small>${this.formatDate(
                          row.submitted_at || row.applied
                        )}</small></td>
                        <td>
                            <span class="badge bg-warning">Pending</span>
                        </td>
                    </tr>
                `
          )
          .join("");
      }
    }

    // Discipline Cases Table (matches PHP id="disciplineTableBody")
    const disciplineTable = document.getElementById("disciplineTableBody");
    if (disciplineTable && this.state.tableData.discipline_cases) {
      const data =
        this.state.tableData.discipline_cases.data ||
        this.state.tableData.discipline_cases ||
        [];
      if (data.length === 0) {
        disciplineTable.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No open discipline cases</td></tr>';
      } else {
        disciplineTable.innerHTML = data
          .slice(0, 5)
          .map(
            (row) => `
                    <tr>
                        <td>${this.escapeHtml(row.student_name || row.student || "-")}</td>
                        <td>${this.escapeHtml(row.class_name || row.form || "-")}</td>
                        <td><small>${
                          this.escapeHtml(row.violation || row.description || row.offense || "-")
                        }</small></td>
                        <td>
                            <span class="badge bg-${
                              row.severity === "high" ||
                              row.severity === "Major"
                                ? "danger"
                                : "warning"
                            }">
                                ${this.escapeHtml(row.severity || "Pending")}
                            </span>
                        </td>
                    </tr>
                `
          )
          .join("");
      }
    }

    // Upcoming Events (matches PHP id="upcomingEvents") - shared smart widget
    const eventsContainer = document.getElementById("upcomingEvents");
    if (eventsContainer && this.state.tableData.upcoming_events) {
      const events =
        this.state.tableData.upcoming_events.data ||
        this.state.tableData.upcoming_events ||
        [];
      if (typeof window.UpcomingEventsWidget?.render === "function") {
        window.UpcomingEventsWidget.render(eventsContainer, events, {
          max: 5,
          emptyText: "No upcoming events",
        });
      } else if (events.length === 0) {
        eventsContainer.innerHTML =
          '<li class="list-group-item text-center text-muted py-3">No upcoming events</li>';
      } else {
        eventsContainer.innerHTML = events
          .slice(0, 5)
          .map(
            (event) => `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${this.escapeHtml(event.title || event.name || "-")}</strong>
                            <br><small class="text-muted">${this.formatDate(
                              event.event_date || event.date
                            )}</small>
                        </div>
                        <span class="badge bg-info">${
                          this.escapeHtml(event.type || "Event")
                        }</span>
                    </li>
                `
          )
          .join("");
      }
    }
  },

  destroyCharts: function () {
    Object.values(this.charts).forEach((chart) => {
      if (chart && typeof chart.destroy === "function") {
        chart.destroy();
      }
    });
    this.charts = {};
  },

  showLoading: function (show) {
    // Update refresh button state
    const refreshBtn = document.getElementById("refreshDashboard");
    if (refreshBtn) {
      if (show) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
      } else {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML =
          '<i class="bi bi-arrow-clockwise me-1"></i> Refresh';
      }
    }
  },

  showErrorState: function (message) {
    const errorDiv = document.getElementById("dashboardError");
    const errorMsg = document.getElementById("dashboardErrorMessage");

    if (errorDiv) {
      errorDiv.hidden = false;
      if (errorMsg)
        errorMsg.textContent = message || "Failed to load dashboard data";
    }
  },

  hideErrorState: function () {
    const errorDiv = document.getElementById("dashboardError");
    if (errorDiv) errorDiv.hidden = true;
  },

  setupEventListeners: function () {
    // Refresh button
    const refreshBtn = document.getElementById("refreshDashboard");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        this.hideErrorState();
        this.loadDashboardData();
      });
    }
  },

  setupPeriodListeners: function () {
    const periodBar = document.getElementById("headteacherDashboardPeriodBar");
    if (!periodBar) return;

    periodBar.addEventListener("click", (event) => {
      const btn = event.target.closest(".dash-period-btn");
      if (!btn) return;
      const newPeriod = btn.dataset.period;
      if (newPeriod === this.period) return;

      this.period = newPeriod;
      periodBar.querySelectorAll(".dash-period-btn").forEach((b) => {
        b.classList.remove("btn-success");
        b.classList.add("btn-outline-success");
      });
      btn.classList.remove("btn-outline-success");
      btn.classList.add("btn-success");

      const label = document.getElementById("headteacherDashboardPeriodBarLabel");
      if (label) label.textContent = btn.textContent;

      this.hideErrorState();
      this.loadDashboardData();
    });

    const defaultBtn = periodBar.querySelector(".dash-period-btn.btn-success");
    if (defaultBtn) {
      const label = document.getElementById("headteacherDashboardPeriodBarLabel");
      if (label) label.textContent = defaultBtn.textContent;
    }
  },

  setupAutoRefresh: function () {
    const refresh = () => {
      if (!document.getElementById("headteacherDashboard")) {
        clearInterval(this.autoRefreshTimer);
        return;
      }
      if (document.visibilityState === "visible" && !this.state.isLoading) {
        this.loadDashboardData();
      }
    };
    document.addEventListener("visibilitychange", refresh);
    window.addEventListener("online", refresh);
    this.autoRefreshTimer = setInterval(refresh, this.config.refreshInterval);
  },

  // Utility methods
  formatNumber: function (num) {
    if (num === undefined || num === null) return "0";
    num = Number(num);
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "K";
    return num.toLocaleString();
  },

  formatPercent: function (num) {
    return Math.round(Number(num) || 0) + "%";
  },

  formatDate: function (date) {
    if (!date) return "-";
    try {
      return new Date(date).toLocaleDateString("en-KE", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (e) {
      return date;
    }
  },

  escapeHtml: function (value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  formatTitle: function (key) {
    return key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
  },
};

if (window.__APP_BOOTED__) {
    headteacherDashboardController.init();
} else {
    window.addEventListener('kingsway:ready', () => {
        headteacherDashboardController.init();
    }, { once: true });
}
