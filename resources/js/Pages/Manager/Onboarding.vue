<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import { useThemeMode } from '@/composables/useThemeMode';
import { isValidSaudiMobile, sanitizeEmailValue, sanitizeSaudiMobileValue } from '@/utils/installInputGuards';

const EMAIL_REGEX = /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/;
const ALLOWED_LOGO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_LOGO_SIZE_BYTES = 2 * 1024 * 1024;

const props = defineProps({
    currentRegionId: { type: Number, default: null },
    currentSchool: { type: Object, default: null },
    accountStatus: { type: Object, default: null },
});

const { isLightMode } = useThemeMode();

const countries = ref([]);
const governorates = ref([]);
const educationTypes = ref([]);
const educationStages = ref([]);
const availableTemplates = ref([]);
const selectedCountryId = ref('');
const selectedGovernorateId = ref('');
const selectedEducationTypeId = ref('');
const selectedTemplateKey = ref('');
const selectedSchoolType = ref('');
const selectedEducationStageIds = ref([]);
const currentSchool = ref(props.currentSchool);
const message = ref('');
const error = ref('');
const creatingSchool = ref(false);
const createErrors = ref({});
const templatesLoading = ref(false);
const templatesError = ref('');
const templateRequestId = ref(0);
const governoratesLoading = ref(false);
const governoratesError = ref('');
const governorateRequestId = ref(0);
const schoolDetailsSectionRef = ref(null);
const logoPreviewUrl = ref('');
const logoInputKey = ref(0);

const createForm = ref({ name: '', phone: '', email: '', address: '', notes: '', logo: null });
const schoolTypeOptions = [
    { value: 'boys', label: 'بنين', description: 'مدرسة مخصصة للطلاب البنين.' },
    { value: 'girls', label: 'بنات', description: 'مدرسة مخصصة للطالبات البنات.' },
    { value: 'mixed', label: 'مختلطة', description: 'مدرسة تستقبل البنين والبنات.' },
];

const hasCurrentSchool = computed(() => Boolean(currentSchool.value?.id));
const accountStatus = computed(() => props.accountStatus || {});
const shouldShowAccountStatus = computed(() => Boolean(accountStatus.value?.message));

const availableEducationTypes = computed(() =>
    [...educationTypes.value]
        .map((type) => ({
            id: Number(type.id),
            name: type.name || 'غير محدد',
        }))
        .filter((type) => type.id > 0)
        .sort((first, second) => String(first.name || '').localeCompare(String(second.name || ''), 'ar')));

const availableGovernorates = computed(() =>
    [...governorates.value]
        .map((governorate) => ({
            id: Number(governorate.id),
            countryId: Number(governorate.country_id || 0),
            name: String(governorate.name || '').trim(),
        }))
        .filter((governorate) => governorate.id > 0 && governorate.name !== '')
        .sort((first, second) => first.name.localeCompare(second.name, 'ar')));

const availableEducationStages = computed(() =>
    [...educationStages.value]
        .map((stage) => ({
            id: Number(stage.id),
            name: String(stage.name || '').trim(),
            sortOrder: Number(stage.sort_order || 0),
            isActive: Boolean(stage.is_active),
        }))
        .filter((stage) => stage.id > 0 && stage.name !== '' && stage.isActive)
        .sort((first, second) => first.sortOrder - second.sortOrder || first.name.localeCompare(second.name, 'ar')));

const selectedCountryName = computed(() =>
    countries.value.find((country) => Number(country.id) === Number(selectedCountryId.value))?.name || '');

const selectedEducationTypeName = computed(() =>
    educationTypes.value.find((type) => Number(type.id) === Number(selectedEducationTypeId.value))?.name || '');

const selectedTemplate = computed(() =>
    availableTemplates.value.find((template) => String(template.key || '') === String(selectedTemplateKey.value || '')) || null);

const currentSchoolSummary = computed(() => {
    const school = currentSchool.value;
    if (!school) return null;

    const directorate = school.directorate || {};

    return {
        name: school.name || 'غير محدد',
        code: school.school_id || 'غير متوفر',
        country: directorate.country?.name || selectedCountryName.value || 'غير محددة',
        governorate: directorate.governorate_model?.name || directorate.governorate || 'غير محددة',
        educationType: directorate.education_type?.name || selectedEducationTypeName.value || directorate.name || 'غير محدد',
        defaultTemplate: school.default_template?.name || selectedTemplate.value?.template_name || 'غير محدد',
        schoolType: school.school_type_label || 'غير محدد',
        educationStages: Array.isArray(school.education_stages)
            ? school.education_stages.map((stage) => stage.name).filter(Boolean)
            : [],
        status: school.status || '',
        supervisionStatus: school.supervision_status || '',
        logoUrl: school.logo_url || (school.logo_path ? `/media-files/${school.logo_path}` : ''),
    };
});

const resolvedSchoolLogoUrl = computed(() => logoPreviewUrl.value || currentSchoolSummary.value?.logoUrl || '');

const persistButtonLabel = computed(() => {
    if (creatingSchool.value) return hasCurrentSchool.value ? 'جارٍ حفظ التعديلات...' : 'جارٍ إضافة المدرسة...';

    return hasCurrentSchool.value ? 'حفظ تعديلات المدرسة' : 'إضافة المدرسة';
});

const formSectionTitle = computed(() =>
    hasCurrentSchool.value ? 'تعديل بيانات المدرسة الحالية' : 'إضافة مدرسة جديدة');

const formSectionDescription = computed(() =>
    hasCurrentSchool.value
        ? 'تم ربط حسابك بهذه المدرسة. يمكنك تعديل بياناتها من النموذج التالي، مع إمكانية استبدال الشعار الحالي عند الحاجة.'
        : 'اختر الدولة ثم نوع التعليم ثم القالب المطابق أولًا، وبعدها أدخل بيانات مدرستك ليتم إنشاؤها وتطبيق البيانات الافتراضية المختارة داخل نطاقها المدرسي.',
);

const statusLabel = (status) => {
    if (status === 'ACTIVE') return 'نشطة';
    if (status === 'SUSPENDED') return 'معلقة';

    return status || 'غير محددة';
};

const supervisionStatusLabel = (status) => {
    if (status === 'ACTIVE_ASSOCIATION') return 'ارتباط نشط';
    if (status === 'WAITING_MANAGER_APPROVAL') return 'بانتظار موافقة المدير';
    if (status === 'WAITING_SUPERVISOR_CONFIRM') return 'بانتظار تأكيد المشرف';
    if (status === 'SUSPENDED') return 'معلقة';

    return status || 'غير محددة';
};

const parseRequestError = (requestError, fallbackMessage) =>
    requestError?.response?.data?.message
    || Object.values(requestError?.response?.data?.errors || {}).flat().join(' | ')
    || fallbackMessage;

const resetCreateErrors = () => {
    createErrors.value = {};
};

const revokeLogoPreview = () => {
    if (logoPreviewUrl.value && logoPreviewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreviewUrl.value);
    }

    logoPreviewUrl.value = '';
};

const clearSelectedLogo = () => {
    revokeLogoPreview();
    createForm.value.logo = null;
    delete createErrors.value.logo;
    logoInputKey.value += 1;
};

const fillSchoolForm = (school = null) => {
    createForm.value = {
        name: school?.name || '',
        phone: school?.phone || '',
        email: school?.email || '',
        address: school?.address || '',
        notes: school?.notes || '',
        logo: null,
    };
};

const resetTemplateState = () => {
    availableTemplates.value = [];
    selectedTemplateKey.value = '';
    templatesError.value = '';
    templatesLoading.value = false;
};

const resetGovernorateState = () => {
    governorates.value = [];
    selectedGovernorateId.value = '';
    governoratesError.value = '';
    governoratesLoading.value = false;
};

const normalizeCreateForm = () => {
    createForm.value.name = String(createForm.value.name || '').trim();
    createForm.value.phone = sanitizeSaudiMobileValue(createForm.value.phone || '');
    createForm.value.email = sanitizeEmailValue(createForm.value.email || '');
    createForm.value.address = String(createForm.value.address || '').trim();
    createForm.value.notes = String(createForm.value.notes || '').trim();
};

const syncSelectorsFromSchool = (school) => {
    const directorate = school?.directorate;
    selectedTemplateKey.value = school?.default_template?.key || '';
    selectedSchoolType.value = school?.school_type || '';
    selectedEducationStageIds.value = Array.isArray(school?.education_stages)
        ? school.education_stages.map((stage) => Number(stage.id)).filter((id) => id > 0)
        : [];
    if (!directorate) return;

    selectedCountryId.value = directorate.country_id ? Number(directorate.country_id) : '';
    selectedGovernorateId.value = directorate.governorate_id ? Number(directorate.governorate_id) : '';
    selectedEducationTypeId.value = directorate.education_type_id ? Number(directorate.education_type_id) : '';
};

const syncCurrentSchool = (school) => {
    currentSchool.value = school || null;

    if (school?.directorate) {
        syncSelectorsFromSchool(school);
    } else {
        resetTemplateState();
    }

    fillSchoolForm(school);
    clearSelectedLogo();
};

const startEditingCurrentSchool = () => {
    if (!currentSchool.value) return;

    fillSchoolForm(currentSchool.value);
    clearSelectedLogo();
    resetCreateErrors();
    error.value = '';
    message.value = 'يمكنك الآن تعديل بيانات المدرسة الحالية ثم حفظها.';
    schoolDetailsSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const handleLogoChange = (event) => {
    const [file] = event.target.files || [];
    revokeLogoPreview();
    createForm.value.logo = null;
    delete createErrors.value.logo;

    if (!file) return;
    if (!ALLOWED_LOGO_TYPES.includes(file.type)) {
        createErrors.value.logo = 'اختر صورة شعار بصيغة JPG أو PNG أو WebP.';
        logoInputKey.value += 1;
        return;
    }
    if (file.size > MAX_LOGO_SIZE_BYTES) {
        createErrors.value.logo = 'حجم شعار المدرسة يجب ألا يتجاوز 2 ميجابايت.';
        logoInputKey.value += 1;
        return;
    }

    createForm.value.logo = file;
    logoPreviewUrl.value = URL.createObjectURL(file);
};

const validateSchoolForm = () => {
    normalizeCreateForm();
    resetCreateErrors();

    const errors = {};
    if (!hasCurrentSchool.value) {
        if (!selectedCountryId.value) errors.country_id = 'اختر الدولة أولًا.';
        if (!selectedGovernorateId.value) errors.governorate_id = 'اختر المحافظة أولًا.';
        if (!selectedEducationTypeId.value) errors.education_type_id = 'اختر نوع التعليم أولًا.';
        if (!selectedTemplateKey.value) errors.template_key = 'اختر قالبًا افتراضيًا مناسبًا قبل إنشاء المدرسة.';
        if (!selectedSchoolType.value) errors.school_type = 'اختر نوع المدرسة أولًا.';
        if (!selectedEducationStageIds.value.length) errors.education_stage_ids = 'اختر مرحلة تعليمية واحدة على الأقل.';
    }

    if (createForm.value.name === '') errors.name = 'اسم المدرسة مطلوب.';
    else if (createForm.value.name.length > 255) errors.name = 'اسم المدرسة يجب ألا يتجاوز 255 حرفًا.';

    if (createForm.value.phone === '') errors.phone = 'رقم الجوال مطلوب.';
    else if (!isValidSaudiMobile(createForm.value.phone)) errors.phone = 'استخدم رقم جوال سعودي صحيح مثل 05xxxxxxxx أو +9665xxxxxxxx.';

    if (createForm.value.email !== '' && createForm.value.email.length > 255) errors.email = 'البريد الإلكتروني يجب ألا يتجاوز 255 حرفًا.';
    else if (createForm.value.email !== '' && !EMAIL_REGEX.test(createForm.value.email)) errors.email = 'صيغة البريد الإلكتروني غير صحيحة.';

    if (createForm.value.address.length > 500) errors.address = 'العنوان يجب ألا يتجاوز 500 حرف.';
    if (createForm.value.notes.length > 2000) errors.notes = 'الملاحظات يجب ألا تتجاوز 2000 حرف.';

    if (createForm.value.logo) {
        if (!ALLOWED_LOGO_TYPES.includes(createForm.value.logo.type)) errors.logo = 'اختر صورة شعار بصيغة JPG أو PNG أو WebP.';
        else if (createForm.value.logo.size > MAX_LOGO_SIZE_BYTES) errors.logo = 'حجم شعار المدرسة يجب ألا يتجاوز 2 ميجابايت.';
    }

    createErrors.value = errors;
    return Object.keys(errors).length === 0;
};

const loadRegions = async () => {
    const response = await axios.get(route('manager.onboarding.regions'));
    const payload = response.data || {};

    countries.value = Array.isArray(payload.countries) ? payload.countries : [];
    governorates.value = Array.isArray(payload.governorates) ? payload.governorates : [];
    educationTypes.value = Array.isArray(payload.educationTypes) ? payload.educationTypes : [];
    educationStages.value = Array.isArray(payload.educationStages) ? payload.educationStages : [];

    if (props.currentSchool?.directorate) {
        syncSelectorsFromSchool(props.currentSchool);
    }
};

const loadGovernorates = async () => {
    if (hasCurrentSchool.value || !selectedCountryId.value) {
        resetGovernorateState();
        return;
    }

    const requestId = governorateRequestId.value + 1;
    governorateRequestId.value = requestId;
    governoratesLoading.value = true;
    governoratesError.value = '';
    governorates.value = [];

    try {
        const response = await axios.get(route('manager.onboarding.governorates'), {
            params: {
                country_id: selectedCountryId.value,
            },
        });

        if (governorateRequestId.value !== requestId) return;

        governorates.value = Array.isArray(response.data?.governorates)
            ? response.data.governorates
            : [];

        if (!governorates.value.some((governorate) => Number(governorate.id) === Number(selectedGovernorateId.value))) {
            selectedGovernorateId.value = '';
        }

        governoratesError.value = response.data?.message || '';
    } catch (requestError) {
        if (governorateRequestId.value !== requestId) return;

        governorates.value = [];
        selectedGovernorateId.value = '';
        governoratesError.value = parseRequestError(requestError, 'تعذر تحميل محافظات هذه الدولة الآن.');
    } finally {
        if (governorateRequestId.value === requestId) {
            governoratesLoading.value = false;
        }
    }
};

const loadTemplateOptions = async () => {
    if (hasCurrentSchool.value || !selectedCountryId.value || !selectedEducationTypeId.value) {
        resetTemplateState();
        return;
    }

    const requestId = templateRequestId.value + 1;
    templateRequestId.value = requestId;
    templatesLoading.value = true;
    templatesError.value = '';
    availableTemplates.value = [];

    try {
        const response = await axios.get(route('manager.onboarding.templates'), {
            params: {
                country_id: selectedCountryId.value,
                education_type_id: selectedEducationTypeId.value,
            },
        });

        if (templateRequestId.value !== requestId) return;

        availableTemplates.value = Array.isArray(response.data?.templates)
            ? response.data.templates.map((template) => ({
                ...template,
                key: String(template.key || ''),
            })).filter((template) => template.key !== '')
            : [];

        if (!availableTemplates.value.some((template) => template.key === selectedTemplateKey.value)) {
            selectedTemplateKey.value = '';
        }

        templatesError.value = response.data?.message || '';
    } catch (requestError) {
        if (templateRequestId.value !== requestId) return;

        availableTemplates.value = [];
        selectedTemplateKey.value = '';
        templatesError.value = parseRequestError(requestError, 'تعذر تحميل القوالب المطابقة الآن.');
    } finally {
        if (templateRequestId.value === requestId) {
            templatesLoading.value = false;
        }
    }
};

const buildSchoolPayload = () => {
    const payload = new FormData();
    payload.append('name', createForm.value.name);
    payload.append('phone', createForm.value.phone);
    payload.append('email', createForm.value.email || '');
    payload.append('address', createForm.value.address || '');
    payload.append('notes', createForm.value.notes || '');
    if (!hasCurrentSchool.value) {
        payload.append('country_id', selectedCountryId.value);
        payload.append('governorate_id', selectedGovernorateId.value);
        payload.append('education_type_id', selectedEducationTypeId.value);
        payload.append('template_key', selectedTemplateKey.value);
        payload.append('school_type', selectedSchoolType.value);
        selectedEducationStageIds.value.forEach((stageId) => payload.append('education_stage_ids[]', String(stageId)));
    }
    if (createForm.value.logo instanceof File) payload.append('logo', createForm.value.logo);
    if (hasCurrentSchool.value) payload.append('_method', 'PUT');

    return payload;
};

const persistSchool = async () => {
    error.value = '';
    message.value = '';

    if (!validateSchoolForm()) {
        error.value = createErrors.value.country_id
            || createErrors.value.governorate_id
            || createErrors.value.education_type_id
            || createErrors.value.school_type
            || createErrors.value.education_stage_ids
            || createErrors.value.logo
            || 'يرجى تصحيح الحقول المظللة ثم إعادة المحاولة.';
        return;
    }

    creatingSchool.value = true;
    const wasEditing = hasCurrentSchool.value;
    try {
        const response = await axios.post(
            wasEditing ? route('manager.onboarding.schools.update', currentSchool.value.id) : route('manager.onboarding.schools.store'),
            buildSchoolPayload(),
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        syncCurrentSchool(response.data.school);
        resetCreateErrors();
        message.value = wasEditing
            ? `تم تحديث بيانات المدرسة: ${response.data.school?.name || ''}`
            : `تم إنشاء المدرسة وتطبيق القالب الافتراضي بنجاح: ${response.data.school?.name || ''}`;
    } catch (requestError) {
        createErrors.value = requestError?.response?.data?.errors || {};
        error.value = parseRequestError(requestError, wasEditing ? 'تعذر تحديث بيانات المدرسة.' : 'تعذر إنشاء المدرسة.');
    } finally {
        creatingSchool.value = false;
    }
};

watch(selectedCountryId, async (countryId, previousCountryId) => {
    if (hasCurrentSchool.value) return;

    resetCreateErrors();

    if (Number(countryId || 0) !== Number(previousCountryId || 0)) {
        selectedGovernorateId.value = '';
        selectedTemplateKey.value = '';
    }

    if (!countryId) {
        resetGovernorateState();
        availableTemplates.value = [];
        templatesError.value = '';
        templatesLoading.value = false;
        return;
    }

    await loadGovernorates();
});

watch([selectedCountryId, selectedEducationTypeId], async ([countryId, educationTypeId], [previousCountryId, previousEducationTypeId]) => {
    if (hasCurrentSchool.value) return;

    resetCreateErrors();

    if (
        Number(countryId || 0) !== Number(previousCountryId || 0)
        || Number(educationTypeId || 0) !== Number(previousEducationTypeId || 0)
    ) {
        selectedTemplateKey.value = '';
    }

    if (!countryId || !educationTypeId) {
        availableTemplates.value = [];
        templatesError.value = '';
        templatesLoading.value = false;
        return;
    }

    await loadTemplateOptions();
});

onMounted(async () => {
    await loadRegions();

    if (props.currentSchool) {
        syncCurrentSchool(props.currentSchool);
        return;
    }

    fillSchoolForm();
});

onBeforeUnmount(() => revokeLogoPreview());
</script>

<template>
    <Head title="تهيئة مدير المدرسة" />

    <RoleLayout title="تهيئة مدير المدرسة" role="SCHOOL_MANAGER">
        <div class="ui-page-shell manager-onboarding-shell mx-auto grid w-full max-w-[88rem] gap-5" :class="{ 'manager-onboarding-shell--light': isLightMode }">
            <section class="ui-page-hero manager-onboarding-hero overflow-hidden rounded-[28px] border border-white/10 bg-slate-900/85 shadow-[0_25px_80px_rgba(2,6,23,0.25)]">
                <div class="manager-onboarding-hero-inner bg-gradient-to-l from-cyan-500/10 via-transparent to-blue-500/10 p-5 md:p-6">
                    <div class="ui-page-header flex flex-col gap-4 lg:flex-row-reverse lg:items-start lg:justify-between">
                        <div class="ui-page-heading space-y-3 text-right">
                            <span class="ui-page-kicker manager-onboarding-badge inline-flex rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">تهيئة مدرسة المدير</span>
                            <div>
                                <h2 class="ui-page-title text-right text-xl font-extrabold text-white md:text-2xl">الدولة ونوع التعليم وبيانات المدرسة</h2>
                                <p class="ui-page-copy mt-3 max-w-3xl text-right text-sm leading-8 text-slate-300">
                                    <template v-if="hasCurrentSchool">
                                        هذه هي المدرسة المرتبطة بحسابك الآن، ويمكنك تعديل بياناتها وشعارها من النموذج أدناه دون الحاجة لاختيار مدرسة جاهزة.
                                    </template>
                                    <template v-else>
                                        اختر الدولة ثم نوع التعليم ثم القالب الذي أعده السوبر أدمن داخل صفحة القوالب الافتراضية. بعد ذلك سيُنشئ النظام المدرسة ويطبّق البيانات الافتراضية المطابقة مباشرة داخلها.
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 lg:max-w-sm">
                            <span class="manager-onboarding-pill manager-onboarding-pill--info rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-2 text-xs font-bold text-cyan-100">
                                {{ hasCurrentSchool ? 'مدرسة مرتبطة بالحساب' : 'إضافة المدرسة الأولى' }}
                            </span>
                            <span class="manager-onboarding-pill manager-onboarding-pill--muted rounded-full border border-slate-600/60 bg-slate-800/80 px-3 py-2 text-xs font-bold text-slate-200">
                                {{ hasCurrentSchool ? 'وضع التعديل فقط' : 'خطوة تهيئة أساسية' }}
                            </span>
                        </div>
                    </div>

                    <AppInlineAlert
                        v-if="shouldShowAccountStatus"
                        :variant="accountStatus.variant || 'info'"
                        class="manager-onboarding-banner mt-5"
                        :message="accountStatus.message"
                    />

                    <div v-if="!hasCurrentSchool" class="manager-onboarding-grid mt-5 grid grid-cols-1 gap-4 lg:grid-cols-4">
                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">الدولة</label>
                            <select v-model="selectedCountryId" name="country_id" data-field-label="الدولة" class="ui-select manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" :disabled="hasCurrentSchool">
                                <option value="" disabled>اختر الدولة</option>
                                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                            </select>
                            <p v-if="createErrors.country_id" class="ui-field-error text-xs text-red-300">{{ createErrors.country_id }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">المحافظة</label>
                            <select
                                v-model="selectedGovernorateId"
                                name="governorate_id"
                                data-field-label="المحافظة"
                                class="ui-select manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white"
                                :disabled="!selectedCountryId || governoratesLoading || availableGovernorates.length === 0 || hasCurrentSchool"
                            >
                                <option value="" disabled>
                                    {{ governoratesLoading ? 'جارٍ تحميل المحافظات...' : 'اختر المحافظة' }}
                                </option>
                                <option v-for="governorate in availableGovernorates" :key="governorate.id" :value="governorate.id">{{ governorate.name }}</option>
                            </select>
                            <p v-if="createErrors.governorate_id" class="ui-field-error text-xs text-red-300">{{ createErrors.governorate_id }}</p>
                            <p v-else-if="governoratesError" class="ui-field-error text-xs text-red-300">{{ governoratesError }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">نوع التعليم</label>
                            <select v-model="selectedEducationTypeId" name="education_type_id" data-field-label="نوع التعليم" class="ui-select manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" :disabled="!selectedCountryId || !selectedGovernorateId || hasCurrentSchool">
                                <option value="" disabled>اختر نوع التعليم</option>
                                <option v-for="educationType in availableEducationTypes" :key="educationType.id" :value="educationType.id">{{ educationType.name }}</option>
                            </select>
                            <p v-if="createErrors.education_type_id" class="ui-field-error text-xs text-red-300">{{ createErrors.education_type_id }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">القالب الافتراضي</label>
                            <select
                                v-model="selectedTemplateKey"
                                name="template_key"
                                data-field-label="القالب الافتراضي"
                                class="ui-select manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white"
                                :disabled="!selectedCountryId || !selectedGovernorateId || !selectedEducationTypeId || templatesLoading || availableTemplates.length === 0 || hasCurrentSchool"
                            >
                                <option value="" disabled>
                                    {{ templatesLoading ? 'جارٍ تحميل القوالب المطابقة...' : 'اختر القالب المناسب' }}
                                </option>
                                <option v-for="template in availableTemplates" :key="template.key" :value="template.key">
                                    {{ template.template_name }}
                                </option>
                            </select>
                            <p v-if="createErrors.template_key" class="ui-field-error text-xs text-red-300">{{ createErrors.template_key }}</p>
                            <p v-else-if="selectedTemplate" class="text-xs leading-6 text-slate-400">
                                سيتم تطبيق {{ selectedTemplate.template_name }} على المدرسة الجديدة عند الحفظ.
                            </p>
                        </div>
                    </div>

                    <AppInlineAlert
                        v-if="!hasCurrentSchool && selectedCountryId && governoratesLoading"
                        variant="info"
                        class="manager-onboarding-banner mt-4"
                        message="جارٍ تحميل المحافظات التابعة للدولة المختارة."
                    />

                    <AppInlineAlert
                        v-else-if="!hasCurrentSchool && selectedCountryId && !governoratesLoading && !governoratesError && availableGovernorates.length === 0"
                        variant="warning"
                        class="manager-onboarding-banner manager-onboarding-banner--warning mt-4"
                        message="لا توجد محافظات متاحة لهذه الدولة حاليًا."
                    />

                    <AppInlineAlert
                        v-if="!hasCurrentSchool && selectedCountryId && availableEducationTypes.length === 0"
                        variant="warning"
                        class="manager-onboarding-banner manager-onboarding-banner--warning mt-4"
                        message="لا توجد أنواع تعليم مركزية متاحة حاليًا. يرجى مراجعة السوبر أدمن لإضافة نوع التعليم المناسب داخل صفحة القوالب الافتراضية."
                    />

                    <AppInlineAlert
                        v-if="!hasCurrentSchool && selectedCountryId && selectedEducationTypeId && templatesLoading"
                        variant="info"
                        class="manager-onboarding-banner mt-4"
                        message="جارٍ تحميل القوالب المطابقة للدولة ونوع التعليم المختارين."
                    />

                    <AppInlineAlert
                        v-else-if="!hasCurrentSchool && selectedCountryId && selectedEducationTypeId && templatesError"
                        variant="danger"
                        class="manager-onboarding-banner mt-4"
                        :message="templatesError"
                    />

                    <AppInlineAlert
                        v-else-if="!hasCurrentSchool && selectedCountryId && selectedEducationTypeId && availableTemplates.length === 0"
                        variant="warning"
                        class="manager-onboarding-banner manager-onboarding-banner--warning mt-4"
                        message="لا توجد قوالب افتراضية مطابقة لهذه الدولة ونوع التعليم حاليًا."
                    />

                    <AppInlineAlert
                        v-if="hasCurrentSchool"
                        variant="success"
                        class="manager-onboarding-banner manager-onboarding-banner--success mt-4"
                        message="تمت إضافة المدرسة الأولى لهذا الحساب. لم تعد حقول الإضافة متاحة هنا، ويمكنك فقط تعديل بيانات المدرسة الحالية."
                    />

                    <div v-if="!hasCurrentSchool" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                        <div class="ui-card-soft manager-onboarding-school-type-panel rounded-[24px] border border-white/10 bg-slate-800/55 p-4 text-right">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-cyan-300">نوع المدرسة</p>
                                    <h3 class="mt-2 text-base font-extrabold text-white">اختر طبيعة المدرسة الجديدة</h3>
                                    <p class="mt-2 text-sm leading-7 text-slate-300">هذا الاختيار مستقل عن نوع التعليم، ويُحفَظ كخاصية ثابتة على المدرسة نفسها.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <label
                                    v-for="schoolType in schoolTypeOptions"
                                    :key="schoolType.value"
                                    class="manager-onboarding-type-option flex cursor-pointer flex-col rounded-2xl border px-4 py-4 text-right transition"
                                    :class="selectedSchoolType === schoolType.value
                                        ? 'border-cyan-400/50 bg-cyan-500/10'
                                        : 'border-white/10 bg-slate-950/45 hover:border-cyan-400/25'"
                                >
                                    <input v-model="selectedSchoolType" type="radio" name="school_type" class="sr-only" :value="schoolType.value">
                                    <span class="text-sm font-bold text-white">{{ schoolType.label }}</span>
                                    <span class="mt-2 text-xs leading-6 text-slate-400">{{ schoolType.description }}</span>
                                </label>
                            </div>
                            <p v-if="createErrors.school_type" class="ui-field-error mt-3 text-xs text-red-300">{{ createErrors.school_type }}</p>
                        </div>

                        <div class="ui-card-soft manager-onboarding-stage-panel rounded-[24px] border border-white/10 bg-slate-800/55 p-4 text-right">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-cyan-300">المراحل التعليمية</p>
                                    <h3 class="mt-2 text-base font-extrabold text-white">اختر المراحل المتاحة داخل المدرسة</h3>
                                    <p class="mt-2 text-sm leading-7 text-slate-300">سيُطبّق القالب الافتراضي على البيانات العامة دائمًا، وعلى بيانات المراحل المطابقة لاختياراتك فقط.</p>
                                </div>
                                <span class="rounded-full border border-slate-600/60 bg-slate-900/80 px-3 py-1 text-[11px] font-bold text-slate-200">{{ availableEducationStages.length }} مرحلة</span>
                            </div>

                            <div v-if="availableEducationStages.length" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label
                                    v-for="stage in availableEducationStages"
                                    :key="stage.id"
                                    class="manager-onboarding-stage-option flex cursor-pointer items-start gap-3 rounded-2xl border px-4 py-4 text-right transition"
                                    :class="selectedEducationStageIds.includes(stage.id)
                                        ? 'border-emerald-400/40 bg-emerald-500/10'
                                        : 'border-white/10 bg-slate-950/45 hover:border-emerald-400/25'"
                                >
                                    <input
                                        v-model="selectedEducationStageIds"
                                        type="checkbox"
                                        name="education_stage_ids[]"
                                        class="mt-1 rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-400"
                                        :value="stage.id"
                                    >
                                    <span class="flex-1">
                                        <span class="block text-sm font-bold text-white">{{ stage.name }}</span>
                                        <span class="mt-1 block text-xs leading-6 text-slate-400">ستُنشأ عناصر هذه المرحلة فقط إذا كانت موجودة داخل القالب المختار.</span>
                                    </span>
                                </label>
                            </div>
                            <AppInlineAlert
                                v-else
                                variant="warning"
                                class="manager-onboarding-banner manager-onboarding-banner--warning mt-4"
                                message="لا توجد مراحل تعليمية مفعلة حاليًا. يرجى مراجعة السوبر أدمن لإضافتها من صفحة القوالب الافتراضية."
                            />
                            <p v-if="createErrors.education_stage_ids" class="ui-field-error mt-3 text-xs text-red-300">{{ createErrors.education_stage_ids }}</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/10 p-5 md:p-6">
                    <div class="ui-card-soft manager-onboarding-school-card rounded-[24px] border border-white/10 bg-slate-800/65 p-4 md:p-5">
                        <div class="flex flex-col gap-4 lg:flex-row-reverse lg:items-start lg:justify-between">
                            <div class="flex flex-row-reverse items-start gap-4 text-right">
                                <div class="manager-onboarding-logo-card flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[22px] border border-cyan-400/15 bg-slate-950/70">
                                    <img v-if="resolvedSchoolLogoUrl" :src="resolvedSchoolLogoUrl" alt="شعار المدرسة" class="h-full w-full object-contain p-2">
                                    <span v-else class="text-[11px] font-bold text-slate-500">لا يوجد شعار</span>
                                </div>

                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-slate-400">المدرسة الحالية</p>
                                    <h3 class="text-lg font-extrabold text-white">{{ currentSchoolSummary?.name || 'لم تتم إضافة مدرسة بعد' }}</h3>
                                    <p class="text-sm text-slate-400">{{ currentSchoolSummary?.code || 'سيتم إنشاء رقم المدرسة تلقائيًا بعد الإضافة.' }}</p>
                                </div>
                            </div>

                            <button v-if="hasCurrentSchool" class="ui-secondary-button manager-onboarding-secondary-button inline-flex items-center justify-center rounded-2xl border border-blue-400/25 bg-blue-500/10 px-4 py-3 text-sm font-bold text-blue-200 transition hover:bg-blue-500/20" @click="startEditingCurrentSchool">
                                تعديل البيانات
                            </button>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-8">
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">الدولة</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.country || 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">المحافظة</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.governorate || 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">نوع التعليم</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.educationType || 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">القالب الافتراضي</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.defaultTemplate || 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">نوع المدرسة</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.schoolType || 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4 xl:col-span-2">
                                <p class="text-xs font-semibold text-slate-500">المراحل التعليمية</p>
                                <p class="mt-2 font-bold text-white">{{ currentSchoolSummary?.educationStages?.length ? currentSchoolSummary.educationStages.join('، ') : 'بانتظار الإضافة' }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">حالة المدرسة</p>
                                <p class="mt-2 font-bold text-white">{{ statusLabel(currentSchoolSummary?.status) }}</p>
                            </div>
                            <div class="ui-card-soft manager-onboarding-stat-card rounded-2xl border border-white/8 bg-slate-950/40 p-4">
                                <p class="text-xs font-semibold text-slate-500">حالة الإشراف</p>
                                <p class="mt-2 font-bold text-white">{{ supervisionStatusLabel(currentSchoolSummary?.supervisionStatus) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section ref="schoolDetailsSectionRef" class="ui-form-shell manager-onboarding-form-section overflow-hidden rounded-[28px] border border-white/10 bg-slate-900/85 shadow-[0_25px_80px_rgba(2,6,23,0.22)]">
                <div class="grid gap-5 p-5 md:p-6 xl:grid-cols-[19rem_minmax(0,1fr)]">
                    <div class="ui-section-heading space-y-3 text-right xl:order-2">
                        <span class="ui-page-kicker inline-flex rounded-full border border-blue-400/20 bg-blue-500/10 px-3 py-1 text-xs font-bold text-blue-100">نموذج المدرسة</span>
                        <div>
                            <h3 class="ui-section-title text-xl font-extrabold text-white">{{ formSectionTitle }}</h3>
                            <p class="ui-section-subtitle mt-3 max-w-3xl text-sm leading-8 text-slate-300">{{ formSectionDescription }}</p>
                        </div>
                    </div>

                    <div class="ui-card-soft manager-onboarding-guidance rounded-[24px] border border-white/10 bg-slate-800/55 p-4 text-right xl:order-1 xl:self-start">
                        <p class="text-sm font-extrabold text-white">إرشادات سريعة</p>
                        <ul dir="rtl" class="mt-3 space-y-3 text-sm leading-7 text-slate-300">
                            <li class="flex items-start gap-3 text-right">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-cyan-400" aria-hidden="true" />
                                <span class="flex-1">استخدم اسمًا رسميًا واضحًا للمدرسة.</span>
                            </li>
                            <li class="flex items-start gap-3 text-right">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-cyan-400" aria-hidden="true" />
                                <span class="flex-1">يفضّل أن يكون الشعار مربعًا أو بخلفية شفافة.</span>
                            </li>
                            <li class="flex items-start gap-3 text-right">
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-cyan-400" aria-hidden="true" />
                                <span class="flex-1">يمكن استبدال الشعار لاحقًا من نفس الصفحة.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-white/10 p-5 md:p-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">اسم المدرسة</label>
                            <input v-model="createForm.name" id="manager-onboarding-school-name" name="name" data-field-label="اسم المدرسة" type="text" maxlength="255" autocomplete="organization" class="ui-input manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" placeholder="مثال: مدارس الصفوة">
                            <p v-if="createErrors.name" class="ui-field-error text-xs text-red-300">{{ createErrors.name }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">الجوال</label>
                            <input v-model="createForm.phone" id="manager-onboarding-school-phone" name="phone" data-field-label="رقم الجوال" type="text" inputmode="tel" maxlength="20" autocomplete="tel" class="ui-input manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" placeholder="05xxxxxxxx">
                            <p v-if="createErrors.phone" class="ui-field-error text-xs text-red-300">{{ createErrors.phone }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">البريد الإلكتروني (اختياري)</label>
                            <input v-model="createForm.email" id="manager-onboarding-school-email" name="email" data-field-label="البريد الإلكتروني" type="email" maxlength="255" autocomplete="email" class="ui-input manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" placeholder="school@example.com">
                            <p v-if="createErrors.email" class="ui-field-error text-xs text-red-300">{{ createErrors.email }}</p>
                        </div>

                        <div class="space-y-2 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-400">العنوان (اختياري)</label>
                            <input v-model="createForm.address" id="manager-onboarding-school-address" name="address" data-field-label="العنوان" type="text" maxlength="500" autocomplete="street-address" class="ui-input manager-onboarding-input w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white">
                            <p v-if="createErrors.address" class="ui-field-error text-xs text-red-300">{{ createErrors.address }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                        <div class="ui-card-soft manager-onboarding-preview-panel rounded-[24px] border border-white/10 bg-slate-800/55 p-4">
                            <div class="ui-card-soft manager-onboarding-preview-frame flex min-h-64 items-center justify-center overflow-hidden rounded-[20px] border border-dashed border-white/15 bg-slate-950/65">
                                <img v-if="resolvedSchoolLogoUrl" :src="resolvedSchoolLogoUrl" alt="معاينة شعار المدرسة" class="h-full w-full object-contain p-4">
                                <div v-else class="px-4 text-center text-sm leading-8 text-slate-500">لم يتم اختيار شعار بعد</div>
                            </div>
                            <div class="mt-4 text-right">
                                <p class="text-xs font-semibold text-slate-400">المعاينة الحالية</p>
                                <p class="mt-1 text-sm leading-7 text-slate-400">{{ createForm.logo ? 'هذه معاينة الملف الذي اخترته قبل الحفظ.' : 'سيظهر شعار المدرسة هنا بعد الاختيار أو بعد الحفظ.' }}</p>
                            </div>
                        </div>

                        <div class="ui-card-soft manager-onboarding-upload-panel rounded-[24px] border border-white/10 bg-slate-800/55 p-4 text-right">
                            <label class="ui-field-label block text-xs font-semibold text-slate-300">شعار المدرسة (اختياري)</label>
                            <p class="ui-helper-text mt-3 text-sm leading-8 text-slate-300">
                                ارفع شعارًا واضحًا يساعد على تمييز المدرسة داخل الواجهة والبطاقات. الصيغ المسموحة: JPG وPNG وWebP، وبحجم أقصى 2 ميجابايت.
                            </p>

                            <label class="ui-card-soft manager-onboarding-upload-dropzone mt-4 flex cursor-pointer flex-row-reverse items-center justify-between gap-4 rounded-[22px] border border-dashed border-blue-400/35 bg-slate-950/45 px-4 py-4 text-right transition hover:border-cyan-300/60 hover:bg-slate-950/65">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-l from-cyan-500 to-blue-600 text-2xl font-extrabold text-white">+</span>
                                <span class="grid flex-1 gap-1 text-right">
                                    <strong class="text-sm text-white">اختر شعار المدرسة</strong>
                                    <small class="text-xs text-slate-400">اسحب الملف هنا أو اضغط للاختيار من جهازك</small>
                                </span>
                                <input :key="logoInputKey" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="hidden" @change="handleLogoChange">
                            </label>

                            <div class="mt-4 flex flex-wrap justify-end gap-2">
                                <span class="manager-onboarding-pill manager-onboarding-pill--muted rounded-full border border-slate-600/60 bg-slate-900/80 px-3 py-2 text-xs font-bold text-slate-200">
                                    {{ createForm.logo ? `تم اختيار: ${createForm.logo.name}` : 'لم يتم اختيار ملف بعد' }}
                                </span>
                                <span v-if="currentSchoolSummary?.logoUrl && !createForm.logo" class="manager-onboarding-pill manager-onboarding-pill--success rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-2 text-xs font-bold text-emerald-200">
                                    الشعار الحالي محفوظ ويمكنك استبداله
                                </span>
                            </div>

                            <div class="mt-4 flex flex-wrap justify-end gap-3">
                                <button v-if="createForm.logo" type="button" class="ui-action-button manager-onboarding-remove-button inline-flex items-center justify-center rounded-2xl border border-amber-400/25 bg-amber-400/10 px-4 py-3 text-sm font-bold text-amber-200 transition hover:bg-amber-400/20" @click="clearSelectedLogo">
                                    إزالة الملف المختار
                                </button>
                            </div>

                            <p v-if="createErrors.logo" class="ui-field-error mt-3 text-xs text-red-300">{{ createErrors.logo }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-2 text-right">
                        <label class="ui-field-label block text-xs font-semibold text-slate-400">ملاحظات (اختياري)</label>
                        <textarea v-model="createForm.notes" id="manager-onboarding-school-notes" name="notes" data-field-label="الملاحظات" rows="4" maxlength="2000" class="ui-textarea manager-onboarding-input min-h-32 w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-white" />
                        <p v-if="createErrors.notes" class="ui-field-error text-xs text-red-300">{{ createErrors.notes }}</p>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button class="ui-primary-button manager-onboarding-primary-button inline-flex min-w-52 items-center justify-center rounded-2xl border border-blue-400/30 bg-gradient-to-l from-cyan-500 to-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-[0_18px_35px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="creatingSchool" @click="persistSchool">
                            {{ persistButtonLabel }}
                        </button>
                    </div>
                </div>
            </section>

            <AppInlineAlert
                v-if="message"
                variant="success"
                class="manager-onboarding-feedback manager-onboarding-feedback--success"
                :message="message"
            />
            <AppInlineAlert
                v-if="error"
                variant="danger"
                class="manager-onboarding-feedback manager-onboarding-feedback--error"
                :message="error"
            />
        </div>
    </RoleLayout>
</template>

<style scoped>
.manager-onboarding-shell--light .manager-onboarding-hero,
.manager-onboarding-shell--light .manager-onboarding-form-section,
.manager-onboarding-shell--light .manager-onboarding-school-card,
.manager-onboarding-shell--light .manager-onboarding-guidance,
.manager-onboarding-shell--light .manager-onboarding-preview-panel,
.manager-onboarding-shell--light .manager-onboarding-upload-panel,
.manager-onboarding-shell--light .manager-onboarding-school-type-panel,
.manager-onboarding-shell--light .manager-onboarding-stage-panel,
:global(.role-layout--light) .manager-onboarding-hero,
:global(.role-layout--light) .manager-onboarding-form-section,
:global(.role-layout--light) .manager-onboarding-school-card,
:global(.role-layout--light) .manager-onboarding-guidance,
:global(.role-layout--light) .manager-onboarding-preview-panel,
:global(.role-layout--light) .manager-onboarding-upload-panel,
:global(.role-layout--light) .manager-onboarding-school-type-panel,
:global(.role-layout--light) .manager-onboarding-stage-panel,
:global(html.theme-light) .manager-onboarding-hero,
:global(html.theme-light) .manager-onboarding-form-section,
:global(html.theme-light) .manager-onboarding-school-card,
:global(html.theme-light) .manager-onboarding-guidance,
:global(html.theme-light) .manager-onboarding-preview-panel,
:global(html.theme-light) .manager-onboarding-upload-panel,
:global(html.theme-light) .manager-onboarding-school-type-panel,
:global(html.theme-light) .manager-onboarding-stage-panel,
:global(html[data-theme='light']) .manager-onboarding-hero,
:global(html[data-theme='light']) .manager-onboarding-form-section,
:global(html[data-theme='light']) .manager-onboarding-school-card,
:global(html[data-theme='light']) .manager-onboarding-guidance,
:global(html[data-theme='light']) .manager-onboarding-preview-panel,
:global(html[data-theme='light']) .manager-onboarding-upload-panel,
:global(html[data-theme='light']) .manager-onboarding-school-type-panel,
:global(html[data-theme='light']) .manager-onboarding-stage-panel {
    border-color: rgba(148, 163, 184, 0.34) !important;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.95)) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.82), 0 20px 44px rgba(15, 23, 42, 0.08);
}

.manager-onboarding-shell--light .manager-onboarding-hero-inner,
:global(.role-layout--light) .manager-onboarding-hero-inner,
:global(html.theme-light) .manager-onboarding-hero-inner,
:global(html[data-theme='light']) .manager-onboarding-hero-inner {
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.08), rgba(37, 99, 235, 0.04), transparent 72%) !important;
}

.manager-onboarding-shell--light h2,
.manager-onboarding-shell--light h3,
.manager-onboarding-shell--light strong,
:global(.role-layout--light) .manager-onboarding-shell h2,
:global(.role-layout--light) .manager-onboarding-shell h3,
:global(.role-layout--light) .manager-onboarding-shell strong,
:global(html.theme-light) .manager-onboarding-shell h2,
:global(html.theme-light) .manager-onboarding-shell h3,
:global(html.theme-light) .manager-onboarding-shell strong,
:global(html[data-theme='light']) .manager-onboarding-shell h2,
:global(html[data-theme='light']) .manager-onboarding-shell h3,
:global(html[data-theme='light']) .manager-onboarding-shell strong {
    color: rgb(15 23 42) !important;
}

.manager-onboarding-shell--light p,
.manager-onboarding-shell--light label,
.manager-onboarding-shell--light li,
.manager-onboarding-shell--light small,
:global(.role-layout--light) .manager-onboarding-shell p,
:global(.role-layout--light) .manager-onboarding-shell label,
:global(.role-layout--light) .manager-onboarding-shell li,
:global(.role-layout--light) .manager-onboarding-shell small,
:global(html.theme-light) .manager-onboarding-shell p,
:global(html.theme-light) .manager-onboarding-shell label,
:global(html.theme-light) .manager-onboarding-shell li,
:global(html.theme-light) .manager-onboarding-shell small,
:global(html[data-theme='light']) .manager-onboarding-shell p,
:global(html[data-theme='light']) .manager-onboarding-shell label,
:global(html[data-theme='light']) .manager-onboarding-shell li,
:global(html[data-theme='light']) .manager-onboarding-shell small {
    color: rgb(71 85 105) !important;
}

.manager-onboarding-shell--light .manager-onboarding-pill--info,
.manager-onboarding-shell--light .manager-onboarding-badge,
:global(.role-layout--light) .manager-onboarding-pill--info,
:global(.role-layout--light) .manager-onboarding-badge,
:global(html.theme-light) .manager-onboarding-pill--info,
:global(html.theme-light) .manager-onboarding-badge,
:global(html[data-theme='light']) .manager-onboarding-pill--info,
:global(html[data-theme='light']) .manager-onboarding-badge {
    border-color: rgba(6, 182, 212, 0.26) !important;
    background: rgba(236, 254, 255, 0.96) !important;
    color: rgb(14 116 144) !important;
}

.manager-onboarding-shell--light .manager-onboarding-pill--muted,
:global(.role-layout--light) .manager-onboarding-pill--muted,
:global(html.theme-light) .manager-onboarding-pill--muted,
:global(html[data-theme='light']) .manager-onboarding-pill--muted {
    border-color: rgba(148, 163, 184, 0.34) !important;
    background: rgba(241, 245, 249, 0.96) !important;
    color: rgb(51 65 85) !important;
}

.manager-onboarding-shell--light .manager-onboarding-banner--warning,
:global(.role-layout--light) .manager-onboarding-banner--warning,
:global(html.theme-light) .manager-onboarding-banner--warning,
:global(html[data-theme='light']) .manager-onboarding-banner--warning {
    border-color: rgba(245, 158, 11, 0.22) !important;
    background: rgba(255, 251, 235, 0.98) !important;
    color: rgb(180 83 9) !important;
}

.manager-onboarding-shell--light .manager-onboarding-pill--success,
.manager-onboarding-shell--light .manager-onboarding-banner--success,
.manager-onboarding-shell--light .manager-onboarding-feedback--success,
:global(.role-layout--light) .manager-onboarding-pill--success,
:global(.role-layout--light) .manager-onboarding-banner--success,
:global(.role-layout--light) .manager-onboarding-feedback--success,
:global(html.theme-light) .manager-onboarding-pill--success,
:global(html.theme-light) .manager-onboarding-banner--success,
:global(html.theme-light) .manager-onboarding-feedback--success,
:global(html[data-theme='light']) .manager-onboarding-pill--success,
:global(html[data-theme='light']) .manager-onboarding-banner--success,
:global(html[data-theme='light']) .manager-onboarding-feedback--success {
    border-color: rgba(16, 185, 129, 0.24) !important;
    background: rgba(236, 253, 245, 0.96) !important;
    color: rgb(4 120 87) !important;
}

.manager-onboarding-shell--light .manager-onboarding-feedback--error,
.manager-onboarding-shell--light .manager-onboarding-remove-button,
:global(.role-layout--light) .manager-onboarding-feedback--error,
:global(.role-layout--light) .manager-onboarding-remove-button,
:global(html.theme-light) .manager-onboarding-feedback--error,
:global(html.theme-light) .manager-onboarding-remove-button,
:global(html[data-theme='light']) .manager-onboarding-feedback--error,
:global(html[data-theme='light']) .manager-onboarding-remove-button {
    border-color: rgba(239, 68, 68, 0.22) !important;
    background: rgba(254, 242, 242, 0.98) !important;
    color: rgb(185 28 28) !important;
}

.manager-onboarding-shell--light .manager-onboarding-secondary-button,
:global(.role-layout--light) .manager-onboarding-secondary-button,
:global(html.theme-light) .manager-onboarding-secondary-button,
:global(html[data-theme='light']) .manager-onboarding-secondary-button {
    border-color: rgba(59, 130, 246, 0.24) !important;
    background: rgba(239, 246, 255, 0.98) !important;
    color: rgb(29 78 216) !important;
}

.manager-onboarding-shell--light .manager-onboarding-primary-button,
:global(.role-layout--light) .manager-onboarding-primary-button,
:global(html.theme-light) .manager-onboarding-primary-button,
:global(html[data-theme='light']) .manager-onboarding-primary-button {
    border-color: rgba(37, 99, 235, 0.22) !important;
    box-shadow: 0 16px 30px rgba(37, 99, 235, 0.18) !important;
}

.manager-onboarding-shell--light .manager-onboarding-input,
.manager-onboarding-shell--light select,
.manager-onboarding-shell--light textarea,
.manager-onboarding-shell--light .manager-onboarding-logo-card,
.manager-onboarding-shell--light .manager-onboarding-stat-card,
.manager-onboarding-shell--light .manager-onboarding-preview-frame,
.manager-onboarding-shell--light .manager-onboarding-upload-dropzone,
.manager-onboarding-shell--light .manager-onboarding-type-option,
.manager-onboarding-shell--light .manager-onboarding-stage-option,
:global(.role-layout--light) .manager-onboarding-input,
:global(.role-layout--light) .manager-onboarding-shell select,
:global(.role-layout--light) .manager-onboarding-shell textarea,
:global(.role-layout--light) .manager-onboarding-logo-card,
:global(.role-layout--light) .manager-onboarding-stat-card,
:global(.role-layout--light) .manager-onboarding-preview-frame,
:global(.role-layout--light) .manager-onboarding-upload-dropzone,
:global(.role-layout--light) .manager-onboarding-type-option,
:global(.role-layout--light) .manager-onboarding-stage-option,
:global(html.theme-light) .manager-onboarding-input,
:global(html.theme-light) .manager-onboarding-shell select,
:global(html.theme-light) .manager-onboarding-shell textarea,
:global(html.theme-light) .manager-onboarding-logo-card,
:global(html.theme-light) .manager-onboarding-stat-card,
:global(html.theme-light) .manager-onboarding-preview-frame,
:global(html.theme-light) .manager-onboarding-upload-dropzone,
:global(html.theme-light) .manager-onboarding-type-option,
:global(html.theme-light) .manager-onboarding-stage-option,
:global(html[data-theme='light']) .manager-onboarding-input,
:global(html[data-theme='light']) .manager-onboarding-shell select,
:global(html[data-theme='light']) .manager-onboarding-shell textarea,
:global(html[data-theme='light']) .manager-onboarding-logo-card,
:global(html[data-theme='light']) .manager-onboarding-stat-card,
:global(html[data-theme='light']) .manager-onboarding-preview-frame,
:global(html[data-theme='light']) .manager-onboarding-upload-dropzone,
:global(html[data-theme='light']) .manager-onboarding-type-option,
:global(html[data-theme='light']) .manager-onboarding-stage-option {
    border-color: rgba(148, 163, 184, 0.38) !important;
    background: rgba(255, 255, 255, 0.96) !important;
    color: rgb(15 23 42) !important;
}

.manager-onboarding-shell--light input::placeholder,
.manager-onboarding-shell--light textarea::placeholder,
:global(.role-layout--light) .manager-onboarding-shell input::placeholder,
:global(.role-layout--light) .manager-onboarding-shell textarea::placeholder,
:global(html.theme-light) .manager-onboarding-shell input::placeholder,
:global(html.theme-light) .manager-onboarding-shell textarea::placeholder,
:global(html[data-theme='light']) .manager-onboarding-shell input::placeholder,
:global(html[data-theme='light']) .manager-onboarding-shell textarea::placeholder {
    color: rgb(148 163 184) !important;
}

.manager-onboarding-shell--light input:focus,
.manager-onboarding-shell--light select:focus,
.manager-onboarding-shell--light textarea:focus,
.manager-onboarding-shell--light .manager-onboarding-upload-dropzone:hover,
:global(.role-layout--light) .manager-onboarding-shell input:focus,
:global(.role-layout--light) .manager-onboarding-shell select:focus,
:global(.role-layout--light) .manager-onboarding-shell textarea:focus,
:global(.role-layout--light) .manager-onboarding-upload-dropzone:hover,
:global(html.theme-light) .manager-onboarding-shell input:focus,
:global(html.theme-light) .manager-onboarding-shell select:focus,
:global(html.theme-light) .manager-onboarding-shell textarea:focus,
:global(html.theme-light) .manager-onboarding-upload-dropzone:hover,
:global(html[data-theme='light']) .manager-onboarding-shell input:focus,
:global(html[data-theme='light']) .manager-onboarding-shell select:focus,
:global(html[data-theme='light']) .manager-onboarding-shell textarea:focus,
:global(html[data-theme='light']) .manager-onboarding-upload-dropzone:hover {
    border-color: rgba(59, 130, 246, 0.42) !important;
    background: rgba(255, 255, 255, 1) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
}

.manager-onboarding-shell--light .text-white,
:global(.role-layout--light) .manager-onboarding-shell .text-white,
:global(html.theme-light) .manager-onboarding-shell .text-white,
:global(html[data-theme='light']) .manager-onboarding-shell .text-white {
    color: rgb(15 23 42) !important;
}

.manager-onboarding-shell--light .text-slate-300,
.manager-onboarding-shell--light .text-slate-400,
.manager-onboarding-shell--light .text-slate-500,
:global(.role-layout--light) .manager-onboarding-shell .text-slate-300,
:global(.role-layout--light) .manager-onboarding-shell .text-slate-400,
:global(.role-layout--light) .manager-onboarding-shell .text-slate-500,
:global(html.theme-light) .manager-onboarding-shell .text-slate-300,
:global(html.theme-light) .manager-onboarding-shell .text-slate-400,
:global(html.theme-light) .manager-onboarding-shell .text-slate-500,
:global(html[data-theme='light']) .manager-onboarding-shell .text-slate-300,
:global(html[data-theme='light']) .manager-onboarding-shell .text-slate-400,
:global(html[data-theme='light']) .manager-onboarding-shell .text-slate-500 {
    color: rgb(100 116 139) !important;
}
</style>

