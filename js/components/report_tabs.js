document.addEventListener("click", (event) => {
  const tab = event.target.closest("[data-report-tab-target][data-report-tab-value]");
  if (!tab) return;
  const select = document.getElementById(tab.dataset.reportTabTarget);
  if (!select) return;
  select.value = tab.dataset.reportTabValue;
  const group = tab.closest('[role="tablist"]');
  group?.querySelectorAll("[data-report-tab-target]").forEach((button) => {
    const active = button === tab;
    button.classList.toggle("active", active);
    button.setAttribute("aria-selected", active ? "true" : "false");
  });
  select.dispatchEvent(new Event("change", { bubbles: true }));
});
