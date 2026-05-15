import { reactive } from 'vue';

const defaultState = () => ({
    open: false,
    kind: 'confirm',
    title: '',
    message: '',
    confirmText: 'تأكيد',
    cancelText: 'إلغاء',
    variant: 'info',
    inputValue: '',
    inputPlaceholder: '',
    inputRequired: false,
    inputMultiline: false,
    inputLabel: '',
    errorMessage: '',
});

const state = reactive(defaultState());

let resolver = null;

const resetState = () => {
    Object.assign(state, defaultState());
};

const finish = (value) => {
    const currentResolver = resolver;
    resolver = null;
    resetState();
    currentResolver?.(value);
};

const normalizeOptions = (options, fallbackKind) => {
    if (typeof options === 'string') {
        return {
            kind: fallbackKind,
            message: options,
        };
    }

    return {
        kind: fallbackKind,
        ...(options || {}),
    };
};

const openDialog = (config) => new Promise((resolve) => {
    if (resolver !== null) {
        finish(config.kind === 'alert' ? undefined : null);
    }

    resolver = resolve;
    Object.assign(state, defaultState(), config, {
        open: true,
        errorMessage: '',
        inputValue: config.defaultValue ?? '',
    });
});

export const useActionDialog = () => ({
    confirm: (options) => openDialog({
        title: 'تأكيد الإجراء',
        confirmText: 'متابعة',
        cancelText: 'إلغاء',
        variant: 'danger',
        ...normalizeOptions(options, 'confirm'),
    }),
    alert: (options) => openDialog({
        title: 'تنبيه',
        confirmText: 'حسنًا',
        variant: 'info',
        ...normalizeOptions(options, 'alert'),
    }),
    prompt: (options) => openDialog({
        title: 'إدخال مطلوب',
        confirmText: 'حفظ',
        cancelText: 'إلغاء',
        variant: 'info',
        inputRequired: false,
        inputMultiline: false,
        ...normalizeOptions(options, 'prompt'),
    }),
});

export const useActionDialogState = () => ({
    state,
    cancel: () => finish(state.kind === 'alert' ? undefined : null),
    submit: () => {
        if (state.kind === 'prompt') {
            const trimmed = String(state.inputValue || '').trim();
            if (state.inputRequired && trimmed === '') {
                state.errorMessage = 'هذا الحقل مطلوب لإكمال العملية.';
                return;
            }

            finish(trimmed);
            return;
        }

        finish(state.kind === 'alert' ? undefined : true);
    },
});
