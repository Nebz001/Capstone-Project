import { showSuccessAlert } from "../components/alerts";
import {
    initPhilippineContactInputs,
    validateFormContactNo,
} from "../utils/philippine-contact";
import { initOrganizationTypeSchoolToggle } from "../utils/organization-type-school";

const ACCEPT_RE =
    /\.(pdf|doc|docx|jpe?g|png)$/i;

const setAttachVisualState = (item, hasFile, fileName) => {
    const btn = item.querySelector(".req-attach-btn");
    const badge = item.querySelector(".req-attached-badge");
    const nameEl = item.querySelector(".req-file-name");

    if (btn) {
        btn.classList.toggle(
            "text-emerald-600",
            Boolean(hasFile && fileName),
        );
        btn.classList.toggle(
            "ring-1",
            Boolean(hasFile && fileName),
        );
        btn.classList.toggle(
            "ring-emerald-200/90",
            Boolean(hasFile && fileName),
        );
        btn.classList.toggle(
            "bg-emerald-50/80",
            Boolean(hasFile && fileName),
        );
        btn.classList.toggle("text-slate-400", !hasFile || !fileName);
    }
    if (nameEl) {
        nameEl.textContent = fileName || "";
    }
    if (badge) {
        // Hide the text badge when a file name is shown beside the icon; show only if no name string.
        badge.classList.toggle("hidden", !hasFile || Boolean(fileName));
    }
};

const clearClientMsg = (item) => {
    const el = item.querySelector(".req-client-msg");
    if (el) {
        el.textContent = "";
        el.classList.add("hidden");
    }
};

const setClientMsg = (item, text) => {
    const el = item.querySelector(".req-client-msg");
    if (!el) {
        return;
    }
    if (!text) {
        el.textContent = "";
        el.classList.add("hidden");
        return;
    }
    el.textContent = text;
    el.classList.remove("hidden");
};

const REQUIREMENTS_MIN_ONE_MSG =
    "Select at least one requirement you are submitting.";
const REQUIRED_CHECKBOX_AND_FILE_MSG =
    "This requirement must be selected and must have an attached file.";
const REQUIRED_FILE_ONLY_MSG = "Please attach a file for this requirement.";

const REQUIRED_REGISTER_KEYS = new Set([
    "letter_of_intent",
    "application_form",
    "by_laws",
    "updated_list_of_officers_founders",
    "dean_endorsement_faculty_adviser",
    "proposed_projects_budget",
]);

const REQUIRED_RENEW_KEYS = new Set([
    "letter_of_intent",
    "application_form",
    "by_laws_updated_if_applicable",
    "updated_list_of_officers_founders_ay",
    "dean_endorsement_faculty_adviser",
    "proposed_projects_budget",
]);

const clearRequirementsSectionClientError = (form) => {
    const el = form.querySelector(".requirements-section-client-error");
    if (!el) {
        return;
    }
    el.textContent = "";
    el.classList.add("hidden");
};

const hasAnyRequirementChecked = (form) =>
    form.querySelectorAll(
        'input[type="checkbox"][name="requirements[]"]:checked',
    ).length > 0;

const requirementKeyFromItem = (item) => {
    const checkbox = item.querySelector(
        'input[type="checkbox"][name="requirements[]"]',
    );
    if (checkbox?.value) {
        return checkbox.value;
    }

    return item.dataset.requirementKey || "";
};

const requiredKeysForForm = (form) => {
    const action = form.getAttribute("action") || "";
    return action.includes("/organizations/renew")
        ? REQUIRED_RENEW_KEYS
        : REQUIRED_REGISTER_KEYS;
};

const syncRequirementRow = (item) => {
    const checkbox = item.querySelector(
        'input[type="checkbox"][name="requirements[]"]',
    );
    const fileInput = item.querySelector(".req-file-input");
    const attachBtn = item.querySelector(".req-attach-btn");

    if (!checkbox || !fileInput || !attachBtn) {
        return;
    }

    if (!checkbox.checked) {
        fileInput.disabled = true;
        fileInput.value = "";
        attachBtn.disabled = true;
        setAttachVisualState(item, false, "");
        clearClientMsg(item);
        return;
    }

    fileInput.disabled = false;
    attachBtn.disabled = false;

    const f = fileInput.files?.[0];
    if (f) {
        setAttachVisualState(item, true, f.name);
    } else {
        setAttachVisualState(item, false, "");
    }
};

const initRequirementAttachments = () => {
    const forms = document.querySelectorAll(
        'form[action*="/organizations/register"], form[action*="/organizations/renew"]',
    );

    forms.forEach((form) => {
        if (form.dataset.orgFormBlocked === "true") {
            return;
        }

        const items = form.querySelectorAll(".requirement-item");

        items.forEach((item) => {
            const checkbox = item.querySelector(
                'input[type="checkbox"][name="requirements[]"]',
            );
            const fileInput = item.querySelector(".req-file-input");
            const attachBtn = item.querySelector(".req-attach-btn");

            if (!checkbox || !fileInput || !attachBtn) {
                return;
            }

            attachBtn.addEventListener("click", () => {
                if (!checkbox.checked || attachBtn.disabled) {
                    return;
                }
                fileInput.click();
            });

            checkbox.addEventListener("change", () => {
                syncRequirementRow(item);
            });

            fileInput.addEventListener("change", () => {
                const f = fileInput.files?.[0];
                if (f && !checkbox.checked) {
                    checkbox.checked = true;
                }
                if (f && !ACCEPT_RE.test(f.name)) {
                    fileInput.value = "";
                    setClientMsg(
                        item,
                        "Please choose a PDF, Word, or image file.",
                    );
                    syncRequirementRow(item);
                    return;
                }
                clearClientMsg(item);
                syncRequirementRow(item);
            });

            syncRequirementRow(item);
        });

        form.addEventListener("change", (ev) => {
            const t = ev.target;
            if (
                t instanceof HTMLInputElement &&
                t.name === "requirements[]" &&
                t.type === "checkbox"
            ) {
                clearRequirementsSectionClientError(form);
            }
        });

        form.addEventListener("submit", (e) => {
            if (!validateFormContactNo(form)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }

            if (!hasAnyRequirementChecked(form)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const el = form.querySelector(".requirements-section-client-error");
                if (el) {
                    el.textContent = REQUIREMENTS_MIN_ONE_MSG;
                    el.classList.remove("hidden");
                }
                form
                    .querySelector("#requirements-section-validation")
                    ?.scrollIntoView({ block: "nearest", behavior: "smooth" });
                return;
            }

            clearRequirementsSectionClientError(form);

            const othersSpec = form.querySelector(
                'input[name="requirements_other"]',
            );
            let blocked = false;
            const requiredKeys = requiredKeysForForm(form);

            items.forEach((item) => {
                clearClientMsg(item);
            });

            for (const item of items) {
                const checkbox = item.querySelector(
                    'input[type="checkbox"][name="requirements[]"]',
                );
                const fileInput = item.querySelector(".req-file-input");
                if (!checkbox || !fileInput) {
                    continue;
                }

                const key = requirementKeyFromItem(item);
                const isRequiredStandard = requiredKeys.has(key);
                if (isRequiredStandard && !checkbox.checked) {
                    setClientMsg(item, REQUIRED_CHECKBOX_AND_FILE_MSG);
                    blocked = true;
                    continue;
                }

                if (!checkbox.checked) {
                    continue;
                }

                const isOthers =
                    checkbox.value === "others" ||
                    item.dataset.requirementKey === "others";

                if (isOthers && othersSpec) {
                    if (!(othersSpec.value || "").trim()) {
                        setClientMsg(
                            item,
                            "Please describe what “Others” refers to.",
                        );
                        blocked = true;
                        continue;
                    }
                }

                if (!fileInput.files?.length) {
                    setClientMsg(item, REQUIRED_FILE_ONLY_MSG);
                    blocked = true;
                }
            }

            if (blocked) {
                e.preventDefault();
                const firstErr = form.querySelector(
                    ".req-client-msg:not(.hidden)",
                );
                firstErr?.scrollIntoView({ block: "nearest", behavior: "smooth" });
            }
        });

        form.addEventListener("reset", () => {
            window.setTimeout(() => {
                clearRequirementsSectionClientError(form);
                items.forEach((item) => {
                    syncRequirementRow(item);
                    clearClientMsg(item);
                });
            }, 0);
        });
    });
};

const initAdviserSearch = () => {
    const forms = document.querySelectorAll(
        'form[action*="/organizations/register"], form[action*="/organizations/renew"]',
    );

    forms.forEach((form) => {
        const searchInput = form.querySelector("#adviser_search");
        const hiddenIdInput = form.querySelector("#adviser_user_id");
        const resultsBox = form.querySelector("#adviser_search_results");
        const selectedSummary = form.querySelector("#adviser_selected_summary");
        const selectedText = form.querySelector("#adviser_selected_text");

        if (
            !(searchInput instanceof HTMLInputElement) ||
            !(hiddenIdInput instanceof HTMLInputElement) ||
            !(resultsBox instanceof HTMLElement) ||
            !(selectedSummary instanceof HTMLElement) ||
            !(selectedText instanceof HTMLElement)
        ) {
            return;
        }

        let debounceTimer = null;
        const hideResults = () => {
            resultsBox.classList.add("hidden");
            resultsBox.innerHTML = "";
        };

        const renderResults = (rows) => {
            if (!Array.isArray(rows) || rows.length === 0) {
                hideResults();
                return;
            }

            resultsBox.innerHTML = rows
                .map(
                    (row) => `
                    <button
                        type="button"
                        class="flex w-full flex-col items-start rounded-lg px-3 py-2 text-left text-xs text-slate-700 transition hover:bg-slate-100"
                        data-adviser-select
                        data-adviser-id="${row.id}"
                        data-adviser-text="${(row.full_name || "").replace(/"/g, "&quot;")} | ${(
                            row.school_id || ""
                        ).replace(/"/g, "&quot;")} | ${(row.email || "").replace(/"/g, "&quot;")}"
                    >
                        <span class="font-semibold text-slate-900">${row.full_name || "N/A"}</span>
                        <span>${row.school_id || "No school ID"} • ${row.email || "No email"}</span>
                    </button>
                `,
                )
                .join("");
            resultsBox.classList.remove("hidden");
        };

        const runSearch = async (query) => {
            if (!query || query.trim().length < 2) {
                hideResults();
                return;
            }
            try {
                const url = `/api/users/search-advisers?q=${encodeURIComponent(
                    query.trim(),
                )}`;
                const response = await fetch(url, {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!response.ok) {
                    hideResults();
                    return;
                }
                const rows = await response.json();
                renderResults(rows);
            } catch (_error) {
                hideResults();
            }
        };

        searchInput.addEventListener("input", () => {
            searchInput.setCustomValidity("");
            hiddenIdInput.value = "";
            selectedText.textContent = "";
            selectedSummary.classList.add("hidden");
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(
                () => runSearch(searchInput.value || ""),
                220,
            );
        });

        resultsBox.addEventListener("click", (event) => {
            const target = event.target;
            const button =
                target instanceof HTMLElement
                    ? target.closest("[data-adviser-select]")
                    : null;
            if (!(button instanceof HTMLElement)) {
                return;
            }
            const adviserId = button.getAttribute("data-adviser-id") || "";
            const adviserText = button.getAttribute("data-adviser-text") || "";
            hiddenIdInput.value = adviserId;
            searchInput.value = adviserText;
            selectedText.textContent = adviserText;
            selectedSummary.classList.remove("hidden");
            hideResults();
        });

        document.addEventListener("click", (event) => {
            const target = event.target;
            if (!(target instanceof Node)) {
                return;
            }
            if (!form.contains(target)) {
                hideResults();
            }
        });

        form.addEventListener("submit", (event) => {
            if (!hiddenIdInput.value) {
                event.preventDefault();
                event.stopImmediatePropagation();
                searchInput.setCustomValidity(
                    "Please select a faculty adviser from search results.",
                );
                searchInput.reportValidity();
                return;
            }
            searchInput.setCustomValidity("");
        });
    });
};

const showOrganizationApplicationSuccessAlert = () => {
    const flashEl =
        document.getElementById("organization-register-success-alert-data") ||
        document.getElementById("organization-renew-success-alert-data") ||
        document.getElementById("after-activity-report-success-alert-data");

    if (!flashEl) {
        return;
    }

    const title = flashEl.dataset.successTitle || "Success";
    const text =
        flashEl.dataset.successMessage ||
        "Your application was submitted successfully.";
    const redirectUrl = flashEl.dataset.successRedirectUrl || "";
    const parsedDelay = Number.parseInt(
        flashEl.dataset.successRedirectDelay || "1800",
        10,
    );
    const redirectDelay =
        Number.isFinite(parsedDelay) && parsedDelay > 0 ? parsedDelay : 1800;

    const run = () => {
        if (!redirectUrl) {
            showSuccessAlert(title, text, { confirmButtonText: "Continue" });
            return;
        }

        showSuccessAlert(title, text, {
            timer: redirectDelay,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didClose: () => {
                window.location.assign(redirectUrl);
            },
        });
    };

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(run);
    });
};

export const initOrganizationApplicationAlerts = () => {
    initPhilippineContactInputs();
    initRequirementAttachments();
    initAdviserSearch();
    initOrganizationTypeSchoolToggle();

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            showOrganizationApplicationSuccessAlert,
            { once: true },
        );
    } else {
        showOrganizationApplicationSuccessAlert();
    }
};
