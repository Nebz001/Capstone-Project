import { showConfirmAlert, showSuccessAlert, showWarningAlert } from '../components/alerts';
import { initToast, showToast } from '../components/toast';

const escapeHtml = (value) => {
	return String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');
};

export const initActivityCalendarSubmissionPage = () => {
	const mainForm = document.getElementById('activity-calendar-form');
	if (!mainForm) return;

	if (mainForm.dataset.officerValidationPending === 'true') {
		return;
	}

	initToast();

	const addButton = document.getElementById('add-activity');
	const cancelEditButton = document.getElementById('cancel-edit');
	const entryTitle = document.getElementById('activity-entry-title');
	const submitButton = document.getElementById('submit-activity-calendar');

	const entry = {
		date: document.getElementById('activity_date'),
		name: document.getElementById('activity_name'),
		sdg: document.getElementById('activity_sdg'),
		venue: document.getElementById('activity_venue'),
		participantProgram: document.getElementById('activity_participant_program'),
		budget: document.getElementById('activity_budget'),
	};

	const previewBody = document.getElementById('activities-preview-body');
	const emptyState = document.getElementById('activities-empty-state');
	const addedSection = document.getElementById('added-activities-section');
	const hiddenJson = document.getElementById('activities_json');
	const hiddenInputs = document.getElementById('activities-hidden-inputs');

	if (!addButton || !cancelEditButton || !entryTitle || !submitButton) return;
	if (!previewBody || !emptyState || !addedSection || !hiddenJson || !hiddenInputs) return;
	if (!entry.date || !entry.name || !entry.sdg || !entry.venue || !entry.participantProgram || !entry.budget) return;

	const activities = [];
	let editingIndex = null;

	const updateHidden = () => {
		hiddenJson.value = JSON.stringify(activities);
		hiddenInputs.innerHTML = activities
			.map((activity, index) => {
				const prefix = `activities[${index}]`;
				return [
					`<input type="hidden" name="${prefix}[date]" value="${escapeHtml(activity.date)}" />`,
					`<input type="hidden" name="${prefix}[name]" value="${escapeHtml(activity.name)}" />`,
					`<input type="hidden" name="${prefix}[sdg]" value="${escapeHtml(activity.sdg)}" />`,
					`<input type="hidden" name="${prefix}[venue]" value="${escapeHtml(activity.venue)}" />`,
					`<input type="hidden" name="${prefix}[participant_program]" value="${escapeHtml(activity.participantProgram)}" />`,
					`<input type="hidden" name="${prefix}[budget]" value="${escapeHtml(activity.budget)}" />`,
				].join('');
			})
			.join('');
	};

	const render = () => {
		const existingRows = previewBody.querySelectorAll('tr[data-index]');
		existingRows.forEach((row) => row.remove());

		if (activities.length === 0) {
			emptyState.classList.remove('hidden');
			updateHidden();
			return;
		}

		emptyState.classList.add('hidden');
		activities.forEach((activity, index) => {
			const row = document.createElement('tr');
			row.className = 'align-top';
			row.dataset.index = String(index);

			row.innerHTML = `
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.date || '—')}</td>
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.name || '—')}</td>
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.sdg || '—')}</td>
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.venue || '—')}</td>
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.participantProgram || '—')}</td>
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml(activity.budget || '—')}</td>
				<td class="px-5 py-3.5">
					<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">Pending review</span>
				</td>
				<td class="px-5 py-3.5 text-sm text-slate-500">For admin use</td>
				<td class="px-5 py-3.5">
					<div class="flex flex-col gap-2 sm:flex-row sm:items-center">
						<button type="button" data-action="edit" class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-sky-500/15">Edit</button>
						<button type="button" data-action="delete" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-sky-500/15">Delete</button>
					</div>
				</td>
			`;

			previewBody.appendChild(row);
		});

		updateHidden();
	};

	const setModeAdd = () => {
		editingIndex = null;
		entryTitle.textContent = 'Enter One Activity';
		addButton.textContent = 'Add Activity';
		cancelEditButton.classList.add('hidden');
	};

	const setModeEdit = (index) => {
		editingIndex = index;
		entryTitle.textContent = 'Edit Activity';
		addButton.textContent = 'Update Activity';
		cancelEditButton.classList.remove('hidden');
	};

	const resetEntry = () => {
		entry.date.value = '';
		entry.name.value = '';
		entry.sdg.value = '';
		entry.venue.value = '';
		entry.participantProgram.value = '';
		entry.budget.value = '';
	};

	const loadEntry = (activity) => {
		entry.date.value = activity.date ?? '';
		entry.name.value = activity.name ?? '';
		entry.sdg.value = activity.sdg ?? '';
		entry.venue.value = activity.venue ?? '';
		entry.participantProgram.value = activity.participantProgram ?? '';
		entry.budget.value = activity.budget ?? '';
	};

	const getEntryData = () => {
		return {
			date: entry.date.value.trim(),
			name: entry.name.value.trim(),
			sdg: entry.sdg.value.trim(),
			venue: entry.venue.value.trim(),
			participantProgram: entry.participantProgram.value.trim(),
			budget: entry.budget.value.trim(),
		};
	};

	const validateEntry = () => {
		const fields = [
			entry.date,
			entry.name,
			entry.sdg,
			entry.venue,
			entry.participantProgram,
			entry.budget,
		];

		for (const field of fields) {
			if (!field.checkValidity()) {
				field.reportValidity();
				return false;
			}
		}

		return true;
	};

	addButton.addEventListener('click', () => {
		if (!validateEntry()) return;

		const data = getEntryData();

		if (editingIndex === null) {
			activities.push(data);
			render();
			resetEntry();
			entry.date.focus({ preventScroll: true });
			addedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
			showToast('Activity added successfully', { variant: 'success' });
			return;
		}

		activities[editingIndex] = data;
		render();
		resetEntry();
		setModeAdd();
		entry.date.focus({ preventScroll: true });
		addedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
		showToast('Activity updated successfully', { variant: 'success' });
	});

	cancelEditButton.addEventListener('click', () => {
		resetEntry();
		setModeAdd();
		entry.date.focus({ preventScroll: true });
	});

	const handleSubmitCalendar = (event) => {
		const submitter = event.submitter;
		if (submitter && submitter !== submitButton) return;

		event.preventDefault();

		if (activities.length < 5) {
			showWarningAlert(
				'Incomplete Activity Calendar',
				'Please add at least 5 activities before submitting the activity calendar.',
			);
			return;
		}

		const academicYear = document.getElementById('academic_year');
		const term = document.getElementById('term');
		const organizationName = document.getElementById('organization_name');
		const dateSubmitted = document.getElementById('date_submitted');

		const organizationFields = [academicYear, term, organizationName, dateSubmitted].filter(Boolean);
		for (const field of organizationFields) {
			if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) continue;

			if (field instanceof HTMLSelectElement) {
				if (!field.checkValidity()) {
					field.reportValidity();
					return;
				}
				continue;
			}

			if (field.value.trim() === '') {
				field.setCustomValidity('Please fill out this field.');
				field.reportValidity();
				field.setCustomValidity('');
				return;
			}

			if (!field.checkValidity()) {
				field.reportValidity();
				return;
			}
		}

		showSuccessAlert(
			'Activity Calendar Submitted',
			'Your activity calendar has been submitted successfully.',
		);
	};

	mainForm.addEventListener('submit', handleSubmitCalendar);

	previewBody.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof HTMLElement)) return;

		const action = target.getAttribute('data-action');
		const row = target.closest('tr[data-index]');
		const index = row?.getAttribute('data-index');
		const parsed = index ? Number(index) : NaN;
		if (!Number.isInteger(parsed)) return;

		if (action === 'edit') {
			const activity = activities[parsed];
			if (!activity) return;

			loadEntry(activity);
			setModeEdit(parsed);
			entry.date.focus({ preventScroll: true });
			entryTitle.scrollIntoView({ behavior: 'smooth', block: 'center' });
			return;
		}

		if (action === 'delete') {
			showConfirmAlert({
				title: 'Delete Activity?',
				text: 'This activity will be removed from the list.',
				icon: 'warning',
				confirmButtonText: 'Delete',
				cancelButtonText: 'Cancel',
			}).then((result) => {
				if (!result.isConfirmed) return;

				activities.splice(parsed, 1);

				if (editingIndex !== null) {
					if (editingIndex === parsed) {
						resetEntry();
						setModeAdd();
					} else if (editingIndex > parsed) {
						editingIndex -= 1;
					}
				}

				render();
				showToast('Activity deleted successfully', { variant: 'success' });
			});
		}
	});

	setModeAdd();
	render();
};
