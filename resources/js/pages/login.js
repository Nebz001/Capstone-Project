import { ALERT_BUTTON_CLASSES, showAlert, showSuccessAlert } from '../components/alerts';

export const initLoginPage = () => {
	const form = document.getElementById('student-login-form');
	if (!form) return;

	const successMessageSource = document.getElementById('login-success-alert-data');
	const errorMessageSource = document.getElementById('login-error-alert-data');
	const successMessage = successMessageSource?.dataset.message?.trim();
	const errorMessage = errorMessageSource?.dataset.message?.trim();

	if (successMessage) {
		window.requestAnimationFrame(() => {
			showSuccessAlert('Login Successful', successMessage || 'Welcome back!', {
				confirmButtonText: 'Continue',
			});
		});
	} else if (errorMessage) {
		window.requestAnimationFrame(() => {
			showAlert({
				icon: 'error',
				title: 'Login Failed',
				text: errorMessage || 'Invalid email or password.',
				confirmButtonText: 'OK',
				customClass: {
					confirmButton: ALERT_BUTTON_CLASSES.redConfirm,
				},
			});
		});
	}

	const passwordToggles = Array.from(document.querySelectorAll('[data-password-toggle]'));

	const updatePasswordToggleVisual = (button, input) => {
		if (!(button instanceof HTMLButtonElement) || !(input instanceof HTMLInputElement)) return;

		const isHidden = input.type === 'password';
		const openIcon = button.querySelector('[data-icon-eye-open]');
		const closedIcon = button.querySelector('[data-icon-eye-closed]');

		if (openIcon) {
			openIcon.classList.toggle('hidden', !isHidden);
		}

		if (closedIcon) {
			closedIcon.classList.toggle('hidden', isHidden);
		}

		button.setAttribute('aria-label', isHidden ? 'Show password' : 'Hide password');
		button.setAttribute('title', isHidden ? 'Show password' : 'Hide password');
		button.setAttribute('aria-pressed', String(!isHidden));
	};

	passwordToggles.forEach((toggleButton) => {
		if (!(toggleButton instanceof HTMLButtonElement)) return;

		const targetId = toggleButton.dataset.passwordTarget;
		if (!targetId) return;

		const targetInput = document.getElementById(targetId);
		if (!(targetInput instanceof HTMLInputElement)) return;

		updatePasswordToggleVisual(toggleButton, targetInput);

		toggleButton.addEventListener('click', () => {
			const isHidden = targetInput.type === 'password';
			targetInput.type = isHidden ? 'text' : 'password';
			updatePasswordToggleVisual(toggleButton, targetInput);
		});
	});
};
