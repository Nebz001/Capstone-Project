import './bootstrap';

import { initActivityCalendarSubmissionPage } from './pages/activity-calendar-submission';
import { initLoginPage } from './pages/login';
import { initRegisterPage } from './pages/register';
import { initRegisterOrganizationPage } from './pages/register-organization';
import { initOrganizationDashboard } from './pages/organization-dashboard';
import { initOrganizationApplicationAlerts } from './pages/organization-applications';
import { initAdminCalendarPage } from './pages/admin-calendar';

initActivityCalendarSubmissionPage();
initLoginPage();
initRegisterPage();
initRegisterOrganizationPage();
initOrganizationDashboard();
initOrganizationApplicationAlerts();
initAdminCalendarPage();
