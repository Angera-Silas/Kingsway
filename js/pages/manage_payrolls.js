const ManagePayrollsController = {
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (typeof PayrollManagerController !== "undefined") {
      PayrollManagerController.init();
      return;
    }
    window.location.href = (window.APP_BASE || "") + "/home.php?route=manage_payrolls";
  },
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => ManagePayrollsController.init().catch(() => {}));
} else {
  ManagePayrollsController.init().catch(() => {});
}
