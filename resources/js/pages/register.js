export const initRegisterPage = () => {
	const form = document.getElementById('student-register-form');
	if (!form) return;

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
	const passwordToggles = Array.from(document.querySelectorAll('[data-password-toggle]'));

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
		toggleButton.addEventListener('click', () => {
			if (!(toggleButton instanceof HTMLButtonElement)) return;
			const targetId = toggleButton.dataset.passwordTarget;
			if (!targetId) return;

			const targetInput = document.getElementById(targetId);
			if (!(targetInput instanceof HTMLInputElement)) return;

			const isHidden = targetInput.type === 'password';
			targetInput.type = isHidden ? 'text' : 'password';
			toggleButton.textContent = isHidden ? 'Hide' : 'Show';
		});
	});

	if (passwordInput instanceof HTMLInputElement && confirmInput instanceof HTMLInputElement) {
		passwordInput.addEventListener('input', updatePasswordMatch);
		confirmInput.addEventListener('input', updatePasswordMatch);
		updatePasswordMatch();
	}
};
