import { showSuccessAlert } from "../components/alerts";

export const initOrganizationApplicationAlerts = () => {
    const registerForm = document.querySelector(
        'form[action*="/organizations/register"]',
    );
    const renewalForm = document.querySelector(
        'form[action*="/organizations/renew"]',
    );

    if (!registerForm && !renewalForm) {
        return;
    }

    const flashEl =
        document.getElementById("organization-register-success-alert-data") ||
        document.getElementById("organization-renew-success-alert-data");

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

    window.requestAnimationFrame(() => {
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
    });
};
