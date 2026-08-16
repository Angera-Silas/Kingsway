/**
 * Fee Structure Controller (role-router umbrella)
 *
 * The manage_fee_structure.php shell routes the user to a role-specific
 * template (admin / accountant / viewer), each of which boots its own
 * controller (FeeStructureAdminController, FeeStructureAccountantController,
 * FeeStructureViewerController). This umbrella is lazy-loaded by
 * PageShell.loadRoleTemplate via `scriptSrc` and delegates init() to
 * whichever role controller is present.
 */

class FeeStructureController {
  constructor() {
    this.roleController = null;
  }

  static getRoleController() {
    return (
      window.FeeStructureAdminController ||
      window.FeeStructureAccountantController ||
      window.FeeStructureViewerController ||
      window.adminController ||
      window.accountantController ||
      window.viewerController
    );
  }

  /**
   * Initialize the controller
   */
  static async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const controller = window.fee_structureController;
    if (!controller) return null;
    const roleController = FeeStructureController.getRoleController();
    if (roleController && typeof roleController.init === "function") {
      controller.roleController = roleController;
      return roleController.init();
    }
    return null;
  }
}

window.fee_structureController = FeeStructureController;
