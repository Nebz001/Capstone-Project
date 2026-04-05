/**
 * Guest layout navbar: hamburger auth menu on small screens.
 */
export function initGuestNavbar() {
    const root = document.getElementById("guest-navbar-auth");
    if (!root) {
        return;
    }

    const toggle = root.querySelector("[data-guest-nav-toggle]");
    const panel = root.querySelector("[data-guest-nav-panel]");
    if (!toggle || !panel) {
        return;
    }

    const openClass = "guest-nav-auth-panel--open";

    const setOpen = (open) => {
        panel.classList.toggle(openClass, open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
    };

    toggle.addEventListener("click", (e) => {
        e.stopPropagation();
        setOpen(!panel.classList.contains(openClass));
    });

    document.addEventListener("click", (e) => {
        if (!root.contains(e.target)) {
            setOpen(false);
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            setOpen(false);
        }
    });

    panel.querySelectorAll("a").forEach((a) => {
        a.addEventListener("click", () => setOpen(false));
    });
}
