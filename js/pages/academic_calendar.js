/** Read-only academic calendar page backed by the shared calendar component. */
const academicCalendarController = {
  table: null,
  yearId: null,
  year: null,

  async init() {
    await window.AuthContext?.ready?.();
    if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }

    // The year filter loads the table, so the table does not load on its own.
    this.table = window.AcademicEventsTable?.create?.({
      containerId: 'calendarEventsTable',
      autoLoad: false,
      onReady: (instance) => this.renderUpcoming(instance.state.allEvents),
    });
    if (!this.table) {
      this.showError('The calendar view could not be initialized. Refresh the page to try again.');
      return;
    }
    document.getElementById('printCalendar')?.addEventListener('click', () => this.print());
    document.getElementById('exportCalendarCsv')?.addEventListener('click', () => this.exportCsv());
    document.getElementById('calendarPdf')?.addEventListener('click', () => this.downloadPdf());

    const select = document.getElementById('academicYearFilter');
    if (window.AcademicYearFilter && select) {
      // Calls back once with the default (current) year, then on every change.
      await window.AcademicYearFilter.mount(select, {
        onChange: (id, year) => {
          this.yearId = id || null;
          this.year = year || null;
          this.table.setYear(this.yearId);
        },
      });
    } else {
      await this.table.load();
    }
  },

  renderUpcoming(events) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const upcoming = (Array.isArray(events) ? events : []).filter((event) => {
      const date = new Date(`${event.start_date || event.date || event.event_date || ''}T00:00:00`);
      return !Number.isNaN(date.getTime()) && date >= today;
    });
    window.UpcomingEventsWidget?.render?.(
      document.getElementById('upcomingEvents'),
      upcoming,
      { max: 8, emptyText: 'No upcoming events for this academic year.' },
    );
  },

  yearLabel() {
    return String(this.year?.year_code || this.year?.year_name || 'current').replace(/[^0-9A-Za-z-]+/g, '-');
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      window.showNotification?.('You do not have permission to print', 'warning');
      return;
    }
    window.print();
  },

  /** CSV of the rows the table is showing (its filters applied). */
  exportCsv() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      window.showNotification?.('You do not have permission to export', 'warning');
      return;
    }
    const rows = this.table?.state?.events || [];
    if (!rows.length) {
      window.showNotification?.('There are no events to export.', 'info');
      return;
    }
    const cell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const lines = [['Term', 'Week', 'Event', 'Type', 'From', 'To', 'Location', 'Status'].map(cell).join(',')];
    rows.forEach((ev) => {
      lines.push([
        ev.term_name, ev.week_number, ev.title, ev.type,
        ev.start_date || ev.date, ev.end_date || ev.start_date || ev.date, ev.location, ev.status,
      ].map(cell).join(','));
    });
    window.KingswayFileLifecycle.exportText('﻿' + lines.join('\r\n'), `academic_calendar_${this.yearLabel()}.csv`, 'text/csv;charset=utf-8');
  },

  /** The official term-by-term calendar PDF for the selected year. */
  async downloadPdf() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
      window.showNotification?.('You do not have permission to print', 'warning');
      return;
    }
    if (!window.PrintManager?.printAcademicCalendar) return;
    const options = { filename: `academic_calendar_${this.yearLabel()}` };
    if (this.yearId) options.academicYear = this.yearId;
    await window.PrintManager.printAcademicCalendar(options).catch(() => {});
  },

  showError(message) {
    const upcoming = document.getElementById('upcomingEvents');
    if (upcoming) {
      const item = document.createElement('li');
      item.className = 'list-group-item text-danger text-center py-4';
      item.textContent = String(message ?? 'Could not load upcoming events.');
      upcoming.replaceChildren(item);
    }
    window.showNotification?.(message, 'error');
  },
};

window.academicCalendarController = academicCalendarController;
document.addEventListener('DOMContentLoaded', () => academicCalendarController.init().catch((error) => {
  console.error('Academic Calendar initialization failed:', error);
  academicCalendarController.showError('Could not load the academic calendar. Refresh to try again.');
}));
