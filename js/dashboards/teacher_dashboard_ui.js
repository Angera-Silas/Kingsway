window.TeacherDashboardUI = (() => {
  'use strict';
  const escape = (value) => String(value ?? '—').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const unwrap = (response) => { let value = response; for (let i = 0; i < 4 && value && typeof value === 'object' && !Array.isArray(value) && 'data' in value; i += 1) value = value.data; return value || {}; };
  const formatDate = (value) => { const parsed = value ? new Date(value) : null; return parsed && !Number.isNaN(parsed.valueOf()) ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' }).format(parsed) : '—'; };
  const formatTime = (value) => value ? String(value).slice(0, 5) : '—';
  const setTeacherName = (root) => { const user = window.AuthContext?.getUser?.() || {}; const name = user.first_name || user.full_name || 'Teacher'; root.querySelectorAll('[data-teacher-name]').forEach((element) => { element.textContent = name; }); };
  const bindRoutes = (root) => root.addEventListener('click', (event) => { const link = event.target.closest('[data-route]'); if (!link) return; event.preventDefault(); window.AppRouter?.go?.(link.dataset.route); });
  const listItem = (icon, title, meta, accent = 'teal') => `<div class="teacher-list-item teacher-list-item--${accent}"><i class="bi ${icon}"></i><div><strong>${escape(title)}</strong><span>${escape(meta)}</span></div></div>`;
  const eventItems = (rows) => rows?.length ? rows.map((row) => listItem('bi-calendar-event', row.title || row.name || row.event_name, `${formatDate(row.start_date)}${row.event_type ? ` · ${row.event_type}` : ''}`, 'gold')).join('') : '<p class="teacher-empty">No upcoming events.</p>';
  const chart = (existing, canvas, config) => { existing?.destroy(); return canvas && typeof Chart !== 'undefined' ? new Chart(canvas, config) : null; };
  return { escape, unwrap, formatDate, formatTime, setTeacherName, bindRoutes, listItem, eventItems, chart };
})();
