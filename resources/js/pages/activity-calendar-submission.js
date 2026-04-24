import { ALERT_BUTTON_CLASSES, showAlert, showConfirmAlert } from '../components/alerts';
import { initToast, showToast } from '../components/toast';

const escapeHtml = (value) => {
	return String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');
};

const showSubmissionSuccessAlert = () => {
	const flashEl = document.getElementById('activity-calendar-submitted-flash');
	if (!flashEl) return;

	let data;
	try {
		data = JSON.parse(flashEl.textContent);
	} catch {
		return;
	}
	flashEl.remove();

	showAlert({
		icon: 'success',
		title: 'Activity Calendar Submitted',
		text: 'Your activity calendar has been submitted successfully. What would you like to do next?',
		showConfirmButton: true,
		showDenyButton: true,
		confirmButtonText: 'Go to Activity Submission',
		denyButtonText: 'Go to Proposal Submission',
		allowOutsideClick: false,
		customClass: {
			actions: 'gap-2 flex-col sm:flex-row w-full px-4',
			confirmButton: ALERT_BUTTON_CLASSES.indigoConfirm + ' w-full sm:w-auto',
			denyButton: ALERT_BUTTON_CLASSES.neutralCancel + ' w-full sm:w-auto',
		},
	}).then((result) => {
		if (result.isConfirmed && (data.activitySubmissionUrl || data.submittedDocumentsUrl)) {
			window.location.href = data.activitySubmissionUrl || data.submittedDocumentsUrl;
		} else if (result.isDenied && data.proposalSubmissionUrl) {
			window.location.href = data.proposalSubmissionUrl;
		}
	});
};

export const initActivityCalendarSubmissionPage = () => {
	showSubmissionSuccessAlert();

	const mainForm = document.getElementById('activity-calendar-form');
	if (!mainForm) return;

	if (mainForm.dataset.activityCalendarFormBlocked === 'true') {
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
		sdgDropdown: document.getElementById('activity-sdg-dropdown'),
		sdgTrigger: document.getElementById('activity-sdg-trigger'),
		sdgMenu: document.getElementById('activity-sdg-menu'),
		sdgTriggerText: document.getElementById('activity-sdg-trigger-text'),
		sdgSelectedWrap: document.getElementById('activity-sdg-selected-wrap'),
		sdgSelectedList: document.getElementById('activity-sdg-selected-list'),
		sdgOptions: Array.from(document.querySelectorAll('.activity-sdg-option')),
		sdgReminder: document.getElementById('activity-sdg-required-reminder'),
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
	if (!entry.date || !entry.name || entry.sdgOptions.length === 0 || !entry.venue || !entry.participantProgram || !entry.budget) return;
	if (!entry.sdgDropdown || !entry.sdgTrigger || !entry.sdgMenu || !entry.sdgTriggerText || !entry.sdgSelectedWrap || !entry.sdgSelectedList) return;

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
					...(Array.isArray(activity.sdgs)
						? activity.sdgs.map(
								(sdg, sdgIndex) =>
									`<input type="hidden" name="${prefix}[sdg][${sdgIndex}]" value="${escapeHtml(sdg)}" />`,
						  )
						: []),
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
				<td class="px-5 py-3.5 text-slate-900">${escapeHtml((activity.sdgs || []).join(', ') || '—')}</td>
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

	const selectedSdgs = () =>
		entry.sdgOptions.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value.trim());

	const closeSdgDropdown = () => {
		entry.sdgMenu.classList.add('hidden');
		entry.sdgTrigger.setAttribute('aria-expanded', 'false');
	};

	const openSdgDropdown = () => {
		entry.sdgMenu.classList.remove('hidden');
		entry.sdgTrigger.setAttribute('aria-expanded', 'true');
	};

	const updateSdgSelectionUi = () => {
		const selected = selectedSdgs();
		entry.sdgTriggerText.textContent = selected.length > 0 ? selected.join(', ') : 'Select one or more SDGs';
		entry.sdgTriggerText.classList.toggle('text-slate-500', selected.length === 0);
		entry.sdgTriggerText.classList.toggle('text-slate-900', selected.length > 0);

		if (selected.length === 0) {
			entry.sdgSelectedWrap.classList.add('hidden');
			entry.sdgSelectedList.innerHTML = '';
			return;
		}

		entry.sdgSelectedWrap.classList.remove('hidden');
		entry.sdgSelectedList.innerHTML = selected
			.map(
				(sdg) =>
					`<span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">${escapeHtml(sdg)}</span>`,
			)
			.join('');
	};

	const resetEntry = () => {
		entry.date.value = '';
		entry.name.value = '';
		entry.sdgOptions.forEach((checkbox) => {
			checkbox.checked = false;
		});
		updateSdgSelectionUi();
		closeSdgDropdown();
		if (entry.sdgReminder) {
			entry.sdgReminder.classList.add('hidden');
		}
		entry.venue.value = '';
		entry.participantProgram.value = '';
		entry.budget.value = '';
	};

	const loadEntry = (activity) => {
		entry.date.value = activity.date ?? '';
		entry.name.value = activity.name ?? '';
		const selected = Array.isArray(activity.sdgs) ? activity.sdgs : [];
		entry.sdgOptions.forEach((checkbox) => {
			checkbox.checked = selected.includes(checkbox.value);
		});
		updateSdgSelectionUi();
		if (entry.sdgReminder) {
			entry.sdgReminder.classList.add('hidden');
		}
		entry.venue.value = activity.venue ?? '';
		entry.participantProgram.value = activity.participantProgram ?? '';
		entry.budget.value = activity.budget ?? '';
	};

	const getEntryData = () => {
		return {
			date: entry.date.value.trim(),
			name: entry.name.value.trim(),
			sdgs: selectedSdgs(),
			venue: entry.venue.value.trim(),
			participantProgram: entry.participantProgram.value.trim(),
			budget: entry.budget.value.trim(),
		};
	};

	const validateEntry = () => {
		const fields = [
			entry.date,
			entry.name,
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

		if (selectedSdgs().length === 0) {
			if (entry.sdgReminder) {
				entry.sdgReminder.classList.remove('hidden');
			}
			return false;
		}
		if (entry.sdgReminder) {
			entry.sdgReminder.classList.add('hidden');
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

		updateHidden();
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

	entry.sdgTrigger.addEventListener('click', () => {
		if (entry.sdgMenu.classList.contains('hidden')) {
			openSdgDropdown();
			return;
		}
		closeSdgDropdown();
	});

	entry.sdgOptions.forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			updateSdgSelectionUi();
			if (entry.sdgReminder && selectedSdgs().length > 0) {
				entry.sdgReminder.classList.add('hidden');
			}
		});
	});

	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Node)) return;
		if (!entry.sdgDropdown.contains(target)) {
			closeSdgDropdown();
		}
	});

	setModeAdd();
	updateSdgSelectionUi();
	render();
};
