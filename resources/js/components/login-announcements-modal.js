/**
 * Login announcement modal: shows after sign-in when the server injects payload (see layouts + View composer).
 */
export function initLoginAnnouncementsModal() {
    const payloadEl = document.getElementById('login-announcements-payload');
    const dismissUrlEl = document.getElementById('login-announcements-dismiss-url');
    const modal = document.getElementById('login-announcements-modal');
    if (!payloadEl || !dismissUrlEl || !modal) {
        return;
    }

    let items;
    try {
        items = JSON.parse(payloadEl.textContent || '[]');
    } catch {
        return;
    }

    if (!Array.isArray(items) || items.length === 0) {
        return;
    }

    let dismissUrl;
    try {
        dismissUrl = JSON.parse(dismissUrlEl.textContent || '""');
    } catch {
        return;
    }

    const titleEl = document.getElementById('login-announcements-title');
    const counterEl = document.getElementById('login-announcements-counter');
    const bodyEl = document.getElementById('login-announcements-body');
    const imageWrap = document.getElementById('login-announcements-image-wrap');
    const imageEl = document.getElementById('login-announcements-image');
    const linkWrap = document.getElementById('login-announcements-link-wrap');
    const linkEl = document.getElementById('login-announcements-link');
    const nav = document.getElementById('login-announcements-nav');
    const prevBtn = document.getElementById('login-announcements-prev');
    const nextBtn = document.getElementById('login-announcements-next');
    const closeBtn = document.getElementById('login-announcements-close');

    if (!titleEl || !counterEl || !bodyEl || !imageWrap || !imageEl || !linkWrap || !linkEl || !nav || !prevBtn || !nextBtn || !closeBtn) {
        return;
    }

    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let index = 0;

    const showNav = items.length > 1;
    if (showNav) {
        nav.classList.remove('hidden');
    }

    const render = () => {
        const item = items[index];
        if (!item) {
            return;
        }

        titleEl.textContent = item.title || 'Announcement';
        counterEl.textContent = `${index + 1} / ${items.length}`;

        if (item.body) {
            bodyEl.textContent = item.body;
            bodyEl.classList.remove('hidden');
        } else {
            bodyEl.textContent = '';
            bodyEl.classList.add('hidden');
        }

        if (item.image_url) {
            imageEl.src = item.image_url;
            imageEl.alt = item.title || '';
            imageWrap.classList.remove('hidden');
        } else {
            imageEl.removeAttribute('src');
            imageWrap.classList.add('hidden');
        }

        if (item.link_url) {
            linkEl.href = item.link_url;
            linkEl.textContent = item.link_label || 'Open link';
            linkWrap.classList.remove('hidden');
        } else {
            linkWrap.classList.add('hidden');
            linkEl.removeAttribute('href');
        }

        prevBtn.disabled = index <= 0;
        nextBtn.disabled = index >= items.length - 1;
    };

    const open = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        render();
    };

    const closeVisual = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    const dismissOnServer = async () => {
        const ids = items.map((i) => i.id).filter((id) => typeof id === 'number');
        if (ids.length === 0 || !dismissUrl) {
            return;
        }
        try {
            await fetch(dismissUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ announcement_ids: ids }),
            });
        } catch {
            // Best-effort; modal is already closed for UX.
        }
    };

    const close = () => {
        closeVisual();
        void dismissOnServer();
    };

    prevBtn.addEventListener('click', () => {
        if (index > 0) {
            index -= 1;
            render();
        }
    });

    nextBtn.addEventListener('click', () => {
        if (index < items.length - 1) {
            index += 1;
            render();
        }
    });

    closeBtn.addEventListener('click', close);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (!modal.classList.contains('flex')) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    });

    open();
}
