import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

export function initOrganizationDashboard() {
    const el = document.getElementById('activity-calendar');
    if (!el) return;

    const statusColors = {
        approved:  { bg: '#d1fae5', border: '#34d399', text: '#065f46', label: 'Approved' },
        pending:   { bg: '#fef3c7', border: '#fbbf24', text: '#78350f', label: 'Pending Approval' },
        scheduled: { bg: '#dbeafe', border: '#60a5fa', text: '#1e3a8a', label: 'Scheduled' },
    };

    const jsonEl = document.getElementById('calendar-events-data');
    let events = [];
    if (jsonEl) {
        try { events = JSON.parse(jsonEl.textContent); } catch { events = []; }
    }

    const mapped = events.map(e => {
        const colors = statusColors[e.status] || statusColors.scheduled;
        return {
            title: e.title,
            start: e.start,
            end: e.end || null,
            backgroundColor: colors.bg,
            borderColor: colors.border,
            textColor: colors.text,
            extendedProps: {
                status: e.status,
                time: e.time || null,
                venue: e.venue || null,
            },
        };
    });

    let popoverEl = null;

    function removePopover() {
        if (popoverEl) {
            popoverEl.remove();
            popoverEl = null;
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function showPopover(info) {
        removePopover();

        const { title, start, end, extendedProps } = info.event;
        const colors = statusColors[extendedProps.status] || statusColors.scheduled;

        const startStr = formatDate(start?.toISOString().split('T')[0]);
        let dateDisplay = startStr;
        if (end) {
            const endAdj = new Date(end);
            endAdj.setDate(endAdj.getDate() - 1);
            const endStr = formatDate(endAdj.toISOString().split('T')[0]);
            if (endStr !== startStr) dateDisplay = `${startStr} — ${endStr}`;
        }

        popoverEl = document.createElement('div');
        popoverEl.className = 'fc-event-popover';
        popoverEl.innerHTML = `
            <div class="fc-event-popover-inner">
                <div class="fc-event-popover-header" style="background:${colors.bg}; border-color:${colors.border};">
                    <span class="fc-event-popover-badge" style="background:${colors.border};"></span>
                    <span class="fc-event-popover-status" style="color:${colors.text};">${colors.label}</span>
                </div>
                <div class="fc-event-popover-body">
                    <p class="fc-event-popover-title">${title}</p>
                    <div class="fc-event-popover-details">
                        <div class="fc-event-popover-row">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                            <span>${dateDisplay}</span>
                        </div>
                        ${extendedProps.time ? `
                        <div class="fc-event-popover-row">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            <span>${extendedProps.time}</span>
                        </div>` : ''}
                        ${extendedProps.venue ? `
                        <div class="fc-event-popover-row">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                            <span>${extendedProps.venue}</span>
                        </div>` : ''}
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(popoverEl);

        const rect = info.el.getBoundingClientRect();
        const popRect = popoverEl.getBoundingClientRect();
        let top = rect.bottom + 6 + window.scrollY;
        let left = rect.left + (rect.width / 2) - (popRect.width / 2) + window.scrollX;

        if (left + popRect.width > window.innerWidth - 12) left = window.innerWidth - popRect.width - 12;
        if (left < 12) left = 12;
        if (top + popRect.height > window.innerHeight + window.scrollY - 12) {
            top = rect.top - popRect.height - 6 + window.scrollY;
        }

        popoverEl.style.top = `${top}px`;
        popoverEl.style.left = `${left}px`;
        popoverEl.classList.add('fc-event-popover-visible');
    }

    document.addEventListener('click', (e) => {
        if (popoverEl && !popoverEl.contains(e.target) && !e.target.closest('.fc-daygrid-event')) {
            removePopover();
        }
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') removePopover(); });

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin],
        initialView: 'dayGridMonth',
        locale: 'en',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: '',
        },
        events: mapped,
        editable: false,
        selectable: false,
        eventStartEditable: false,
        eventDurationEditable: false,
        droppable: false,
        dayMaxEvents: 3,
        fixedWeekCount: false,
        eventDisplay: 'block',
        eventClick(info) {
            info.jsEvent.preventDefault();
            info.jsEvent.stopPropagation();
            showPopover(info);
        },
        eventMouseEnter(info) {
            info.el.style.filter = 'brightness(0.95)';
            info.el.style.cursor = 'pointer';
        },
        eventMouseLeave(info) {
            info.el.style.filter = '';
            info.el.style.cursor = 'default';
        },
    });

    calendar.render();
}
