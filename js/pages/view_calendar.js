/**
 * View Calendar Page Controller
 * Manages academic calendar display and event management
 */
const ViewCalendarController = (() => {
    let events = [];
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];

    const typeColors = {
        academic: 'primary', exam: 'danger', holiday: 'success',
        meeting: 'info', sports: 'warning', extracurricular: 'secondary', other: 'dark'
    };

    async function loadData() {
        try {
            const response = await window.API.schedules.getEvents();
            const data = response?.data || response || [];
            events = Array.isArray(data) ? data : (data.events || data.data || []);

            renderStats(events);
            renderCalendar();
            renderUpcomingEvents();
        } catch (e) {
            console.error('Load calendar failed:', e);
            events = [];
            renderCalendar();
        }
    }

    function renderStats(data) {
        const now = new Date();
        const thisMonthEvents = data.filter(e => {
            const d = new Date(e.start_date || e.date);
            return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        });
        const academic = data.filter(e => (e.type || e.event_type || '').toLowerCase() === 'academic');
        const extra = data.filter(e => ['sports', 'extracurricular'].includes((e.type || e.event_type || '').toLowerCase()));

        document.getElementById('totalEvents').textContent = data.length;
        document.getElementById('thisMonthEvents').textContent = thisMonthEvents.length;
        document.getElementById('academicEvents').textContent = academic.length;
        document.getElementById('extraCurricularEvents').textContent = extra.length;
    }

    function renderCalendar() {
        const title = document.getElementById('calendarTitle');
        if (title) title.textContent = `${monthNames[currentMonth]} ${currentYear}`;

        const monthSelect = document.getElementById('monthFilter');
        if (monthSelect) monthSelect.value = currentMonth;

        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();

        const tbody = document.getElementById('calendarBody');
        if (!tbody) return;

        tbody.replaceChildren();
        const weekTemplate = document.getElementById('calendarWeekTemplate');
        const cellTemplate = document.getElementById('calendarDayCellTemplate');
        const blankTemplate = document.getElementById('calendarBlankCellTemplate');
        const eventTemplate = document.getElementById('calendarEventTemplate');
        if (!weekTemplate || !cellTemplate || !blankTemplate || !eventTemplate) return;

        let week = weekTemplate.content.firstElementChild.cloneNode(true);
        tbody.appendChild(week);
        const appendBlankCell = () => week.appendChild(blankTemplate.content.firstElementChild.cloneNode(true));
        const appendDayCell = (day) => {
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = today.getDate() === day && today.getMonth() === currentMonth && today.getFullYear() === currentYear;
            const dayEvents = events.filter(e => {
                const eDate = (e.start_date || e.date || '').substring(0, 10);
                return eDate === dateStr;
            });
            const cell = cellTemplate.content.firstElementChild.cloneNode(true);
            cell.dataset.date = dateStr;
            cell.style.height = '90px';
            cell.style.verticalAlign = 'top';
            cell.style.cursor = 'pointer';
            if (isToday) cell.classList.add('bg-light', 'border-primary');
            const dayLabel = cell.querySelector('[data-field="day"]');
            if (dayLabel) {
                dayLabel.textContent = String(day);
                dayLabel.classList.toggle('text-primary', isToday);
            }
            const eventContainer = cell.querySelector('[data-field="events"]');
            dayEvents.slice(0, 3).forEach(evt => {
                const color = typeColors[evt.type || evt.event_type] || 'secondary';
                const event = eventTemplate.content.firstElementChild.cloneNode(true);
                event.textContent = evt.title || evt.name || 'Event';
                event.title = evt.title || evt.name || 'Event';
                event.dataset.eventId = String(evt.id ?? '');
                event.classList.add(`bg-${color}`);
                event.style.maxWidth = '100%';
                event.style.fontSize = '0.65rem';
                eventContainer?.appendChild(event);
            });
            if (dayEvents.length > 3) {
                const more = cell.querySelector('[data-field="more"]');
                if (more) {
                    more.textContent = `+${dayEvents.length - 3} more`;
                    more.classList.remove('d-none');
                }
            }
            week.appendChild(cell);
        };

        for (let i = 0; i < firstDay; i++) appendBlankCell();
        for (let day = 1; day <= daysInMonth; day++) {
            appendDayCell(day);
            if ((firstDay + day) % 7 === 0 && day < daysInMonth) {
                week = weekTemplate.content.firstElementChild.cloneNode(true);
                tbody.appendChild(week);
            }
        }
        const remainingCells = (7 - (firstDay + daysInMonth) % 7) % 7;
        for (let i = 0; i < remainingCells; i++) appendBlankCell();
    }

    function renderUpcomingEvents() {
        const list = document.getElementById('upcomingEventsList');
        if (!list) return;

        const now = new Date();
        now.setHours(0, 0, 0, 0);
        const upcoming = events
            .filter(e => new Date(e.start_date || e.date) >= now)
            .sort((a, b) => new Date(a.start_date || a.date) - new Date(b.start_date || b.date))
            .slice(0, 10);

        list.querySelectorAll('[data-upcoming-event]').forEach((item) => item.remove());
        const emptyState = document.getElementById('upcomingEventsEmpty');
        if (emptyState) emptyState.hidden = upcoming.length > 0;
        if (!upcoming.length) return;
        const template = document.getElementById('upcomingEventTemplate');
        if (!template) return;
        const fragment = document.createDocumentFragment();
        upcoming.forEach(evt => {
            const color = typeColors[evt.type || evt.event_type] || 'secondary';
            const date = new Date(evt.start_date || evt.date);
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            const startT = evt.start_time || (evt.start_at ? String(evt.start_at).substring(11, 16) : '') || '';
            const endT = evt.end_time || (evt.end_at ? String(evt.end_at).substring(11, 16) : '') || '';
            const endDate = evt.end_date && evt.end_date !== (evt.start_date || evt.date)
                ? new Date(evt.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
                : '';
            const rangeStr = [dateStr, endDate].filter(Boolean).join(' – ');
            const timeStr = [startT, endT].filter(Boolean).join(' – ');
            const item = template.content.firstElementChild.cloneNode(true);
            item.dataset.eventId = String(evt.id ?? '');
            const typeBadge = item.querySelector('[data-field="type"]');
            if (typeBadge) {
                typeBadge.textContent = evt.type || evt.event_type || 'event';
                typeBadge.classList.add(`bg-${color}`);
            }
            const title = item.querySelector('[data-field="title"]');
            if (title) title.textContent = evt.title || evt.name || 'Event';
            const datetime = item.querySelector('[data-field="datetime"]');
            if (datetime) datetime.textContent = `${rangeStr}${timeStr ? ` · ${timeStr}` : ''}`;
            const description = item.querySelector('[data-field="description"]');
            if (description && evt.description) {
                description.textContent = `${evt.description.substring(0, 60)}${evt.description.length > 60 ? '...' : ''}`;
                description.hidden = false;
            }
            fragment.appendChild(item);
        });
        list.appendChild(fragment);
    }

    function dayClick(dateStr) {
        openModal(null, dateStr);
    }

    function openModal(event = null, defaultDate = null) {
        document.getElementById('eventId').value = event?.id || '';
        document.getElementById('eventTitle').value = event?.title || event?.name || '';
        document.getElementById('eventStartDate').value = event?.start_date || defaultDate || '';
        document.getElementById('eventStartTime').value = event?.start_time || (event?.start_at ? String(event.start_at).substring(11, 16) : '') || '';
        document.getElementById('eventEndDate').value = event?.end_date || '';
        document.getElementById('eventEndTime').value = event?.end_time || (event?.end_at ? String(event.end_at).substring(11, 16) : '') || '';
        document.getElementById('eventType').value = event?.type || event?.event_type || 'academic';
        document.getElementById('eventColor').value = event?.color || 'primary';
        document.getElementById('eventDescription').value = event?.description || '';
        document.getElementById('eventVenue').value = event?.venue || event?.location || '';
        document.getElementById('eventModalLabel').textContent = event ? 'Edit Event' : 'Add Event';
        new bootstrap.Modal(document.getElementById('eventModal')).show();
    }

    async function save() {
        const id = document.getElementById('eventId').value;
        const payload = {
            name: document.getElementById('eventTitle').value,
            start_date: document.getElementById('eventStartDate').value,
            start_time: document.getElementById('eventStartTime').value || null,
            end_date: document.getElementById('eventEndDate').value || null,
            end_time: document.getElementById('eventEndTime').value || null,
            type: document.getElementById('eventType').value,
            description: document.getElementById('eventDescription').value || null,
            location: document.getElementById('eventVenue').value || null,
            status: 'upcoming'
        };
        if (!payload.name || !payload.start_date) {
            showNotification('Please fill required fields', 'error');
            return;
        }
        try {
            if (id) {
                await window.API.schedules.updateEvent(id, payload);
            } else {
                await window.API.schedules.createEvent(payload);
            }
            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
            showNotification(id ? 'Event updated' : 'Event created', 'success');
            await loadData();
        } catch (e) {
            showNotification(e.message || 'Failed to save event', 'error');
        }
    }

    async function viewEvent(id) {
        const evt = events.find((e) => String(e.id) === String(id));
        if (evt) {
            openModal(evt);
        } else {
            showNotification('Failed to load event', 'error');
        }
    }

    function navigateMonth(dir) {
        currentMonth += dir;
        if (currentMonth > 11) { currentMonth = 0; currentYear++; }
        if (currentMonth < 0) { currentMonth = 11; currentYear--; }
        loadData();
    }

    function showNotification(message, type) { window.showNotification(message, type); }

    function initYearDropdown() {
        const select = document.getElementById('yearFilter');
        if (!select) return;
        const now = new Date().getFullYear();
        const template = document.getElementById('calendarYearOptionTemplate');
        if (!template) return;
        for (let y = now - 2; y <= now + 3; y++) {
            const opt = template.content.firstElementChild.cloneNode(true);
            opt.value = y;
            opt.textContent = y;
            if (y === currentYear) opt.selected = true;
            select.appendChild(opt);
        }
    }

    function attachListeners() {
        document.getElementById('calendarBody')?.addEventListener('click', (event) => {
            const eventButton = event.target.closest('[data-event-id]');
            if (eventButton) return viewEvent(eventButton.dataset.eventId);
            const cell = event.target.closest('[data-date]');
            if (cell) dayClick(cell.dataset.date);
        });
        document.getElementById('upcomingEventsList')?.addEventListener('click', (event) => {
            const item = event.target.closest('[data-upcoming-event]');
            if (item) viewEvent(item.dataset.eventId);
        });
        document.getElementById('addEventBtn')?.addEventListener('click', () => openModal());
        document.getElementById('saveEventBtn')?.addEventListener('click', () => save());
        document.getElementById('prevMonthBtn')?.addEventListener('click', () => navigateMonth(-1));
        document.getElementById('nextMonthBtn')?.addEventListener('click', () => navigateMonth(1));
        document.getElementById('navigateCalendarBtn')?.addEventListener('click', () => {
            currentMonth = parseInt(document.getElementById('monthFilter').value);
            currentYear = parseInt(document.getElementById('yearFilter').value);
            loadData();
        });
        document.getElementById('eventTypeFilter')?.addEventListener('change', () => loadData());
        document.getElementById('searchEvents')?.addEventListener('keyup', () => {
            clearTimeout(window._calSearchTimeout);
            window._calSearchTimeout = setTimeout(() => loadData(), 300);
        });
        document.getElementById('exportCalendarBtn')?.addEventListener('click', () => {
            window.open((window.APP_BASE || "") + `/api/?route=academic/calendar/export&format=csv&year=${currentYear}`, '_blank');
        });

        // Set default month
        const monthSelect = document.getElementById('monthFilter');
        if (monthSelect) monthSelect.value = currentMonth;
    }

    async function init() {
        initYearDropdown();
        attachListeners();
        await loadData();
    }

    return { init, refresh: loadData, dayClick, viewEvent };
})();

document.addEventListener('DOMContentLoaded', () => ViewCalendarController.init());

window.ViewCalendarController = ViewCalendarController;
