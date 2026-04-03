import './bootstrap';

import { initActivityCalendarSubmissionPage } from './pages/activity-calendar-submission';
import { initLoginPage } from './pages/login';
import { initRegisterPage } from './pages/register';
import { initOrganizationDashboard } from './pages/organization-dashboard';
import { initOrganizationApplicationAlerts } from './pages/organization-applications';
import { initAdminCalendarPage } from './pages/admin-calendar';

initActivityCalendarSubmissionPage();
initLoginPage();
initRegisterPage();
initOrganizationDashboard();
initOrganizationApplicationAlerts();
initAdminCalendarPage();
