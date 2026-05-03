import { showConfirmAlert } from '../components/alerts';
import { initToast, showToast } from '../components/toast';

const escapeHtml = (value) => {
	return String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');
};

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const normalizeServerEntry = (row) => ({
	entryId: row.entryId != null ? Number(row.entryId) : null,
	date: row.date ?? '',
	name: row.name ?? '',
	sdgs: Array.isArray(row.sdgs) ? row.sdgs : [],
	venue: row.venue ?? '',
	participantProgram: row.participantProgram ?? '',
	budget: row.budget != null ? String(row.budget) : '',
});

const toApiPayload = (data) => ({
	date: data.date,
	name: data.name,
	sdg: data.sdgs,
	venue: data.venue,
	participant_program: data.participantProgram,
	budget: data.budget === '' ? '0' : data.budget,
});

const entryUrl = (template, id) => template.replace('__ENTRY_ID__', String(id));

export const initActivityCalendarSubmissionPage = () => {
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

	const persistApi = mainForm.dataset.calendarEntriesPersist === '1';
	const storeUrl = mainForm.dataset.calendarEntriesStoreUrl || '';
	const updateUrlTemplate = mainForm.dataset.calendarEntryUpdateUrlTemplate || '';
	const deleteUrlTemplate = mainForm.dataset.calendarEntryDeleteUrlTemplate || '';

	const activities = [];
	const initialEl = document.getElementById('activity-calendar-initial-activities');
	if (initialEl && initialEl.textContent) {
		try {
			const parsed = JSON.parse(initialEl.textContent.trim());
			if (Array.isArray(parsed)) {
				for (const row of parsed) {
					activities.push(normalizeServerEntry(row));
				}
			}
		} catch {
			/* ignore */
		}
	}

	let editingIndex = null;

	const jsonHeaders = {
		'Content-Type': 'application/json',
		Accept: 'application/json',
		'X-CSRF-TOKEN': getCsrfToken(),
		'X-Requested-With': 'XMLHttpRequest',
	};

	const parseErrorMessage = async (res) => {
		try {
			const body = await res.json();
			if (body.errors && typeof body.errors === 'object') {
				const first = Object.values(body.errors).flat()[0];
				if (first) return String(first);
			}
			if (body.message) return String(body.message);
		} catch {
			/* ignore */
		}
		return 'Something went wrong. Please try again.';
	};

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
			if (activity.entryId) {
				row.dataset.entryId = String(activity.entryId);
			}

			row.innerHTML = `
				<td class="px-5 py-4 font-medium text-slate-900">${escapeHtml(activity.date || '—')}</td>
				<td class="px-5 py-4 font-medium text-slate-900">${escapeHtml(activity.name || '—')}</td>
				<td class="px-5 py-4 text-slate-700">${escapeHtml((activity.sdgs || []).join(', ') || '—')}</td>
				<td class="px-5 py-4 text-slate-700">${escapeHtml(activity.venue || '—')}</td>
				<td class="px-5 py-4 text-slate-700">${escapeHtml(activity.participantProgram || '—')}</td>
				<td class="px-5 py-4 text-slate-700">${escapeHtml(activity.budget || '—')}</td>
				<td class="px-5 py-4">
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
			entryId: editingIndex !== null ? activities[editingIndex]?.entryId ?? null : null,
			date: entry.date.value.trim(),
			name: entry.name.value.trim(),
			sdgs: selectedSdgs(),
			venue: entry.venue.value.trim(),
			participantProgram: entry.participantProgram.value.trim(),
			budget: entry.budget.value.trim(),
		};
	};

	const validateEntry = () => {
		const fields = [entry.date, entry.name, entry.venue, entry.participantProgram, entry.budget];

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

	addButton.addEventListener('click', async () => {
		if (!validateEntry()) return;

		const data = getEntryData();
		const wasEditing = editingIndex !== null;

		if (persistApi && storeUrl && (editingIndex === null || activities[editingIndex]?.entryId)) {
			const payload = toApiPayload(data);
			try {
				if (editingIndex !== null && activities[editingIndex]?.entryId) {
					const id = activities[editingIndex].entryId;
					const url = entryUrl(updateUrlTemplate, id);
					const res = await fetch(url, {
						method: 'PUT',
						credentials: 'same-origin',
						headers: jsonHeaders,
						body: JSON.stringify(payload),
					});
					if (!res.ok) {
						showToast(await parseErrorMessage(res), { variant: 'error' });
						return;
					}
					const body = await res.json();
					const merged = normalizeServerEntry(body.entry);
					activities[editingIndex] = merged;
				} else if (editingIndex === null) {
					const res = await fetch(storeUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: jsonHeaders,
						body: JSON.stringify(payload),
					});
					if (!res.ok) {
						showToast(await parseErrorMessage(res), { variant: 'error' });
						return;
					}
					const body = await res.json();
					activities.push(normalizeServerEntry(body.entry));
				} else {
					activities[editingIndex] = { ...data, entryId: null };
				}

				render();
				resetEntry();
				setModeAdd();
				entry.date.focus({ preventScroll: true });
				addedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				showToast(wasEditing ? 'Activity updated successfully' : 'Activity added successfully', {
					variant: 'success',
				});
			} catch {
				showToast('Network error. Please try again.', { variant: 'error' });
			}
			return;
		}

		if (editingIndex === null) {
			activities.push({ ...data, entryId: null });
			render();
			resetEntry();
			entry.date.focus({ preventScroll: true });
			addedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
			showToast('Activity added successfully', { variant: 'success' });
			return;
		}

		activities[editingIndex] = { ...data, entryId: activities[editingIndex]?.entryId ?? null };
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
			}).then(async (result) => {
				if (!result.isConfirmed) return;

				const act = activities[parsed];
				if (persistApi && deleteUrlTemplate && act?.entryId) {
					try {
						const delUrl = entryUrl(deleteUrlTemplate, act.entryId);
						const res = await fetch(delUrl, {
							method: 'DELETE',
							credentials: 'same-origin',
							headers: {
								Accept: 'application/json',
								'X-CSRF-TOKEN': getCsrfToken(),
								'X-Requested-With': 'XMLHttpRequest',
							},
						});
						if (!res.ok) {
							showToast(await parseErrorMessage(res), { variant: 'error' });
							return;
						}
					} catch {
						showToast('Network error. Please try again.', { variant: 'error' });
						return;
					}
				}

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
