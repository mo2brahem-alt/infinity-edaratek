const FIELD_LABELS = {
    name: 'الاسم',
    email: 'البريد الإلكتروني',
    password: 'كلمة المرور',
    current_password: 'كلمة المرور الحالية',
    password_confirmation: 'تأكيد كلمة المرور',
    phone: 'رقم الجوال',
    mobile: 'رقم الجوال',
    region_id: 'الإدارة التعليمية',
    school_id: 'المدرسة',
    address: 'العنوان',
    notes: 'الملاحظات',
};

const EMAIL_REGEX = /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/;
const SAUDI_MOBILE_REGEXES = [
    /^05\d{8}$/,
    /^5\d{8}$/,
    /^\+9665\d{8}$/,
    /^009665\d{8}$/,
    /^9665\d{8}$/,
];
const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
const EASTERN_ARABIC_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
const VALIDATABLE_SELECTOR = 'input, textarea, select';
const PICKER_INPUT_TYPES = new Set(['date', 'time', 'datetime-local', 'month']);

export const toLatinDigits = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/[٠-٩۰-۹]/g, (character) => {
        const arabicIndex = ARABIC_DIGITS.indexOf(character);
        if (arabicIndex !== -1) {
            return LATIN_DIGITS[arabicIndex];
        }

        const easternIndex = EASTERN_ARABIC_DIGITS.indexOf(character);

        return easternIndex !== -1 ? LATIN_DIGITS[easternIndex] : character;
    });
};

export const sanitizeEmailValue = (value) => {
    let sanitized = toLatinDigits(value).replace(/\s+/g, '');
    sanitized = sanitized.replace(/[^\x21-\x7E]/g, '');
    sanitized = sanitized.replace(/[^A-Za-z0-9.!#$%&'*+/=?^_`{|}~@-]/g, '');

    const atIndex = sanitized.indexOf('@');
    if (atIndex !== -1) {
        sanitized = sanitized.slice(0, atIndex + 1) + sanitized.slice(atIndex + 1).replace(/@/g, '');
    }

    return sanitized.toLowerCase();
};

export const sanitizeSaudiMobileValue = (value) => {
    let sanitized = toLatinDigits(value).replace(/[^\d+]/g, '');
    sanitized = sanitized.replace(/(?!^)\+/g, '');

    if (sanitized.includes('+') && !sanitized.startsWith('+')) {
        sanitized = sanitized.replace(/\+/g, '');
    }

    return sanitized;
};

export const isValidSaudiMobile = (value) => {
    const sanitized = sanitizeSaudiMobileValue(value);

    return SAUDI_MOBILE_REGEXES.some((pattern) => pattern.test(sanitized));
};

const isTextEntryField = (target) =>
    target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement;

const isValidatableField = (target) =>
    target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement;

const fieldToken = (target) => {
    const tokens = [
        target.getAttribute('name'),
        target.getAttribute('id'),
        target.getAttribute('autocomplete'),
        target.getAttribute('data-field-type'),
    ].filter(Boolean);

    return tokens.join(' ').toLowerCase();
};

const inferFieldKind = (target) => {
    const token = fieldToken(target);

    if (target instanceof HTMLInputElement && target.type === 'email') {
        return 'email';
    }

    if (
        (target instanceof HTMLInputElement && target.type === 'tel')
        || target.getAttribute('inputmode') === 'tel'
        || /\b(phone|mobile|tel)\b/.test(token)
    ) {
        return 'phone';
    }

    if (/\bname\b/.test(token)) {
        return 'name';
    }

    if (/\baddress\b/.test(token)) {
        return 'address';
    }

    if (/\bnotes\b/.test(token)) {
        return 'notes';
    }

    return null;
};

const inferMaxLength = (target) => {
    const nativeMaxLength = Number(target.getAttribute('maxlength'));
    if (Number.isFinite(nativeMaxLength) && nativeMaxLength > 0) {
        return nativeMaxLength;
    }

    return {
        email: 255,
        phone: 20,
        name: 255,
        address: 500,
        notes: 2000,
    }[inferFieldKind(target)] || null;
};

const ensureMaxLength = (target) => {
    if (!isTextEntryField(target)) {
        return;
    }

    const maxLength = inferMaxLength(target);
    if (maxLength && !target.hasAttribute('maxlength')) {
        target.setAttribute('maxlength', String(maxLength));
    }
};

const sanitizeFieldValue = (target) => {
    if (!isTextEntryField(target)) {
        return;
    }

    let nextValue = target.value;
    const kind = inferFieldKind(target);

    if (kind === 'email') {
        nextValue = sanitizeEmailValue(nextValue);
    } else if (kind === 'phone') {
        nextValue = sanitizeSaudiMobileValue(nextValue);
    } else {
        nextValue = toLatinDigits(nextValue);
    }

    const maxLength = inferMaxLength(target);
    if (maxLength && nextValue.length > maxLength) {
        nextValue = nextValue.slice(0, maxLength);
    }

    if (nextValue !== target.value) {
        target.value = nextValue;
    }
};

const cleanLabelText = (value) => String(value || '').replace(/\s+/g, ' ').replace(/\(.*?\)/g, '').trim();

const resolveFieldLabel = (target) => {
    const explicit = cleanLabelText(target.getAttribute('data-field-label') || target.getAttribute('aria-label'));
    if (explicit) {
        return explicit;
    }

    const id = target.getAttribute('id');
    if (id) {
        const directLabel = document.querySelector(`label[for="${id}"]`);
        const directText = cleanLabelText(directLabel?.textContent);
        if (directText) {
            return directText;
        }
    }

    const wrappedLabel = cleanLabelText(target.closest('label')?.textContent);
    if (wrappedLabel) {
        return wrappedLabel;
    }

    const token = fieldToken(target);
    const matchedLabel = Object.entries(FIELD_LABELS).find(([key]) => token.includes(key));
    if (matchedLabel) {
        return matchedLabel[1];
    }

    if (inferFieldKind(target) === 'email') {
        return 'البريد الإلكتروني';
    }

    if (inferFieldKind(target) === 'phone') {
        return 'رقم الجوال';
    }

    return target instanceof HTMLSelectElement ? 'هذا الاختيار' : 'هذا الحقل';
};

const invalidMessageForField = (target) => {
    const label = resolveFieldLabel(target);
    const maxLength = inferMaxLength(target);
    const kind = inferFieldKind(target);

    if (kind === 'phone' && String(target.value || '').trim() !== '' && !isValidSaudiMobile(target.value)) {
        return 'يرجى إدخال رقم جوال سعودي صحيح مثل 05xxxxxxxx أو +9665xxxxxxxx.';
    }

    if (kind === 'email' && String(target.value || '').trim() !== '' && !EMAIL_REGEX.test(sanitizeEmailValue(target.value))) {
        return 'يرجى إدخال بريد إلكتروني صحيح.';
    }

    if (!target.validity) {
        return '';
    }

    if (target.validity.valueMissing) {
        return `${label} مطلوب.`;
    }

    if (target.validity.typeMismatch && kind === 'email') {
        return 'يرجى إدخال بريد إلكتروني صحيح.';
    }

    if (target.validity.tooLong && maxLength) {
        return `${label} يجب ألا يتجاوز ${maxLength} حرفًا.`;
    }

    if (target.validity.tooShort) {
        return `${label} يجب ألا يقل عن ${target.getAttribute('minlength')} أحرف.`;
    }

    if (target.validity.patternMismatch && kind === 'phone') {
        return 'يرجى إدخال رقم جوال سعودي صحيح مثل 05xxxxxxxx أو +9665xxxxxxxx.';
    }

    if (target.validity.patternMismatch) {
        return `${label} غير مطابق للشكل المطلوب.`;
    }

    if (target.validity.badInput) {
        return `${label} غير صالح.`;
    }

    return target.validationMessage || '';
};

const resetCustomValidity = (target) => {
    if (isValidatableField(target)) {
        target.setCustomValidity('');
    }
};

const prepareField = (target) => {
    if (!isValidatableField(target)) {
        return;
    }

    ensureMaxLength(target);
    sanitizeFieldValue(target);
};

const applyFinalFieldValidation = (target) => {
    if (!isValidatableField(target)) {
        return;
    }

    prepareField(target);
    resetCustomValidity(target);

    const message = invalidMessageForField(target);
    if (message !== '') {
        target.setCustomValidity(message);
    }
};

const isNativePickerInput = (target) =>
    target instanceof HTMLInputElement
    && PICKER_INPUT_TYPES.has(target.type)
    && !target.disabled
    && !target.readOnly;

const openNativePicker = (target) => {
    if (!isNativePickerInput(target) || typeof target.showPicker !== 'function') {
        return;
    }

    try {
        target.showPicker();
    } catch {
        // Some browsers expose showPicker() but reject calls in edge cases.
    }
};

export const installInputGuards = () => {
    if (typeof window === 'undefined' || window.__edaratekInputGuardsInstalled) {
        return;
    }

    window.__edaratekInputGuardsInstalled = true;

    document.addEventListener('focusin', (event) => {
        prepareField(event.target);
    }, true);

    document.addEventListener('click', (event) => {
        openNativePicker(event.target);
    }, true);

    document.addEventListener('input', (event) => {
        prepareField(event.target);
        resetCustomValidity(event.target);
    }, true);

    document.addEventListener('change', (event) => {
        prepareField(event.target);
        resetCustomValidity(event.target);
    }, true);

    document.addEventListener('blur', (event) => {
        applyFinalFieldValidation(event.target);
    }, true);

    document.addEventListener('invalid', (event) => {
        applyFinalFieldValidation(event.target);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        Array.from(form.querySelectorAll(VALIDATABLE_SELECTOR)).forEach((field) => {
            applyFinalFieldValidation(field);
        });

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.reportValidity();
        }
    }, true);
};
