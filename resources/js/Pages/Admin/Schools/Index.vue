<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    Building2,
    Globe2,
    GraduationCap,
    MapPinned,
    PenSquare,
    Plus,
    School,
    Trash2,
} from 'lucide-vue-next';
import AppModal from '@/Components/AppModal.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    directorates: { type: Array, default: () => [] },
    countries: { type: Array, default: () => [] },
    governorates: { type: Array, default: () => [] },
    educationTypes: { type: Array, default: () => [] },
});

const page = usePage();
const actionDialog = useActionDialog();

const isEducationTypeModalOpen = ref(false);
const isDirectorateModalOpen = ref(false);
const editingEducationTypeId = ref(null);
const editingDirectorateId = ref(null);

const educationTypeForm = useForm({ name: '' });
const directorateForm = useForm({ country_id: '', governorate_id: '', education_type_id: '' });
const directorateGovernoratesSyncing = ref(false);
const directorateGovernoratesSyncError = ref('');
const directorateGovernoratesRequestId = ref(0);
const syncedGovernorateCountryIds = ref([]);

const pageErrors = computed(() => page.props.errors || {});

const directorateGovernorates = computed(() => {
    if (!directorateForm.country_id) return props.governorates;
    return props.governorates.filter((governorate) => Number(governorate.country_id) === Number(directorateForm.country_id));
});

const educationTypeUsageMap = computed(() => {
    const usageMap = new Map();

    props.directorates.forEach((directorate) => {
        const educationTypeId = Number(directorate.education_type_id || 0);
        if (!educationTypeId) return;

        usageMap.set(educationTypeId, (usageMap.get(educationTypeId) || 0) + 1);
    });

    return usageMap;
});

const educationTypeRows = computed(() => props.educationTypes.map((educationType) => ({
    ...educationType,
    directorateCount: educationTypeUsageMap.value.get(Number(educationType.id)) || 0,
})));

const totalLinkedSchools = computed(() => props.directorates.reduce(
    (total, directorate) => total + (Array.isArray(directorate.schools) ? directorate.schools.length : 0),
    0,
));

const activeDirectoratesCount = computed(() => props.directorates.filter(
    (directorate) => Array.isArray(directorate.schools) && directorate.schools.length > 0,
).length);

const anyPageErrors = computed(() => Boolean(
    pageErrors.value.country
    || pageErrors.value.governorate
    || pageErrors.value.education_type
    || pageErrors.value.directorate,
));

const educationTypeFormTitle = computed(() => (
    editingEducationTypeId.value ? 'تعديل نوع التعليم' : 'إضافة نوع تعليم جديد'
));

const educationTypeFormDescription = computed(() => (
    editingEducationTypeId.value
        ? 'حدّث اسم النوع الحالي مع الحفاظ على الروابط القائمة داخل النطاقات التعليمية.'
        : 'أضف أنواع التعليم التي ستُستخدم لاحقًا في ربط المدارس بالنطاقات التعليمية.'
));

const directorateFormTitle = computed(() => (
    editingDirectorateId.value ? 'تعديل نطاق تعليمي' : 'إضافة نطاق تعليمي جديد'
));

const directorateFormDescription = computed(() => (
    editingDirectorateId.value
        ? 'حدّث الدولة أو المحافظة أو نوع التعليم مع الحفاظ على الاتساق داخل النطاق.'
        : 'اربط بين الدولة والمحافظة ونوع التعليم لإنشاء نطاق متاح للمدارس في التهيئة.'
));

watch(() => directorateForm.country_id, async (value) => {
    const stillValid = directorateGovernorates.value.some((governorate) => Number(governorate.id) === Number(directorateForm.governorate_id));
    if (!stillValid) directorateForm.governorate_id = '';

    directorateGovernoratesSyncError.value = '';

    if (!value) return;

    if (directorateGovernorates.value.length === 0) {
        await ensureDirectorateGovernorates(value);
    }
});

const resetEducationTypeForm = () => {
    editingEducationTypeId.value = null;
    educationTypeForm.reset();
    educationTypeForm.clearErrors();
};

const resetDirectorateForm = () => {
    editingDirectorateId.value = null;
    directorateForm.reset();
    directorateForm.clearErrors();
    directorateGovernoratesSyncError.value = '';
};

const openCreateEducationTypeModal = () => {
    resetEducationTypeForm();
    isEducationTypeModalOpen.value = true;
};

const closeEducationTypeModal = () => {
    isEducationTypeModalOpen.value = false;
    resetEducationTypeForm();
};

const openCreateDirectorateModal = () => {
    resetDirectorateForm();
    isDirectorateModalOpen.value = true;
};

const closeDirectorateModal = () => {
    isDirectorateModalOpen.value = false;
    resetDirectorateForm();
};

const reloadDirectorateTaxonomy = async () => {
    await router.reload({
        preserveState: true,
        preserveScroll: true,
        only: ['countries', 'governorates', 'directorates'],
    });
};

const ensureDirectorateGovernorates = async (countryId) => {
    const normalizedCountryId = Number(countryId || 0);
    if (!normalizedCountryId) return;

    const alreadyAvailable = props.governorates.some((governorate) => Number(governorate.country_id) === normalizedCountryId);
    if (alreadyAvailable) return;

    if (syncedGovernorateCountryIds.value.includes(normalizedCountryId)) return;

    const requestId = ++directorateGovernoratesRequestId.value;
    directorateGovernoratesSyncing.value = true;
    directorateGovernoratesSyncError.value = '';

    try {
        await axios.post(
            route('admin.governorates.sync_global'),
            { country_id: normalizedCountryId },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (requestId !== directorateGovernoratesRequestId.value) return;

        syncedGovernorateCountryIds.value.push(normalizedCountryId);
        await reloadDirectorateTaxonomy();

        const governorateStillValid = directorateGovernorates.value.some((governorate) => Number(governorate.id) === Number(directorateForm.governorate_id));
        if (!governorateStillValid) {
            directorateForm.governorate_id = '';
        }
    } catch (requestError) {
        if (requestId !== directorateGovernoratesRequestId.value) return;

        directorateGovernoratesSyncError.value = requestError?.response?.data?.errors?.country_id?.[0]
            || requestError?.response?.data?.message
            || 'تعذر تحميل محافظات هذه الدولة حاليًا.';
    } finally {
        if (requestId === directorateGovernoratesRequestId.value) {
            directorateGovernoratesSyncing.value = false;
        }
    }
};

const submitEducationType = () => {
    const endpoint = editingEducationTypeId.value
        ? route('admin.education_types.update', editingEducationTypeId.value)
        : route('admin.education_types.store');

    const options = {
        preserveScroll: true,
        onSuccess: () => closeEducationTypeModal(),
    };

    if (editingEducationTypeId.value) {
        educationTypeForm.put(endpoint, options);
        return;
    }

    educationTypeForm.post(endpoint, options);
};

const submitDirectorate = () => {
    const endpoint = editingDirectorateId.value
        ? route('admin.directorates.update', editingDirectorateId.value)
        : route('admin.directorates.store');

    const options = {
        preserveScroll: true,
        onSuccess: () => closeDirectorateModal(),
    };

    if (editingDirectorateId.value) {
        directorateForm.put(endpoint, options);
        return;
    }

    directorateForm.post(endpoint, options);
};

const startEducationTypeEdit = (educationType) => {
    editingEducationTypeId.value = educationType.id;
    educationTypeForm.name = educationType.name || '';
    educationTypeForm.clearErrors();
    isEducationTypeModalOpen.value = true;
};

const startDirectorateEdit = (directorate) => {
    editingDirectorateId.value = directorate.id;
    directorateForm.country_id = directorate.country_id || '';
    directorateForm.governorate_id = directorate.governorate_id || '';
    directorateForm.education_type_id = directorate.education_type_id || '';
    directorateForm.clearErrors();
    directorateGovernoratesSyncError.value = '';
    isDirectorateModalOpen.value = true;
};

const getSchoolCount = (directorate) => (Array.isArray(directorate.schools) ? directorate.schools.length : 0);

const destroyWithConfirmation = async (title, message, endpoint) => {
    const confirmed = await actionDialog.confirm({
        title,
        message,
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(endpoint, { preserveScroll: true });
};
</script>

<template>
    <Head title="إعدادات أنواع التعليم والنطاقات التعليمية" />

    <AdminLayout>
        <div class="education-settings-page space-y-6 lg:space-y-8" dir="rtl">
            <section class="education-settings-hero overflow-hidden rounded-[2rem] border border-cyan-900/40 bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.16),transparent_34%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(15,23,42,0.96))] p-6 text-right shadow-[0_30px_90px_-50px_rgba(34,211,238,0.45)] sm:p-7">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-4xl">
                        <p class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                            <Globe2 class="h-4 w-4" />
                            <span>إعدادات مرجعية للمنصة التعليمية</span>
                        </p>
                        <h1 class="mt-4 text-2xl font-black leading-tight text-white sm:text-3xl">أنواع التعليم والنطاقات التعليمية</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300 sm:text-[15px]">
                            تُستخدم هذه الصفحة لبناء المرجعيات الأساسية التي تعتمد عليها تهيئة المدارس. تُدار أنواع التعليم أولًا، ثم تُربط بالدول والمحافظات ضمن نطاقات تعليمية واضحة
                            ومهيأة للاستخدام اليومي داخل النظام.
                        </p>
                    </div>

                    <div class="grid min-w-full gap-3 sm:grid-cols-3 xl:min-w-[420px] xl:max-w-[460px]">
                        <div class="education-settings-stat rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                            <p class="text-xs font-semibold text-slate-400">أنواع التعليم</p>
                            <p class="mt-2 text-2xl font-black text-white">{{ educationTypes.length }}</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">الأنواع المتاحة للاستخدام داخل النطاقات.</p>
                        </div>
                        <div class="education-settings-stat rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                            <p class="text-xs font-semibold text-slate-400">النطاقات التعليمية</p>
                            <p class="mt-2 text-2xl font-black text-white">{{ directorates.length }}</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">روابط الدولة والمحافظة ونوع التعليم.</p>
                        </div>
                        <div class="education-settings-stat rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                            <p class="text-xs font-semibold text-slate-400">المدارس المرتبطة</p>
                            <p class="mt-2 text-2xl font-black text-white">{{ totalLinkedSchools }}</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">ضمن {{ activeDirectoratesCount }} نطاقًا نشطًا حتى الآن.</p>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="anyPageErrors" class="ui-feedback-stack">
                <AppInlineAlert v-if="pageErrors.country" variant="danger" :message="pageErrors.country" />
                <AppInlineAlert v-if="pageErrors.governorate" variant="danger" :message="pageErrors.governorate" />
                <AppInlineAlert v-if="pageErrors.education_type" variant="danger" :message="pageErrors.education_type" />
                <AppInlineAlert v-if="pageErrors.directorate" variant="danger" :message="pageErrors.directorate" />
            </div>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
                <article class="education-settings-panel rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-5 text-right shadow-lg shadow-slate-950/20 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                                <GraduationCap class="h-4 w-4" />
                                <span>إدارة مرجعيات التعليم</span>
                            </p>
                            <h2 class="mt-2 text-xl font-black text-white">إضافة وتعديل أنواع التعليم</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-400">
                                أصبحت عملية الإضافة والتعديل داخل نافذة مستقلة، بينما بقيت هذه البطاقة لعرض الملخص والإجراء الرئيسي فقط.
                            </p>
                        </div>

                        <button type="button" class="ui-primary-button shrink-0 inline-flex items-center gap-2" @click="openCreateEducationTypeModal">
                            <Plus class="h-4 w-4" />
                            <span>إضافة نوع تعليم</span>
                        </button>
                    </div>

                    <div class="education-settings-surface mt-5 rounded-2xl border border-slate-800/80 bg-slate-950/50 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs font-semibold text-slate-400">الأنواع الحالية</p>
                                <p class="mt-2 text-2xl font-black text-white">{{ educationTypeRows.length }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs font-semibold text-slate-400">النطاقات المرتبطة</p>
                                <p class="mt-2 text-2xl font-black text-white">{{ directorates.length }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-7 text-slate-400">
                            استخدم أزرار التعديل داخل القائمة لفتح نفس النافذة مع البيانات الحالية دون إبقاء النموذج داخل جسم الصفحة.
                        </p>
                    </div>
                </article>

                <article class="education-settings-panel rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-5 text-right shadow-lg shadow-slate-950/20 sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-slate-800/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-white">أنواع التعليم المتاحة</h2>
                            <p class="mt-1 text-sm leading-7 text-slate-400">
                                اعرض الأنواع الحالية بسرعة، وراجع عدد النطاقات التي تستخدم كل نوع قبل التعديل أو الحذف.
                            </p>
                        </div>
                        <span class="ui-chip self-start sm:self-auto">{{ educationTypeRows.length }} عناصر</span>
                    </div>

                    <div v-if="educationTypeRows.length === 0" class="pt-5">
                        <AppStatePanel
                            variant="empty"
                            compact
                            title="لا توجد أنواع تعليم مضافة بعد"
                            description="ابدأ بإضافة نوع تعليم واحد على الأقل قبل إنشاء النطاقات التعليمية المرتبطة به."
                        />
                    </div>

                    <div v-else class="mt-5 grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="educationType in educationTypeRows"
                            :key="educationType.id"
                            class="education-settings-item rounded-2xl border border-slate-800/80 bg-slate-950/60 p-4 transition-colors hover:border-slate-700"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <span class="ui-avatar border-cyan-500/20 bg-cyan-500/10 text-cyan-200">
                                            {{ educationType.name?.slice(0, 1) || 'ت' }}
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-black text-white">{{ educationType.name }}</h3>
                                            <p class="mt-1 text-xs text-slate-400">
                                                مستخدم في {{ educationType.directorateCount }} نطاقات تعليمية
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <span class="ui-chip shrink-0">{{ educationType.directorateCount }} نطاق</span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="ui-action-button inline-flex items-center gap-2"
                                    @click="startEducationTypeEdit(educationType)"
                                >
                                    <PenSquare class="h-4 w-4" />
                                    <span>تعديل</span>
                                </button>
                                <button
                                    type="button"
                                    class="education-settings-danger-action ui-action-button inline-flex items-center gap-2 border-rose-500/25 bg-rose-500/10 text-rose-100 hover:border-rose-400/40 hover:bg-rose-500/15"
                                    @click="destroyWithConfirmation(
                                        'حذف نوع التعليم',
                                        'سيتم حذف نوع التعليم نهائيًا إذا لم يكن مرتبطًا بنطاقات تعليمية.',
                                        route('admin.education_types.delete', educationType.id),
                                    )"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    <span>حذف</span>
                                </button>
                            </div>
                        </article>
                    </div>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                <article class="education-settings-panel rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-5 text-right shadow-lg shadow-slate-950/20 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                                <MapPinned class="h-4 w-4" />
                                <span>بناء النطاقات التعليمية</span>
                            </p>
                            <h2 class="mt-2 text-xl font-black text-white">إضافة وتعديل النطاقات التعليمية</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-400">
                                يتم إنشاء النطاق أو تعديله داخل نافذة مستقلة، مع بقاء الصفحة الحالية للعرض والمراجعة فقط.
                            </p>
                        </div>

                        <button type="button" class="ui-primary-button shrink-0 inline-flex items-center gap-2" @click="openCreateDirectorateModal">
                            <Plus class="h-4 w-4" />
                            <span>إضافة نطاق تعليمي</span>
                        </button>
                    </div>

                    <div class="education-settings-surface mt-5 space-y-4 rounded-2xl border border-slate-800/80 bg-slate-950/50 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs font-semibold text-slate-400">الدول المتاحة</p>
                                <p class="mt-2 text-2xl font-black text-white">{{ countries.length }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs font-semibold text-slate-400">المحافظات المحلية</p>
                                <p class="mt-2 text-2xl font-black text-white">{{ governorates.length }}</p>
                            </div>
                        </div>
                        <AppInlineAlert
                            v-if="directorateGovernoratesSyncing"
                            variant="info"
                            message="يتم تجهيز محافظات الدولة المختارة تلقائيًا عند فتح نافذة النطاق."
                        />
                        <AppInlineAlert
                            v-else-if="directorateGovernoratesSyncError"
                            variant="danger"
                            :message="directorateGovernoratesSyncError"
                        />
                        <p class="text-sm leading-7 text-slate-400">
                            عند اختيار الدولة داخل نافذة الإضافة أو التعديل، يتم تحميل المحافظات التابعة لها تلقائيًا دون خطوة تشغيل منفصلة.
                        </p>
                    </div>
                </article>

                <article class="education-settings-panel rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-5 text-right shadow-lg shadow-slate-950/20 sm:p-6">
                    <div class="flex flex-col gap-4 border-b border-slate-800/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-white">النطاقات التعليمية المتاحة</h2>
                            <p class="mt-1 text-sm leading-7 text-slate-400">
                                هذا الربط هو الذي يظهر فعليًا في تهيئة مدير المدرسة ويحدد الدولة والمحافظة ونوع التعليم المسموحين داخل النطاق.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="ui-chip">{{ directorates.length }} نطاق</span>
                            <span class="ui-chip">{{ totalLinkedSchools }} مدرسة مرتبطة</span>
                        </div>
                    </div>

                    <div v-if="directorates.length === 0" class="pt-5">
                        <AppStatePanel
                            variant="empty"
                            compact
                            title="لا توجد نطاقات تعليمية مضافة بعد"
                            description="ابدأ بإضافة دولة ثم محافظة ثم نوع تعليم، وبعدها اربطها كنطاق تعليمي متاح للمدارس."
                        />
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article
                            v-for="directorate in directorates"
                            :key="directorate.id"
                            class="education-settings-item overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-950/60"
                        >
                            <div class="border-b border-slate-800/80 p-4 sm:p-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="ui-avatar border-cyan-500/20 bg-cyan-500/10 text-cyan-200">
                                                <Building2 class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <h3 class="truncate text-lg font-black text-white">{{ directorate.governorate }} - {{ directorate.name }}</h3>
                                                <p class="mt-1 text-sm leading-7 text-slate-400">
                                                    نطاق تعليمي معرف للاستخدام في تهيئة المدارس والربط التشغيلي.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                            <span class="ui-chip">
                                                <Globe2 class="h-3.5 w-3.5" />
                                                <span>{{ directorate.country?.name || 'دولة غير محددة' }}</span>
                                            </span>
                                            <span class="ui-chip">
                                                <MapPinned class="h-3.5 w-3.5" />
                                                <span>{{ directorate.governorate_model?.name || directorate.governorate }}</span>
                                            </span>
                                            <span class="ui-chip">
                                                <GraduationCap class="h-3.5 w-3.5" />
                                                <span>{{ directorate.education_type?.name || directorate.name }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span class="ui-chip">
                                            <School class="h-3.5 w-3.5" />
                                            <span>{{ getSchoolCount(directorate) }} مدارس</span>
                                        </span>
                                        <button
                                            type="button"
                                            class="ui-action-button inline-flex items-center gap-2"
                                            @click="startDirectorateEdit(directorate)"
                                        >
                                            <PenSquare class="h-4 w-4" />
                                            <span>تعديل</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="education-settings-danger-action ui-action-button inline-flex items-center gap-2 border-rose-500/25 bg-rose-500/10 text-rose-100 hover:border-rose-400/40 hover:bg-rose-500/15"
                                            @click="destroyWithConfirmation(
                                                'حذف النطاق التعليمي',
                                                'سيتم حذف هذا النطاق نهائيًا إذا لم تكن هناك مدارس مرتبطة به.',
                                                route('admin.directorates.delete', directorate.id),
                                            )"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            <span>حذف</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 sm:p-5">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-bold text-slate-200">المدارس المرتبطة بهذا النطاق</h4>
                                    <span class="text-xs text-slate-500">إجمالي المدارس: {{ getSchoolCount(directorate) }}</span>
                                </div>

                                <div
                                    v-if="getSchoolCount(directorate) === 0"
                                    class="education-settings-empty-link rounded-2xl border border-dashed border-slate-700 bg-slate-950/50 p-4 text-sm leading-7 text-slate-500"
                                >
                                    لا توجد مدارس مرتبطة بهذا النطاق حتى الآن.
                                </div>

                                <div v-else class="grid gap-3 md:grid-cols-2">
                                    <article
                                        v-for="school in directorate.schools"
                                        :key="school.id"
                                        class="education-settings-school-card rounded-2xl border border-slate-800/80 bg-slate-900/80 p-4"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-white">{{ school.name }}</p>
                                                <p class="mt-1 text-xs text-slate-500">المعرف المدرسي: {{ school.school_id }}</p>
                                            </div>
                                            <span class="ui-chip shrink-0">{{ school.status || 'غير محددة' }}</span>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-400">
                                            <span>الجوال: {{ school.phone || '-' }}</span>
                                            <span>الحالة: {{ school.status || '-' }}</span>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </article>
                    </div>
                </article>
            </section>
        </div>

        <AppModal
            :open="isEducationTypeModalOpen"
            :title="educationTypeFormTitle"
            :description="educationTypeFormDescription"
            max-width-class="max-w-2xl"
            @close="closeEducationTypeModal"
        >
            <div class="space-y-4">
                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">اسم نوع التعليم</span>
                    <input
                        v-model="educationTypeForm.name"
                        class="ui-input"
                        placeholder="مثال: تعليم عام أو تعليم أهلي"
                    />
                    <p v-if="educationTypeForm.errors.name" class="ui-field-error">{{ educationTypeForm.errors.name }}</p>
                </label>
            </div>

            <template #footer>
                <div class="text-xs text-slate-500">تظل القائمة الرئيسية للعرض فقط، بينما تتم الإضافة والتعديل من هذه النافذة.</div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <button type="button" class="ui-secondary-button w-full sm:w-auto" @click="closeEducationTypeModal">إلغاء</button>
                    <button
                        type="button"
                        class="ui-primary-button inline-flex w-full items-center justify-center gap-2 sm:w-auto"
                        :disabled="educationTypeForm.processing"
                        @click="submitEducationType"
                    >
                        <component :is="editingEducationTypeId ? PenSquare : Plus" class="h-4 w-4" />
                        <span>{{ editingEducationTypeId ? 'حفظ التعديل' : 'إضافة نوع التعليم' }}</span>
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="isDirectorateModalOpen"
            :title="directorateFormTitle"
            :description="directorateFormDescription"
            max-width-class="max-w-3xl"
            @close="closeDirectorateModal"
        >
            <div class="space-y-4">
                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">الدولة</span>
                    <select v-model="directorateForm.country_id" class="ui-select">
                        <option value="">اختر الدولة</option>
                        <option v-for="country in countries" :key="`directorate-modal-country-${country.id}`" :value="country.id">{{ country.name }}</option>
                    </select>
                    <p v-if="directorateForm.errors.country_id" class="ui-field-error">{{ directorateForm.errors.country_id }}</p>
                </label>

                <div class="space-y-3">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span class="font-semibold text-white">المحافظة</span>
                        <select v-model="directorateForm.governorate_id" class="ui-select" :disabled="!directorateForm.country_id || directorateGovernoratesSyncing">
                            <option value="">{{ directorateGovernoratesSyncing ? 'جارٍ تحميل المحافظات...' : 'اختر المحافظة' }}</option>
                            <option v-for="governorate in directorateGovernorates" :key="`directorate-modal-governorate-${governorate.id}`" :value="governorate.id">{{ governorate.name }}</option>
                        </select>
                        <p v-if="directorateForm.errors.governorate_id" class="ui-field-error">{{ directorateForm.errors.governorate_id }}</p>
                    </label>

                    <AppInlineAlert
                        v-if="directorateGovernoratesSyncing"
                        variant="info"
                        message="يتم تجهيز محافظات الدولة المختارة تلقائيًا الآن."
                    />
                    <AppInlineAlert
                        v-else-if="directorateGovernoratesSyncError"
                        variant="danger"
                        :message="directorateGovernoratesSyncError"
                    />
                    <AppInlineAlert
                        v-else-if="directorateForm.country_id && directorateGovernorates.length === 0"
                        variant="warning"
                        message="لا توجد محافظات متاحة لهذه الدولة حاليًا."
                    />
                </div>

                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">نوع التعليم</span>
                    <select v-model="directorateForm.education_type_id" class="ui-select">
                        <option value="">اختر نوع التعليم</option>
                        <option v-for="educationType in educationTypes" :key="`directorate-modal-type-${educationType.id}`" :value="educationType.id">{{ educationType.name }}</option>
                    </select>
                    <p v-if="directorateForm.errors.education_type_id" class="ui-field-error">{{ directorateForm.errors.education_type_id }}</p>
                </label>
            </div>

            <template #footer>
                <div class="text-xs text-slate-500">تحميل المحافظات يظل مرتبطًا باختيار الدولة فقط داخل هذه النافذة.</div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <button type="button" class="ui-secondary-button w-full sm:w-auto" @click="closeDirectorateModal">إلغاء</button>
                    <button
                        type="button"
                        class="ui-primary-button inline-flex w-full items-center justify-center gap-2 sm:w-auto"
                        :disabled="directorateForm.processing"
                        @click="submitDirectorate"
                    >
                        <component :is="editingDirectorateId ? PenSquare : Plus" class="h-4 w-4" />
                        <span>{{ editingDirectorateId ? 'حفظ التعديل' : 'إضافة نطاق تعليمي' }}</span>
                    </button>
                </div>
            </template>
        </AppModal>
    </AdminLayout>
</template>

<style>
html.theme-light .education-settings-page .education-settings-hero {
    border-color: rgba(8, 145, 178, 0.2);
    background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.16), transparent 34%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96));
    box-shadow: 0 30px 90px -58px rgba(14, 116, 144, 0.3);
}

html.theme-light .education-settings-page .education-settings-stat,
html.theme-light .education-settings-page .education-settings-panel,
html.theme-light .education-settings-page .education-settings-surface,
html.theme-light .education-settings-page .education-settings-item,
html.theme-light .education-settings-page .education-settings-school-card,
html.theme-light .education-settings-page .education-settings-empty-link {
    box-shadow: 0 18px 50px -42px rgba(15, 23, 42, 0.16);
}

html.theme-light .education-settings-page .education-settings-stat {
    border-color: rgba(148, 163, 184, 0.24);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
}

html.theme-light .education-settings-page .education-settings-panel {
    border-color: rgba(148, 163, 184, 0.24);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.95));
}

html.theme-light .education-settings-page .education-settings-surface {
    border-color: rgba(191, 219, 254, 0.55);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(241, 245, 249, 0.92));
}

html.theme-light .education-settings-page .education-settings-item,
html.theme-light .education-settings-page .education-settings-school-card {
    border-color: rgba(203, 213, 225, 0.85);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.88));
}

html.theme-light .education-settings-page .education-settings-empty-link {
    border-color: rgba(148, 163, 184, 0.45);
    background: rgba(248, 250, 252, 0.92);
}

html.theme-light .education-settings-page .ui-chip {
    border-color: rgba(148, 163, 184, 0.34);
    background: rgba(241, 245, 249, 0.94);
    color: #334155;
}

html.theme-light .education-settings-page .ui-avatar {
    border-color: rgba(8, 145, 178, 0.18);
    background: linear-gradient(180deg, rgba(224, 242, 254, 0.92), rgba(236, 254, 255, 0.96));
    color: #0f766e;
}

html.theme-light .education-settings-page .ui-input,
html.theme-light .education-settings-page .ui-select {
    border-color: rgba(148, 163, 184, 0.42);
    background: rgba(255, 255, 255, 0.98);
    box-shadow: inset 0 1px 2px rgba(148, 163, 184, 0.08);
}

html.theme-light .education-settings-page .ui-primary-button {
    box-shadow: 0 14px 28px -18px rgba(14, 116, 144, 0.42);
}

html.theme-light .education-settings-page .ui-secondary-button {
    border-color: rgba(148, 163, 184, 0.38);
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
}

html.theme-light .education-settings-page .ui-secondary-button:hover,
html.theme-light .education-settings-page .ui-action-button:hover {
    background: rgba(241, 245, 249, 0.96);
}

html.theme-light .education-settings-page .ui-action-button {
    border-color: rgba(148, 163, 184, 0.34);
    background: rgba(248, 250, 252, 0.96);
    color: #334155;
}

html.theme-light .education-settings-page .education-settings-danger-action {
    border-color: rgba(244, 63, 94, 0.28) !important;
    background: rgba(255, 241, 242, 0.98) !important;
    color: #b91c1c !important;
}

html.theme-light .education-settings-page .education-settings-danger-action:hover {
    background: rgba(255, 228, 230, 0.98) !important;
}

html.theme-light .education-settings-page .ui-empty-state {
    border-color: rgba(148, 163, 184, 0.24);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.9));
}

html.theme-light .education-settings-page .ui-inline-alert {
    box-shadow: 0 10px 30px -26px rgba(15, 23, 42, 0.18);
}

html.theme-light .education-settings-page .text-white {
    color: #0f172a !important;
}

html.theme-light .education-settings-page .text-slate-300,
html.theme-light .education-settings-page .text-slate-400 {
    color: #475569 !important;
}

html.theme-light .education-settings-page .text-slate-500 {
    color: #64748b !important;
}

html.theme-light .education-settings-page .text-cyan-200,
html.theme-light .education-settings-page .text-cyan-300 {
    color: #0f766e !important;
}
</style>
