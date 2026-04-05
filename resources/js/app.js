import './bootstrap';

import { initGuestNavbar } from './components/guest-navbar';
import { initLoginAnnouncementsModal } from './components/login-announcements-modal';
import { initOrganizationNavbar } from './components/organization-navbar';
import { initActivityCalendarSubmissionPage } from './pages/activity-calendar-submission';
import { initLoginPage } from './pages/login';
import { initRegisterPage } from './pages/register';
import { initOrganizationDashboard } from './pages/organization-dashboard';
import { initOrganizationApplicationAlerts } from './pages/organization-applications';
import { initAdminCalendarPage } from './pages/admin-calendar';

initGuestNavbar();
initLoginAnnouncementsModal();
initOrganizationNavbar();
initActivityCalendarSubmissionPage();
initLoginPage();
initRegisterPage();
initOrganizationDashboard();
initOrganizationApplicationAlerts();
initAdminCalendarPage();
