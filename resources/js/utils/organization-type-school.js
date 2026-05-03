/**
 * Co-curricular orgs require a School; extra-curricular disables and clears the select.
 */
export function initOrganizationTypeSchoolToggle() {
    const forms = document.querySelectorAll(
        'form[action*="/organizations/register"], form[action*="/organizations/renew"], form[action*="/admin/submissions/register"], form[action*="/admin/submissions/renew"]',
    );

    forms.forEach((form) => {
        if (form.dataset.orgFormBlocked === "true") {
            return;
        }

        const schoolSelect = form.querySelector("#school");
        const schoolFieldGroup = form.querySelector("#school-field-group");
        const radios = form.querySelectorAll('input[name="organization_type"]');
        const requiredMark = form.querySelector("#school-required-mark");
        const nonAcademicNotice = form.querySelector("#school-non-academic-notice");

        if (!schoolSelect || radios.length === 0) {
            return;
        }

        const sync = () => {
            const extra = form.querySelector(
                'input[name="organization_type"][value="extra_curricular"]',
            );
            const isExtra = Boolean(extra?.checked);

            schoolSelect.disabled = isExtra;
            schoolSelect.required = !isExtra;
            if (schoolFieldGroup) {
                schoolFieldGroup.classList.toggle("hidden", isExtra);
            }

            if (isExtra) {
                schoolSelect.value = "";
                const blank = schoolSelect.querySelector('option[value=""]');
                if (blank) {
                    blank.selected = true;
                }
                schoolSelect.setAttribute(
                    "aria-describedby",
                    "school-non-academic-notice",
                );
            } else {
                schoolSelect.removeAttribute("aria-describedby");
            }

            if (requiredMark) {
                requiredMark.classList.toggle("hidden", isExtra);
                requiredMark.setAttribute(
                    "aria-hidden",
                    isExtra ? "true" : "false",
                );
            }

            if (nonAcademicNotice) {
                nonAcademicNotice.classList.toggle("hidden", !isExtra);
            }
        };

        radios.forEach((r) => r.addEventListener("change", sync));
        sync();
    });
}
