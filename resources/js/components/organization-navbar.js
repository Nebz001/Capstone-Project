/**
 * Organization navbar: one dropdown open at a time; click outside closes panels.
 */
export function initOrganizationNavbar() {
    const nav = document.getElementById('organization-navbar');
    if (! nav) {
        return;
    }

    const panels = [...nav.querySelectorAll('details[data-org-navbar-panel]')];

    panels.forEach((details) => {
        details.addEventListener('toggle', () => {
            const summary = details.querySelector('summary');
            if (summary) {
                summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
            }
            if (details.open) {
                panels.forEach((other) => {
                    if (other !== details) {
                        other.open = false;
                    }
                });
            }
        });
    });

    document.addEventListener('click', (e) => {
        if (nav.contains(e.target)) {
            return;
        }
        panels.forEach((d) => {
            d.open = false;
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') {
            return;
        }
        panels.forEach((d) => {
            d.open = false;
        });
    });

    nav.querySelectorAll('[data-org-announcements-close]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const details = btn.closest('details[data-org-navbar-panel]');
            if (details) {
                details.open = false;
            }
        });
    });
}
