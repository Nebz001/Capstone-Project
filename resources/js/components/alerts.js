import Swal from 'sweetalert2';

const INDIGO_CONFIRM_BUTTON_CLASS =
    'inline-flex items-center justify-center rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500/25';

const RED_CONFIRM_BUTTON_CLASS =
    'inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600/30';

const NEUTRAL_CANCEL_BUTTON_CLASS =
    'inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/15';

const baseOptions = {
    buttonsStyling: false,
    customClass: {
        popup: 'text-left',
    },
};

const mergeCustomClass = (baseCustomClass, overrideCustomClass) => {
    return {
        ...(baseCustomClass ?? {}),
        ...(overrideCustomClass ?? {}),
    };
};

export const showAlert = (options = {}) => {
    const merged = {
        ...baseOptions,
        ...options,
        customClass: mergeCustomClass(baseOptions.customClass, options.customClass),
    };

    return Swal.fire(merged);
};

export const showWarningAlert = (title, text, extraOptions = {}) => {
    return showAlert({
        icon: 'warning',
        title,
        text,
        confirmButtonText: 'OK',
        ...extraOptions,
        customClass: mergeCustomClass(
            {
                confirmButton: INDIGO_CONFIRM_BUTTON_CLASS,
            },
            extraOptions.customClass,
        ),
    });
};

export const showSuccessAlert = (title, text, extraOptions = {}) => {
    return showAlert({
        icon: 'success',
        title,
        text,
        confirmButtonText: 'OK',
        ...extraOptions,
        customClass: mergeCustomClass(
            {
                confirmButton: INDIGO_CONFIRM_BUTTON_CLASS,
            },
            extraOptions.customClass,
        ),
    });
};

export const showConfirmAlert = (options = {}) => {
    const {
        title,
        text,
        icon = 'warning',
        confirmButtonText = 'Confirm',
        cancelButtonText = 'Cancel',
        reverseButtons = true,
        customClass,
        ...rest
    } = options;

    return showAlert({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons,
        ...rest,
        customClass: mergeCustomClass(
            {
                actions: 'gap-2',
                confirmButton: RED_CONFIRM_BUTTON_CLASS,
                cancelButton: NEUTRAL_CANCEL_BUTTON_CLASS,
            },
            customClass,
        ),
    });
};

export const ALERT_BUTTON_CLASSES = {
    indigoConfirm: INDIGO_CONFIRM_BUTTON_CLASS,
    redConfirm: RED_CONFIRM_BUTTON_CLASS,
    neutralCancel: NEUTRAL_CANCEL_BUTTON_CLASS,
};
