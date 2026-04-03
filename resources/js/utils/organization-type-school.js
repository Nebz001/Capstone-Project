/**
 * Co-curricular orgs require a School; extra-curricular disables and clears the select.
 */
export function initOrganizationTypeSchoolToggle() {
    const forms = document.querySelectorAll(
        'form[action*="/organizations/register"], form[action*="/organizations/renew"]',
    );

    forms.forEach((form) => {
        const schoolSelect = form.querySelector("#school");
        const radios = form.querySelectorAll('input[name="organization_type"]');
        const requiredMark = form.querySelector("#school-required-mark");

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

            if (isExtra) {
                schoolSelect.value = "";
                const blank = schoolSelect.querySelector('option[value=""]');
                if (blank) {
                    blank.selected = true;
                }
            }

            if (requiredMark) {
                requiredMark.classList.toggle("hidden", isExtra);
                requiredMark.setAttribute(
                    "aria-hidden",
                    isExtra ? "true" : "false",
                );
            }
        };

        radios.forEach((r) => r.addEventListener("change", sync));
        sync();
    });
}
