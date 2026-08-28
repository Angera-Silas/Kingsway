/**
 * dashboard.js — Dashboard page controller.
 * Boots the permission-aware DashboardRouter (js/dashboards/dashboard_router.js)
 * once auth context is ready, so the role-specific dashboard loads into
 * #dashboardContainer.
 */
const dashboardPageController = {
  initialized: false,

  async init() {
    if (this.initialized) return;
    this.initialized = true;

    try {
      if (window.AuthContext?.ready) {
        await AuthContext.ready();
      }
    } catch (error) {
      console.warn("[DashboardPage] Auth context unavailable:", error);
      return;
    }

    if (window.DashboardRouter?.routeToDashboard) {
      try {
        await DashboardRouter.routeToDashboard();
      } catch (error) {
        console.error("[DashboardPage] Dashboard routing failed:", error);
      }
    } else {
      console.error("[DashboardPage] DashboardRouter not loaded.");
    }
  },
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => dashboardPageController.init(), { once: true });
} else {
  dashboardPageController.init();
}

window.dashboardPageController = dashboardPageController;
