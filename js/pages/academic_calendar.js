/**
 * Academic Calendar Controller
 * Page: academic_calendar.php
 * Read-only calendar view (headteacher / deputy roles). Delegates the events
 * table to the shared AcademicEventsTable component and renders a deduplicated
 * upcoming-events widget. Editing happens on the School Events page.
 */
const AcademicCalendarController = {
    initialized: false,
    table: null,

    async init() {
        await window.AuthContext?.ready();
        if (!window.AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || "") + "/index.php";
            return;
        }
        if (this.initialized) return;
        this.initialized = true;

        this.table = window.AcademicEventsTable.create({
            containerId: "calendarEventsTable",
            editable: false,
        });

        await this.loadUpcomingEvents();

        const printBtn = document.getElementById("printCalendar");
        if (printBtn) {
            printBtn.addEventListener("click", () => this.printCalendar());
        }
    },

    async loadUpcomingEvents() {
        const container = document.getElementById("upcomingEvents");
        if (!container || !window.UpcomingEventsWidget) return;
        try {
            const res = await window.API.academic.getCustom({
                action: "unified-events",
                scope: "upcoming",
            });
            const data = res?.data || res;
            const events = (data && data.events) || [];
            window.UpcomingEventsWidget.render(container, events, { max: 8 });
        } catch (error) {
            console.error("Error loading upcoming events:", error);
            container.innerHTML =
                '<li class="list-group-item text-center text-muted py-4">Failed to load upcoming events.</li>';
        }
    },

    printCalendar() {
        const events = this.table?.state?.events || [];
        if (!events.length) {
            this.showNotification("No events to print for the current filter", "warning");
            return;
        }

        if (window.PrintManager?.printAcademicCalendar) {
            window.PrintManager.printAcademicCalendar({
                title: "Academic Calendar",
                subtitle: "School Events Schedule",
                academicYear: this.state?.academicYear || null,
                events: events,
                filename: `academic_calendar_${new Date().toISOString().slice(0,10)}`,
            });
        } else if (window.PrintManager?.printTable) {
            window.PrintManager.printTable({
                title: "Academic Calendar",
                subtitle: "School Events Schedule",
                columns: [
                    { key: "term_name", label: "Term" },
                    { key: "week_number", label: "Week" },
                    { key: "title", label: "Event" },
                    { key: "start_date", label: "Start Date" },
                    { key: "start_time", label: "Start Time" },
                    { key: "end_date", label: "End Date" },
                    { key: "end_time", label: "End Time" },
                    { key: "type", label: "Type" },
                    { key: "status", label: "Status" },
                ],
                rows: events,
                orientation: "landscape",
                paperSize: "A4",
            });
        } else {
            this.showNotification("Printing is not available", "error");
        }
    },

    showNotification(message, type = "info") {
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }
        const alert = document.createElement("div");
        alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.style.zIndex = "9999";
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    },
};

document.addEventListener("DOMContentLoaded", () => AcademicCalendarController.init());
