/**
 * AcademicYearFilter: one reusable academic-year (and term) selector for any page.
 *
 * Pages were built assuming a single academic year. This component lets a
 * page show any visible year (current, upcoming or completed; archived years
 * are never listed by the API) with the current year as the default.
 *
 *   AcademicYearFilter.mount(selectElement, {
 *     onChange: (yearId, year) => loadData(yearId),   // also called once on mount
 *   });
 *
 * Term-scoped pages (timetables, exams, results) also pass a term select. Its
 * options follow the chosen year; the default is the term running today, or
 * the next one to open in that year, or the year's last term:
 *
 *   AcademicYearFilter.mount(yearSelect, {
 *     termSelect,
 *     onChange: (yearId, year, termId, term) => load(yearId, termId),
 *   });
 *
 * Pages that can show a whole year (reports, analysis) pass allTermsLabel,
 * e.g. 'All terms': that option is listed first and is the default, and
 * onChange receives termId = null while it is selected.
 *
 * termId is the academic_year_terms id (what timetable/exam endpoints call
 * academic_year_term_id). The choice is kept in the URL (?academic_year_id=N
 * &academic_year_term_id=M), so a reload, a shared link or the back button
 * returns to the same context.
 */
(function (window) {
  'use strict';

  const PARAM = 'academic_year_id';
  const TERM_PARAM = 'academic_year_term_id';

  function stateLabel(year) {
    if (Number(year.is_current) === 1) return 'current';
    const state = String(year.state || '').toLowerCase();
    if (state === 'upcoming' || state === 'completed') return state;
    return '';
  }

  function optionText(year) {
    const name = year.year_name || year.year_code || `Year ${year.id}`;
    const state = stateLabel(year);
    return state ? `${name} (${state})` : name;
  }

  function fromUrl(name) {
    try {
      return Number(new URLSearchParams(window.location.search).get(name)) || 0;
    } catch (_) {
      return 0;
    }
  }

  /** Set (or, for the default value, remove) query parameters in place. */
  function writeUrl(values) {
    try {
      const url = new URL(window.location.href);
      Object.entries(values).forEach(([name, value]) => {
        if (value) url.searchParams.set(name, String(value));
        else url.searchParams.delete(name);
      });
      window.history.replaceState(window.history.state, '', url.toString());
    } catch (_) {
      // History is unavailable in some embedded contexts; the filter still works.
    }
  }

  function day(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
  }

  let termsPromise = null;
  async function loadTerms() {
    if (!termsPromise) {
      termsPromise = window.API.academic.getTerms()
        .then((response) => {
          const rows = response?.terms || response?.data?.terms || response?.data || response;
          return Array.isArray(rows) ? rows : [];
        })
        .catch(() => { termsPromise = null; return []; });
    }
    return termsPromise;
  }

  /** The term running today, else the next to open, else the last one. */
  function defaultTerm(terms) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const running = terms.find((term) => day(term.opening_date || term.start_date) <= today
      && today <= day(term.closing_date || term.end_date));
    if (running) return running;
    const next = terms.find((term) => day(term.opening_date || term.start_date) > today);
    return next || terms[terms.length - 1];
  }

  async function loadYears() {
    const response = await window.API.academic.getAllAcademicYears();
    const rows = response?.data?.years || response?.years || response?.data || response;
    return (Array.isArray(rows) ? rows : [])
      .filter((year) => year && year.id)
      .sort((a, b) => String(b.start_date || '').localeCompare(String(a.start_date || '')));
  }

  async function mount(select, { onChange, termSelect = null, allTermsLabel = '' } = {}) {
    if (!select) return null;
    select.disabled = true;
    let years = [];
    try {
      years = await loadYears();
    } catch (_) {
      years = [];
    }
    if (!years.length) {
      select.innerHTML = '<option value="">No academic years</option>';
      return null;
    }

    const current = years.find((year) => Number(year.is_current) === 1) || years[0];
    const requested = years.find((year) => Number(year.id) === fromUrl(PARAM));
    const selected = requested || current;
    const allTerms = termSelect ? await loadTerms() : [];
    let requestedTermId = fromUrl(TERM_PARAM);

    select.innerHTML = '';
    years.forEach((year) => {
      const option = document.createElement('option');
      option.value = String(year.id);
      option.textContent = optionText(year);
      select.appendChild(option);
    });
    select.value = String(selected.id);
    select.disabled = false;

    const selectedYear = () => years.find((item) => String(item.id) === select.value) || current;

    /** Rebuild the term options for the chosen year; returns the chosen term. */
    const fillTerms = () => {
      if (!termSelect) return null;
      const year = selectedYear();
      const terms = allTerms
        .filter((term) => Number(term.academic_year_id) === Number(year.id))
        .sort((a, b) => String(a.opening_date || '').localeCompare(String(b.opening_date || '')));
      termSelect.innerHTML = '';
      if (allTermsLabel) {
        const all = document.createElement('option');
        all.value = '';
        all.textContent = allTermsLabel;
        termSelect.appendChild(all);
      }
      terms.forEach((term) => {
        const option = document.createElement('option');
        option.value = String(term.id);
        option.textContent = term.name || `Term ${term.term_number || term.term_id}`;
        termSelect.appendChild(option);
      });
      termSelect.disabled = terms.length === 0;
      if (!terms.length) {
        termSelect.innerHTML = '<option value="">No terms in this year</option>';
        return null;
      }
      const fromLink = terms.find((term) => Number(term.id) === requestedTermId);
      if (allTermsLabel && !fromLink) {
        requestedTermId = 0;
        termSelect.value = '';
        return null;
      }
      const wanted = fromLink || defaultTerm(terms);
      requestedTermId = 0; // the URL term applies to the first fill only
      termSelect.value = String(wanted.id);
      return wanted;
    };

    const selectedTerm = () => {
      if (!termSelect) return null;
      return allTerms.find((term) => String(term.id) === termSelect.value) || null;
    };

    const notify = () => {
      const year = selectedYear();
      const term = selectedTerm();
      writeUrl({
        [PARAM]: Number(year.id) === Number(current.id) ? 0 : year.id,
        ...(termSelect ? { [TERM_PARAM]: term ? term.id : 0 } : {}),
      });
      if (typeof onChange === 'function') {
        onChange(Number(year.id), year, term ? Number(term.id) : null, term);
      }
    };
    select.addEventListener('change', () => { fillTerms(); notify(); });
    termSelect?.addEventListener('change', notify);
    fillTerms();
    notify();

    return { years, current, selected: selectedYear, selectedTerm };
  }

  window.AcademicYearFilter = Object.freeze({ mount, label: optionText });
})(window);
