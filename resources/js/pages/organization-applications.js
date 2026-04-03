import { showSuccessAlert } from "../components/alerts";

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
    if (badge) {
        badge.classList.toggle("hidden", !hasFile || !fileName);
    }
    if (nameEl) {
        nameEl.textContent = fileName || "";
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

        form.addEventListener("submit", (e) => {
            const othersSpec = form.querySelector(
                'input[name="requirements_other"]',
            );
            let blocked = false;

            items.forEach((item) => {
                clearClientMsg(item);
            });

            for (const item of items) {
                const checkbox = item.querySelector(
                    'input[type="checkbox"][name="requirements[]"]',
                );
                const fileInput = item.querySelector(".req-file-input");
                if (!checkbox?.checked || !fileInput) {
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
                    setClientMsg(
                        item,
                        "Attach a file for this requirement (use the paperclip).",
                    );
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
                items.forEach((item) => {
                    syncRequirementRow(item);
                    clearClientMsg(item);
                });
            }, 0);
        });
    });
};

export const initOrganizationApplicationAlerts = () => {
    initRequirementAttachments();

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
