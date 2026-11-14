/**
 * Dashboard tabs: term ribbon, Reports and Export-and-print panes.
 * Markup: components/dashboards/_dashboard_tabs.php.
 *
 * - The term ribbon draws one segment per school week, counted with the same
 *   rule as AcademicCalendarService::countWeeks (a partial first week, then
 *   Monday–Friday weeks), so the week shown matches the school calendar.
 * - Report rows the user cannot open are removed (AppRouteAccess decides).
 * - The export list is built from tables in the Overview that carry
 *   data-export-title. CSV exports what the table shows; Print uses the
 *   school's letterhead through PrintManager. AuthContext.canExport/canPrint
 *   gate each action by the table's data-export-module.
 */
(function (window, document) {
  'use strict';

  const DAY_MS = 86400000;

  // ---- Term ribbon --------------------------------------------------------

  function parseDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
  }

  function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  /** ISO weekday: Monday 1 … Sunday 7. */
  function isoDay(date) {
    return date.getDay() === 0 ? 7 : date.getDay();
  }

  /** Port of AcademicCalendarService::countWeeks, returning the buckets. */
  function schoolWeeks(opening, closing) {
    const weeks = [];
    let cursor = new Date(opening);
    while (cursor <= closing && weeks.length < 60) {
      const dow = isoDay(cursor);
      let end = addDays(cursor, dow <= 5 ? 5 - dow : 7 - dow);
      if (end > closing) end = new Date(closing);
      weeks.push({ start: new Date(cursor), end });
      cursor = addDays(end, 1);
      if (isoDay(cursor) >= 6) cursor = addDays(cursor, 8 - isoDay(cursor));
    }
    return weeks;
  }

  function formatDay(date) {
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
  }

  /** "tomorrow", "in 4 days", "in 7 weeks": how far away a date is. */
  function distance(from, to) {
    const days = Math.round((to - from) / DAY_MS);
    if (days <= 1) return 'tomorrow';
    if (days < 14) return `in ${days} days`;
    return `in ${Math.round(days / 7)} weeks`;
  }

  async function loadTerms() {
    try {
      const response = await window.API?.academic?.getTerms?.();
      const rows = response?.terms || response?.data?.terms || response?.data || response;
      if (!Array.isArray(rows)) return [];
      return rows.map((row) => ({
        name: row.name || `Term ${row.term_number}`,
        yearId: row.academic_year_id,
        yearName: row.year_name || '',
        serverWeeks: Number(row.weeks) || null,
        opening: parseDate(row.opening_date || row.start_date),
        closing: parseDate(row.closing_date || row.end_date),
        breakStart: parseDate(row.half_term_start),
        breakEnd: parseDate(row.half_term_end),
      }))
        .filter((term) => term.opening && term.closing && term.closing >= term.opening)
        .sort((x, y) => x.opening - y.opening);
    } catch (_) {
      return [];
    }
  }

  /**
   * Which school year to draw and what to say about today:
   *   in a term     -> that year; "Week n of N" (or the half-term break)
   *   between terms -> the year of the next term; when it opens
   *   no next term  -> the last year; that nothing is scheduled yet
   */
  function describeToday(terms, today) {
    const current = terms.find((term) => term.opening <= today && today <= term.closing);
    const next = terms.find((term) => term.opening > today);
    const focus = current || next || terms[terms.length - 1];
    return { current, next, yearId: focus.yearId, yearName: focus.yearName };
  }

  function weekSegments(term, today) {
    const weeks = schoolWeeks(term.opening, term.closing);
    let currentIndex = -1;
    if (today <= term.closing) {
      weeks.forEach((week, index) => { if (week.start <= today) currentIndex = index; });
    }
    return weeks.map((week, index) => {
      let state = 'ahead';
      let fill = 0;
      if (today > term.closing || index < currentIndex) {
        state = 'done';
        fill = 1;
      } else if (index === currentIndex) {
        state = 'now';
        const span = Math.max(1, Math.round((week.end - week.start) / DAY_MS) + 1);
        fill = Math.min(span, Math.round((today - week.start) / DAY_MS) + 1) / span;
      }
      const onBreak = Boolean(term.breakStart && term.breakEnd
        && week.start <= term.breakEnd && week.end >= term.breakStart);
      return { ...week, index, state, fill, onBreak };
    });
  }

  async function renderTerm(root) {
    const box = root.querySelector('[data-kw-term]');
    if (!box) return;
    const terms = await loadTerms();
    if (!terms.length) return; // no calendar configured: the ribbon stays hidden

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const view = describeToday(terms, today);
    const yearTerms = terms.filter((term) => term.yearId === view.yearId);

    const nameEl = box.querySelector('[data-kw-term-name]');
    const weekEl = box.querySelector('[data-kw-term-week]');
    weekEl.textContent = '';
    let summary;
    if (view.current) {
      const segments = weekSegments(view.current, today);
      const now = segments.find((segment) => segment.state === 'now');
      const number = now ? now.index + 1 : segments.length;
      nameEl.textContent = view.current.name;
      const strong = document.createElement('strong');
      strong.textContent = `Week ${number}`;
      weekEl.append(strong, ` of ${segments.length}`);
      if (now?.onBreak) weekEl.append(', half-term break');
      summary = `${view.current.name}, week ${number} of ${segments.length}`;
    } else if (view.next) {
      nameEl.textContent = 'School holiday';
      weekEl.textContent = `${view.next.name} opens ${formatDay(view.next.opening)}, ${distance(today, view.next.opening)}`;
      summary = `School holiday. ${weekEl.textContent}`;
    } else {
      nameEl.textContent = 'School holiday';
      weekEl.textContent = 'The next term has not been scheduled yet';
      summary = `School holiday. ${weekEl.textContent}`;
    }
    box.querySelector('[data-kw-term-year]').textContent = view.yearName;

    const year = box.querySelector('[data-kw-year]');
    year.innerHTML = '';
    yearTerms.forEach((term) => {
      const segments = weekSegments(term, today);
      const group = document.createElement('div');
      group.className = 'kw-year-term';
      if (term === view.current) group.classList.add('is-current');
      group.style.flexGrow = String(segments.length);

      const track = document.createElement('div');
      track.className = 'kw-term-track';
      segments.forEach((segment) => {
        const el = document.createElement('span');
        el.className = `kw-term-seg is-${segment.state}${segment.onBreak ? ' is-break' : ''}`;
        el.style.setProperty('--fill', segment.fill.toFixed(3));
        el.title = `${term.name}, week ${segment.index + 1}: ${formatDay(segment.start)} to ${formatDay(segment.end)}`
          + (segment.onBreak ? ' (half-term break)' : '');
        track.appendChild(el);
      });

      const label = document.createElement('div');
      label.className = 'kw-year-label';
      const termName = document.createElement('span');
      termName.className = 'kw-year-name';
      termName.textContent = term.name;
      const dates = document.createElement('span');
      dates.className = 'kw-year-dates';
      dates.textContent = `${formatDay(term.opening)} – ${formatDay(term.closing)}`;
      label.append(termName, dates);

      group.dataset.weeks = String(segments.length);
      if (term.serverWeeks) group.dataset.serverWeeks = String(term.serverWeeks);
      group.append(track, label);
      year.appendChild(group);
    });
    year.setAttribute('aria-label', `${view.yearName} school year. ${summary}.`);
    box.hidden = false;
  }

  // ---- Reports ------------------------------------------------------------

  async function filterReports(pane) {
    const list = pane?.querySelector('[data-kw-report-list]');
    if (!list) return;
    const access = window.AppRouteAccess?.authorizeRoute;
    const rows = Array.from(list.querySelectorAll('[data-kw-report-route]'));
    if (typeof access === 'function') {
      await Promise.all(rows.map(async (row) => {
        try {
          const result = await access(row.dataset.kwReportRoute);
          if (result && result.authorized === false) row.remove();
        } catch (_) {
          // Unknown answer: keep the link; the page itself enforces access.
        }
      }));
    }
    const empty = pane.querySelector('[data-kw-report-empty]');
    if (empty) empty.hidden = list.children.length > 0;
  }

  // ---- Export and print ---------------------------------------------------

  /** A cell's readable text, skipping decoration hidden from assistive tech
   *  (avatar initials, icons) so "JK Joseph Kiprop" exports as the name. */
  function cellText(cell) {
    const copy = cell.cloneNode(true);
    copy.querySelectorAll('[aria-hidden="true"]').forEach((node) => node.remove());
    return String(copy.textContent || '').replace(/\s+/g, ' ').trim();
  }

  /** Header labels and the rows the table currently shows. */
  function readTable(table) {
    const headers = Array.from(table.querySelectorAll('thead th')).map(cellText);
    // Searches hide rows with style.display or the hidden/d-none markers. The
    // overview pane itself is hidden while this tab is open, so layout-based
    // visibility checks would report every row as hidden.
    const rows = Array.from(table.querySelectorAll('tbody tr'))
      .filter((row) => !row.hidden && row.style.display !== 'none' && !row.classList.contains('d-none'))
      .map((row) => Array.from(row.cells).map(cellText))
      // Placeholder rows ("Loading…", "No records") span every column.
      .filter((cells) => cells.length === headers.length && cells.some(Boolean));
    return { headers, rows };
  }

  function csvValue(value) {
    const text = String(value ?? '');
    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  }

  function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }

  function allowed(kind, module) {
    const auth = window.AuthContext;
    const check = kind === 'export' ? auth?.canExport : auth?.canPrint;
    return typeof check !== 'function' || !module || check.call(auth, module);
  }

  function notify(message, type) {
    if (typeof window.showNotification === 'function') window.showNotification(message, type);
  }

  async function downloadCsv(table) {
    const title = table.dataset.exportTitle;
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify(`${title} has no rows to download.`, 'warning');
      return;
    }
    const csv = [headers, ...rows].map((line) => line.map(csvValue).join(',')).join('\r\n');
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(`﻿${csv}`, `${slug(title)}_${date}.csv`, 'text/csv;charset=utf-8');
  }

  async function printTable(table) {
    const title = table.dataset.exportTitle;
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify(`${title} has no rows to print.`, 'warning');
      return;
    }
    if (!window.PrintManager?.printTable) {
      window.print();
      return;
    }
    const columns = headers.map((label, index) => ({ key: `c${index}`, label }));
    const records = rows.map((cells) => Object.fromEntries(cells.map((value, index) => [`c${index}`, value])));
    await window.PrintManager.printTable({ title, columns, rows: records });
  }

  function exportRow(table) {
    const title = table.dataset.exportTitle;
    const module = table.dataset.exportModule || '';
    const count = readTable(table).rows.length;

    const item = document.createElement('li');
    item.className = 'kw-export-row';
    const label = document.createElement('div');
    label.className = 'kw-export-label';
    const name = document.createElement('span');
    name.className = 'kw-export-name';
    name.textContent = title;
    const meta = document.createElement('span');
    meta.className = 'kw-export-count';
    meta.textContent = count === 1 ? '1 row on the overview' : `${count} rows on the overview`;
    label.append(name, meta);

    const actions = document.createElement('div');
    actions.className = 'kw-export-actions';
    const makeButton = (kind, icon, text, run) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-outline-secondary';
      button.innerHTML = `<i class="bi ${icon} me-1" aria-hidden="true"></i>`;
      button.append(text);
      if (!allowed(kind, module)) {
        button.disabled = true;
        button.title = kind === 'export'
          ? 'Your account cannot download this data.'
          : 'Your account cannot print this data.';
      } else if (count === 0) {
        button.disabled = true;
        button.title = 'This table has no rows yet.';
      } else {
        button.addEventListener('click', async () => {
          button.disabled = true;
          try { await run(table); } catch (_) { notify(`Could not finish: ${text.toLowerCase()}.`, 'error'); }
          finally { button.disabled = false; }
        });
      }
      return button;
    };
    actions.append(
      makeButton('export', 'bi-filetype-csv', 'Download CSV', downloadCsv),
      makeButton('print', 'bi-printer', 'Print', printTable),
    );
    item.append(label, actions);
    return item;
  }

  function renderExports(pane, overview) {
    const list = pane?.querySelector('[data-kw-export-list]');
    if (!list || !overview) return;
    list.innerHTML = '';
    overview.querySelectorAll('table[data-export-title]').forEach((table) => list.appendChild(exportRow(table)));
  }

  // ---- Wiring -------------------------------------------------------------

  function init() {
    document.querySelectorAll('[data-kw-dash-tabs]').forEach((root) => {
      if (root.dataset.kwReady) return;
      root.dataset.kwReady = '1';
      const id = root.dataset.kwDashTabs;
      // The panes are siblings of the header, not inside it.
      const overview = document.getElementById(`${id}-overview`);
      const reportsPane = document.getElementById(`${id}-reports`);
      const exportPane = document.getElementById(`${id}-export`);

      void renderTerm(root);
      void filterReports(reportsPane);

      // Row counts change as the overview loads and filters, so build the
      // export list each time its pane becomes active. Watching the pane's
      // class works however the tab is switched (a click, or the UI state
      // manager restoring the last tab); shown.bs.tab did not always fire.
      if (exportPane) {
        let wasActive = exportPane.classList.contains('active');
        if (wasActive) renderExports(exportPane, overview);
        new MutationObserver(() => {
          const active = exportPane.classList.contains('active');
          if (active && !wasActive) renderExports(exportPane, overview);
          wasActive = active;
        }).observe(exportPane, { attributes: true, attributeFilter: ['class'] });

        // Keep the row counts honest while the tab is open: the overview
        // keeps loading and refreshing its tables in the background.
        let pending = 0;
        overview?.querySelectorAll('table[data-export-title] tbody').forEach((body) => {
          new MutationObserver(() => {
            if (!exportPane.classList.contains('active')) return;
            window.clearTimeout(pending);
            pending = window.setTimeout(() => renderExports(exportPane, overview), 200);
          }).observe(body, { childList: true });
        });
      }

      exportPane?.querySelector('[data-kw-print-dashboard]')
        ?.addEventListener('click', () => {
          // Print the overview, not the pane holding the button.
          window.bootstrap?.Tab?.getOrCreateInstance(document.getElementById(`${id}-tab-overview`))?.show();
          window.setTimeout(() => window.print(), 250);
        });
    });
  }

  if (window.__APP_BOOTED__) {
    init();
  } else {
    window.addEventListener('kingsway:ready', init, { once: true });
  }
})(window, document);
