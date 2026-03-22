import { showSuccessAlert } from '../components/alerts';

export const initRegisterPage = () => {
	const form = document.getElementById('student-register-form');
	if (!form) return;

	const successMessageSource = document.getElementById('register-success-alert-data');
	const successMessage = successMessageSource?.dataset.message?.trim();

	if (successMessage) {
		window.requestAnimationFrame(() => {
			showSuccessAlert(
				'Account Created',
				successMessage || 'Your account has been created successfully.',
				{ confirmButtonText: 'Continue' },
			);
		});
	}

	const schoolIdHiddenInput = document.getElementById('school_id');
	const schoolIdSegments = Array.from(
		document.querySelectorAll('[data-school-id-segment]'),
	);

	const updateSchoolIdValue = () => {
		if (!schoolIdHiddenInput || schoolIdSegments.length !== 10) return;
		const digits = schoolIdSegments.map((segment) => segment.value || '');
		schoolIdHiddenInput.value = `${digits.slice(0, 4).join('')}-${digits.slice(4).join('')}`;
	};

	schoolIdSegments.forEach((segment, index) => {
		segment.addEventListener('input', (event) => {
			const input = event.currentTarget;
			if (!(input instanceof HTMLInputElement)) return;

			input.value = input.value.replace(/\D/g, '').slice(-1);
			updateSchoolIdValue();

			if (input.value && index < schoolIdSegments.length - 1) {
				schoolIdSegments[index + 1].focus();
			}
		});

		segment.addEventListener('keydown', (event) => {
			const input = event.currentTarget;
			if (!(input instanceof HTMLInputElement)) return;

			if (event.key === 'Backspace' && !input.value && index > 0) {
				schoolIdSegments[index - 1].focus();
				schoolIdSegments[index - 1].select();
			}
		});

		segment.addEventListener('paste', (event) => {
			event.preventDefault();
			const pastedText = event.clipboardData?.getData('text') ?? '';
			const digits = pastedText.replace(/\D/g, '').slice(0, schoolIdSegments.length - index);

			if (!digits) return;

			digits.split('').forEach((digit, digitIndex) => {
				const target = schoolIdSegments[index + digitIndex];
				if (target) target.value = digit;
			});

			updateSchoolIdValue();
			const nextIndex = Math.min(index + digits.length, schoolIdSegments.length - 1);
			schoolIdSegments[nextIndex].focus();
		});
	});

	updateSchoolIdValue();

	const passwordInput = document.getElementById('password');
	const confirmInput = document.getElementById('password_confirmation');
	const matchStatus = document.getElementById('password-match-status');
	const passwordRequirementsError = document.getElementById('password-requirements-error');
	const passwordToggles = Array.from(document.querySelectorAll('[data-password-toggle]'));
	let passwordSubmitAttempted = false;
	const passwordRuleItems = {
		length: document.querySelector('[data-password-rule-item="length"]'),
		uppercase: document.querySelector('[data-password-rule-item="uppercase"]'),
		lowercase: document.querySelector('[data-password-rule-item="lowercase"]'),
		number: document.querySelector('[data-password-rule-item="number"]'),
	};

	const evaluatePasswordRules = (value) => {
		return {
			length: value.length >= 8,
			uppercase: /[A-Z]/.test(value),
			lowercase: /[a-z]/.test(value),
			number: /\d/.test(value),
		};
	};

	const updatePasswordRules = () => {
		if (!(passwordInput instanceof HTMLInputElement)) return;

		const ruleState = evaluatePasswordRules(passwordInput.value);
		const allRulesMet = Object.values(ruleState).every(Boolean);

		Object.entries(passwordRuleItems).forEach(([ruleName, ruleItem]) => {
			if (!(ruleItem instanceof HTMLElement)) return;

			const isMet = Boolean(ruleState[ruleName]);
			const indicator = ruleItem.querySelector('[data-password-rule-indicator]');

			ruleItem.classList.toggle('text-slate-500', !isMet);
			ruleItem.classList.toggle('text-emerald-600', isMet);
			ruleItem.classList.toggle('font-medium', isMet);

			if (indicator instanceof HTMLElement) {
				indicator.classList.toggle('bg-slate-300', !isMet);
				indicator.classList.toggle('bg-emerald-500', isMet);
			}
		});

		passwordInput.classList.toggle('border-rose-300', !allRulesMet);
		passwordInput.classList.toggle('focus:border-rose-500', !allRulesMet);
		passwordInput.classList.toggle('focus:ring-rose-500/20', !allRulesMet);
		passwordInput.classList.toggle('border-slate-300', allRulesMet);
		passwordInput.classList.toggle('focus:border-sky-500', allRulesMet);
		passwordInput.classList.toggle('focus:ring-sky-500/15', allRulesMet);
		passwordInput.setAttribute('aria-invalid', String(!allRulesMet));

		if (passwordRequirementsError instanceof HTMLElement) {
			const showError = passwordSubmitAttempted && !allRulesMet;
			passwordRequirementsError.classList.toggle('hidden', !showError);
		}

		return allRulesMet;
	};

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

	const updatePasswordMatch = () => {
		if (
			!(passwordInput instanceof HTMLInputElement) ||
			!(confirmInput instanceof HTMLInputElement) ||
			!(matchStatus instanceof HTMLElement)
		) {
			return;
		}

		if (!passwordInput.value && !confirmInput.value) {
			matchStatus.textContent = 'Enter matching passwords to continue.';
			matchStatus.className = 'mt-2 text-xs text-slate-500';
			return;
		}

		if (passwordInput.value === confirmInput.value) {
			matchStatus.textContent = 'Passwords match.';
			matchStatus.className = 'mt-2 text-xs font-medium text-emerald-600';
			return;
		}

		matchStatus.textContent = 'Passwords do not match yet.';
		matchStatus.className = 'mt-2 text-xs font-medium text-rose-600';
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

	if (passwordInput instanceof HTMLInputElement && confirmInput instanceof HTMLInputElement) {
		passwordInput.addEventListener('input', updatePasswordRules);
		passwordInput.addEventListener('input', updatePasswordMatch);
		confirmInput.addEventListener('input', updatePasswordMatch);
		updatePasswordRules();
		updatePasswordMatch();
	}

	form.addEventListener('submit', (event) => {
		if (!(passwordInput instanceof HTMLInputElement)) return;

		passwordSubmitAttempted = true;
		const allRulesMet = updatePasswordRules();

		if (allRulesMet) return;

		event.preventDefault();
		passwordInput.focus({ preventScroll: true });
		passwordInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
	});
};
