import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

export function initAdminCalendarPage() {
    const calendarEl = document.getElementById('admin-activity-calendar');
    if (!calendarEl) return;

    const jsonEl = document.getElementById('admin-calendar-events-data');
    let events = [];
    if (jsonEl) {
        try { events = JSON.parse(jsonEl.textContent); } catch { events = []; }
    }

    const statusColors = {
        PENDING: { bg: '#fef3c7', border: '#fbbf24', text: '#78350f', badge: 'bg-amber-100 text-amber-800 border border-amber-200' },
        UNDER_REVIEW: { bg: '#dbeafe', border: '#60a5fa', text: '#1e3a8a', badge: 'bg-blue-100 text-blue-700 border border-blue-200' },
        REVIEWED: { bg: '#dbeafe', border: '#60a5fa', text: '#1e3a8a', badge: 'bg-blue-100 text-blue-700 border border-blue-200' },
        APPROVED: { bg: '#d1fae5', border: '#34d399', text: '#065f46', badge: 'bg-emerald-100 text-emerald-700 border border-emerald-200' },
        REJECTED: { bg: '#ffe4e6', border: '#fb7185', text: '#9f1239', badge: 'bg-rose-100 text-rose-700 border border-rose-200' },
        REVISION: { bg: '#ffedd5', border: '#fb923c', text: '#9a3412', badge: 'bg-orange-100 text-orange-700 border border-orange-200' },
        REVISION_REQUIRED: { bg: '#ffedd5', border: '#fb923c', text: '#9a3412', badge: 'bg-orange-100 text-orange-700 border border-orange-200' },
    };

    const mapped = events.map((event) => {
        const colors = statusColors[event.status] || statusColors.PENDING;
        return {
            title: event.title,
            start: event.start,
            end: event.end || null,
            backgroundColor: colors.bg,
            borderColor: colors.border,
            textColor: colors.text,
            extendedProps: {
                ...event,
                badgeClass: colors.badge,
            },
        };
    });

    const drawer = document.getElementById('admin-calendar-drawer');
    const backdrop = document.getElementById('admin-calendar-drawer-backdrop');
    const closeButton = document.getElementById('admin-calendar-drawer-close');
    const title = document.getElementById('admin-calendar-drawer-title');
    const status = document.getElementById('admin-calendar-status');
    const org = document.getElementById('admin-calendar-org');
    const submittedBy = document.getElementById('admin-calendar-submitted-by');
    const date = document.getElementById('admin-calendar-date');
    const time = document.getElementById('admin-calendar-time');
    const venue = document.getElementById('admin-calendar-venue');
    const submissionType = document.getElementById('admin-calendar-submission-type');
    const submissionDate = document.getElementById('admin-calendar-submission-date');
    const detailLink = document.getElementById('admin-calendar-view-submission');

    const closeDrawer = () => {
        if (!drawer) return;
        drawer.classList.add('hidden');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    };

    const openDrawer = (event) => {
        if (!drawer) return;

        const props = event.extendedProps || {};
        title.textContent = props.title || event.title || 'Submission Event';
        status.textContent = (props.status || 'PENDING').replaceAll('_', ' ');
        status.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${props.badgeClass || ''}`;

        org.textContent = props.organization_name || 'N/A';
        submittedBy.textContent = props.submitted_by || 'N/A';
        date.textContent = props.date || 'N/A';
        time.textContent = props.time || 'N/A';
        venue.textContent = props.venue || 'N/A';
        submissionType.textContent = props.submission_type || 'N/A';
        submissionDate.textContent = props.submission_date || 'N/A';
        detailLink.href = props.detail_route || '#';

        drawer.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    backdrop?.addEventListener('click', closeDrawer);
    closeButton?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDrawer();
    });

    const calendar = new Calendar(calendarEl, {
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
            openDrawer(info.event);
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

