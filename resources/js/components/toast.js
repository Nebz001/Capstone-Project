let toastEl;
let toastMessageEl;
let toastDotEl;
let toastCloseEl;

let toastTimer = null;
let toastHideTimer = null;
let initialized = false;

const VARIANTS = {
	success: {
		borderRemove: ['border-red-200', 'border-amber-200'],
		dotRemove: ['bg-red-500', 'bg-amber-500'],
		borderAdd: ['border-green-200'],
		dotAdd: ['bg-green-500'],
	},
	warning: {
		borderRemove: ['border-green-200', 'border-red-200'],
		dotRemove: ['bg-green-500', 'bg-red-500'],
		borderAdd: ['border-amber-200'],
		dotAdd: ['bg-amber-500'],
	},
	error: {
		borderRemove: ['border-green-200', 'border-amber-200'],
		dotRemove: ['bg-green-500', 'bg-amber-500'],
		borderAdd: ['border-red-200'],
		dotAdd: ['bg-red-500'],
	},
};

const showToastClasses = () => {
	toastEl.classList.remove('invisible', 'opacity-0', 'translate-y-2');
	toastEl.classList.add('opacity-100', 'translate-y-0');
};

export const initToast = () => {
	toastEl = document.getElementById('toast');
	toastMessageEl = document.getElementById('toast-message');
	toastDotEl = document.getElementById('toast-dot');
	toastCloseEl = document.getElementById('toast-close');

	if (!toastEl || !toastMessageEl || !toastDotEl || !toastCloseEl) {
		initialized = false;
		return false;
	}

	if (!toastCloseEl.dataset.toastBound) {
		toastCloseEl.dataset.toastBound = 'true';
		toastCloseEl.addEventListener('click', () => {
			hideToast();
			if (toastTimer) window.clearTimeout(toastTimer);
		});
	}

	initialized = true;
	return true;
};

export const hideToast = () => {
	if (!initialized) return;

	toastEl.classList.remove('opacity-100', 'translate-y-0');
	toastEl.classList.add('opacity-0', 'translate-y-2');

	if (toastHideTimer) window.clearTimeout(toastHideTimer);
	toastHideTimer = window.setTimeout(() => {
		toastEl.classList.add('invisible');
	}, 210);
};

export const showToast = (message, options = {}) => {
	if (!initialized) return;

	const { variant = 'success', duration = 2600 } = options;
	const variantConfig = VARIANTS[variant] ?? VARIANTS.success;

	toastMessageEl.textContent = message;

	toastEl.classList.remove(...variantConfig.borderRemove);
	toastDotEl.classList.remove(...variantConfig.dotRemove);
	toastEl.classList.add(...variantConfig.borderAdd);
	toastDotEl.classList.add(...variantConfig.dotAdd);

	toastEl.classList.remove('invisible');
	if (toastHideTimer) window.clearTimeout(toastHideTimer);
	if (toastTimer) window.clearTimeout(toastTimer);

	window.requestAnimationFrame(() => {
		showToastClasses();
	});

	toastTimer = window.setTimeout(() => {
		hideToast();
	}, duration);
};
