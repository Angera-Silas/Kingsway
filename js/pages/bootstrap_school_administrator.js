(function () {
  "use strict";
  const state = { reference: null, busy: false };
  const el = (id) => document.getElementById(id);
  const notify = (message, type = "info") => {
    const box = el("bootstrapAlert");
    box.className = `alert alert-${type}`;
    box.textContent = message;
    box.classList.remove("d-none");
  };
  const rows = (response) => response?.data || response || {};
  const options = (items, label, placeholder) =>
    `<option value="">${placeholder}</option>` + items.map((item) => `<option value="${Number(item.id)}">${String(label(item)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</option>`).join("");

  function filterCategories() {
    const type = Number(el("bootstrapStaffType").value);
    const list = (state.reference.staff_categories || []).filter(item => Number(item.staff_type_id) === type);
    el("bootstrapStaffCategory").innerHTML = options(list, item => item.name, "Select category");
  }

  async function load() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.hasRole?.("System Administrator")) {
      notify("Only a System Administrator may use this workflow.", "danger"); return;
    }
    try {
      state.reference = rows(await window.API.staff.getSchoolAdministratorBootstrap());
      if (!state.reference.available) {
        const admin = state.reference.existing_administrator || {};
        el("bootstrapAvailability").className = "badge text-bg-success px-3 py-2";
        el("bootstrapAvailability").textContent = "Bootstrap complete";
        notify(`The production School Administrator is already established: ${admin.full_name || admin.username} (${admin.staff_no || "staff record"}).`, "success");
        return;
      }
      el("bootstrapAvailability").className = "badge text-bg-primary px-3 py-2";
      el("bootstrapAvailability").textContent = "Available once";
      el("bootstrapDepartment").innerHTML = options(state.reference.departments || [], item => `${item.name}${item.code ? ` (${item.code})` : ""}`, "Select department");
      el("bootstrapStaffType").innerHTML = options(state.reference.staff_types || [], item => item.name, "Select staff type");
      el("bootstrapSupervisor").innerHTML = options(state.reference.supervisors || [], item => `${item.name} — ${item.staff_no}`, "None");
      el("bootstrapStaffType").addEventListener("change", filterCategories);
      const adminType = (state.reference.staff_types || []).find(item => /admin/i.test(item.name));
      if (adminType) { el("bootstrapStaffType").value = adminType.id; filterCategories(); }
      document.querySelector('[name="employment_date"]').value = new Date().toISOString().slice(0, 10);
      el("schoolAdminBootstrapForm").classList.remove("d-none");
      notify("Complete the administrator’s verified production details. This action can only succeed once.", "info");
    } catch (error) { notify(error?.message || "Could not load bootstrap data.", "danger"); }
  }

  async function submit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (state.busy || !form.reportValidity()) return;
    const data = Object.fromEntries(new FormData(form).entries());
    delete data.supervisor_id;
    const supervisor = form.elements.supervisor_id.value;
    if (supervisor) data.supervisor_id = Number(supervisor);
    ["department_id", "staff_type_id", "staff_category_id", "late_threshold_minutes"].forEach(key => data[key] = Number(data[key]));
    data.salary = Number(data.salary);
    const confirmed = await window.confirmAction("Create First School Administrator", "Create this production staff account and send the secure invitation now? This one-time bootstrap cannot be repeated after success.", { confirmText: "Create Administrator" });
    if (!confirmed) return;
    state.busy = true; el("bootstrapSubmit").disabled = true;
    try {
      const result = rows(await window.API.staff.bootstrapSchoolAdministrator(data));
      const photo = el("bootstrapProfilePhoto").files[0];
      if (photo && result.staff_id) await window.API.staff.uploadPhoto(result.staff_id, photo);
      form.classList.add("d-none");
      el("bootstrapAvailability").className = "badge text-bg-success px-3 py-2";
      el("bootstrapAvailability").textContent = "Bootstrap complete";
      notify(`School Administrator ${result.staff_no} was created. The secure invitation was sent to ${result.email}.`, "success");
    } catch (error) { notify(error?.message || "Bootstrap failed. No second account was created.", "danger"); }
    finally { state.busy = false; el("bootstrapSubmit").disabled = false; }
  }
  document.addEventListener("DOMContentLoaded", () => { el("schoolAdminBootstrapForm")?.addEventListener("submit", submit); load(); });
})();
