import { showSuccessAlert } from '../components/alerts';

export const initRegisterPage = () => {
	const form = document.getElementById('organization-application-form');
	if (!form) return;

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		showSuccessAlert(
			'Application Submitted',
			'Your organization application has been submitted successfully.',
		);
	});

	const applicationInputs = document.querySelectorAll('input[name="application_for"]');
	const note = document.getElementById('requirements-note');
	const newList = document.getElementById('requirements-new');
	const renewalList = document.getElementById('requirements-renewal');

	const setEnabled = (container, enabled) => {
		if (!container) return;
		container.querySelectorAll('input, textarea, select, button').forEach((el) => {
			el.disabled = !enabled;
		});
	};

	const clearInputs = (container) => {
		if (!container) return;
		container.querySelectorAll('input[type="checkbox"]').forEach((el) => {
			el.checked = false;
		});
		container.querySelectorAll('input[type="text"]').forEach((el) => {
			el.value = '';
		});
	};

	const updateRequirements = () => {
		const selected = document.querySelector('input[name="application_for"]:checked')?.value;

		if (!selected) {
			note?.classList.remove('hidden');
			newList?.classList.add('hidden');
			renewalList?.classList.add('hidden');
			setEnabled(newList, false);
			setEnabled(renewalList, false);
			return;
		}

		note?.classList.add('hidden');

		if (selected === 'new') {
			newList?.classList.remove('hidden');
			renewalList?.classList.add('hidden');
			setEnabled(newList, true);
			setEnabled(renewalList, false);
			clearInputs(renewalList);
			return;
		}

		if (selected === 'renewal') {
			renewalList?.classList.remove('hidden');
			newList?.classList.add('hidden');
			setEnabled(renewalList, true);
			setEnabled(newList, false);
			clearInputs(newList);
		}
	};

	applicationInputs.forEach((input) => {
		input.addEventListener('change', updateRequirements);
	});

	setEnabled(newList, false);
	setEnabled(renewalList, false);
	updateRequirements();
};
