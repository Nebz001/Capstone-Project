/**
 * Philippine mobile numbers: normalized storage as 09XXXXXXXXX (11 digits).
 */

export const PH_CONTACT_INVALID_MSG =
    "Enter a valid Philippine mobile number (e.g. 09XXXXXXXXX).";

export function digitsOnly(value) {
    return String(value ?? "").replace(/\D/g, "");
}

/** @returns {string|null} Normalized 09XXXXXXXXX or null if invalid */
export function normalizePhilippineMobile(value) {
    let d = digitsOnly(value);
    if (d.startsWith("63")) {
        d = d.slice(2);
    }
    if (d.startsWith("0")) {
        d = d.slice(1);
    }
    if (!/^9\d{9}$/.test(d)) {
        return null;
    }
    return `0${d}`;
}

export function isValidPhilippineMobile(value) {
    return normalizePhilippineMobile(value) !== null;
}

/**
 * Register / renew forms: digits-only input, validate on submit, save normalized value.
 */
export function initPhilippineContactInputs() {
    const forms = document.querySelectorAll(
        'form[action*="/organizations/register"], form[action*="/organizations/renew"]',
    );

    forms.forEach((form) => {
        const input = form.querySelector('input[name="contact_no"]');
        if (!input) {
            return;
        }

        const clearValidity = () => {
            input.setCustomValidity("");
        };

        input.addEventListener("input", () => {
            clearValidity();
            const digits = digitsOnly(input.value);
            const next = digits.length > 13 ? digits.slice(0, 13) : digits;
            if (input.value !== next) {
                input.value = next;
            }
        });

        input.addEventListener("blur", () => {
            const n = normalizePhilippineMobile(input.value);
            if (n) {
                input.value = n;
            }
            clearValidity();
        });
    });
}

/**
 * Call from the form submit handler before other checks. Normalizes value when valid.
 * @returns {boolean} true if OK to continue
 */
export function validateFormContactNo(form) {
    const input = form.querySelector('input[name="contact_no"]');
    if (!input) {
        return true;
    }
    const n = normalizePhilippineMobile(input.value);
    if (!n) {
        input.setCustomValidity(PH_CONTACT_INVALID_MSG);
        input.reportValidity();
        input.focus({ preventScroll: false });
        return false;
    }
    input.value = n;
    input.setCustomValidity("");
    return true;
}
