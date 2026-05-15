<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    currentRegionId: {
        type: Number,
        default: null,
    },
    recentRequests: {
        type: Array,
        default: () => [],
    },
});

const countries = ref([]);
const governorates = ref([]);
const regions = ref([]);
const schools = ref([]);
const selectedCountryId = ref('');
const selectedGovernorateId = ref('');
const selectedSchoolIds = ref([]);
const message = ref('');
const error = ref('');
const loadingLocations = ref(false);
const loadingSchools = ref(false);

const availableGovernorates = computed(() =>
    governorates.value
        .filter((governorate) => Number(governorate.country_id) === Number(selectedCountryId.value))
        .sort((first, second) => String(first.name || '').localeCompare(String(second.name || ''), 'ar')),
);

const applyCurrentRegionSelection = () => {
    if (!props.currentRegionId) {
        return;
    }

    const currentRegion = regions.value.find((region) => Number(region.id) === Number(props.currentRegionId));
    if (!currentRegion) {
        return;
    }

    selectedCountryId.value = currentRegion.country_id ? Number(currentRegion.country_id) : '';
    selectedGovernorateId.value = currentRegion.governorate_id ? Number(currentRegion.governorate_id) : '';
};

const loadRegions = async () => {
    loadingLocations.value = true;
    error.value = '';

    try {
        const response = await axios.get(route('supervisor.onboarding.regions'));
        const payload = response.data || {};

        countries.value = Array.isArray(payload.countries) ? payload.countries : [];
        governorates.value = Array.isArray(payload.governorates) ? payload.governorates : [];
        regions.value = Array.isArray(payload.regions) ? payload.regions : [];

        applyCurrentRegionSelection();
    } catch (requestError) {
        error.value =
            requestError?.response?.data?.message
            || 'تعذر تحميل بيانات الدول والمحافظات الآن.';
    } finally {
        loadingLocations.value = false;
    }
};

const loadSchools = async () => {
    if (!selectedCountryId.value || !selectedGovernorateId.value) {
        schools.value = [];
        selectedSchoolIds.value = [];
        return;
    }

    loadingSchools.value = true;

    try {
        const response = await axios.get(route('supervisor.onboarding.location_schools'), {
            params: {
                country_id: selectedCountryId.value,
                governorate_id: selectedGovernorateId.value,
            },
        });

        schools.value = Array.isArray(response.data) ? response.data : [];
        selectedSchoolIds.value = selectedSchoolIds.value.filter((schoolId) =>
            schools.value.some((school) => Number(school.id) === Number(schoolId)),
        );
    } catch (requestError) {
        schools.value = [];
        selectedSchoolIds.value = [];
        error.value =
            requestError?.response?.data?.message
            || 'تعذر تحميل المدارس داخل المحافظة المحددة الآن.';
    } finally {
        loadingSchools.value = false;
    }
};

const submitSelection = async () => {
    error.value = '';
    message.value = '';

    if (!selectedCountryId.value) {
        error.value = 'اختر الدولة أولًا.';
        return;
    }

    if (!selectedGovernorateId.value) {
        error.value = 'اختر المحافظة أولًا.';
        return;
    }

    if (selectedSchoolIds.value.length === 0) {
        error.value = 'اختر مدرسة واحدة على الأقل.';
        return;
    }

    try {
        const response = await axios.post(route('supervisor.onboarding.select'), {
            country_id: selectedCountryId.value,
            governorate_id: selectedGovernorateId.value,
            school_ids: selectedSchoolIds.value,
        });

        const skipped = response.data.skipped_school_ids || [];
        message.value = `تم إنشاء ${response.data.created_count} طلب/طلبات. المدارس المتجاهلة: ${skipped.length}`;
        selectedSchoolIds.value = [];
    } catch (requestError) {
        error.value =
            requestError?.response?.data?.message
            || Object.values(requestError?.response?.data?.errors || {}).flat().join(' | ')
            || 'تعذر حفظ الاختيار.';
    }
};

const requestStatusLabel = (status) => {
    if (status === 'SUPERVISOR_REQUESTED') return 'قيد انتظار المدير';
    if (status === 'MANAGER_APPROVED') return 'بانتظار التأكيد النهائي';
    if (status === 'ACTIVE_ASSOCIATION') return 'ارتباط مفعل';
    if (status === 'MANAGER_REJECTED') return 'مرفوض من المدير';
    if (status === 'SUPERVISOR_REJECTED') return 'مرفوض من المشرف';
    if (status === 'CANCELED') return 'ملغي';

    return status || '-';
};

watch(selectedCountryId, () => {
    const governorateStillValid = availableGovernorates.value.some(
        (governorate) => Number(governorate.id) === Number(selectedGovernorateId.value),
    );

    if (!governorateStillValid) {
        selectedGovernorateId.value = '';
        schools.value = [];
        selectedSchoolIds.value = [];
    }
});

watch(selectedGovernorateId, async () => {
    await loadSchools();
});

onMounted(async () => {
    await loadRegions();

    if (selectedCountryId.value && selectedGovernorateId.value) {
        await loadSchools();
    }
});
</script>

<template>
    <Head title="تهيئة المشرف" />

    <RoleLayout title="تهيئة المشرف" role="SUPERVISOR">
        <div class="ui-page-shell max-w-6xl">
            <section class="ui-page-hero">
                <div class="ui-page-heading text-right">
                    <h1 class="ui-page-title">اختيار الدولة والمحافظة والمدارس</h1>
                    <p class="ui-page-copy">
                        اختر الدولة ثم المحافظة، وبعدها ستظهر المدارس التابعة لهذه المحافظة فقط. سيُنشئ النظام طلبات الإشراف للمدارس المختارة ضمن هذا النطاق الجغرافي فقط.
                    </p>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)]">
                <section class="ui-section">
                    <div v-if="loadingLocations">
                        <AppStatePanel variant="loading" title="جارٍ تحميل بيانات المواقع" description="يتم الآن تجهيز الدول والمحافظات المتاحة للمشرف." compact />
                    </div>

                    <template v-else>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="space-y-2 text-right">
                                <label class="ui-field-label">الدولة</label>
                                <select v-model="selectedCountryId" class="ui-select">
                                    <option value="" disabled>اختر الدولة</option>
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2 text-right">
                                <label class="ui-field-label">المحافظة</label>
                                <select v-model="selectedGovernorateId" class="ui-select" :disabled="!selectedCountryId">
                                    <option value="" disabled>اختر المحافظة</option>
                                    <option v-for="governorate in availableGovernorates" :key="governorate.id" :value="governorate.id">
                                        {{ governorate.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-if="selectedCountryId && availableGovernorates.length === 0" class="ui-inline-alert ui-inline-alert--warning mt-4 text-right">
                            لا توجد محافظات متاحة لهذه الدولة حاليًا.
                        </div>

                        <div class="mt-5 rounded-[24px] border border-white/10 bg-slate-950/35 p-4">
                            <div class="mb-3 text-right">
                                <p class="text-sm font-black text-white">المدارس داخل المحافظة المحددة</p>
                                <p class="text-xs text-slate-400">اختر مدرسة واحدة أو أكثر لإنشاء طلبات الإشراف ضمن المحافظة المختارة فقط.</p>
                            </div>

                            <div v-if="loadingSchools">
                                <AppStatePanel variant="loading" title="جارٍ تحميل المدارس" description="يتم الآن جلب المدارس المتاحة داخل الدولة والمحافظة المحددتين." compact />
                            </div>

                            <div v-else-if="schools.length === 0">
                                <AppStatePanel title="لا توجد مدارس متاحة" description="لم يتم العثور على مدارس ضمن المحافظة الحالية أو أنها مرتبطة بمشرف آخر." compact />
                            </div>

                            <div v-else class="max-h-80 space-y-3 overflow-auto pr-1">
                                <label
                                    v-for="school in schools"
                                    :key="school.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-slate-900/55 p-3 text-sm"
                                >
                                    <input
                                        v-model="selectedSchoolIds"
                                        type="checkbox"
                                        :value="school.id"
                                        class="ui-auth-checkbox h-4 w-4 rounded border-gray-600 bg-slate-900"
                                    >
                                    <span class="min-w-0 flex-1">{{ school.name }} - {{ school.school_id }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button class="ui-primary-button" type="button" @click="submitSelection">إنشاء طلبات الإشراف</button>
                        </div>

                        <p v-if="message" class="ui-inline-alert ui-inline-alert--success mt-4 text-right">{{ message }}</p>
                        <p v-if="error" class="ui-inline-alert ui-inline-alert--danger mt-4 text-right">{{ error }}</p>
                    </template>
                </section>

                <section class="ui-section">
                    <div class="ui-section-header">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">آخر الطلبات</h2>
                            <p class="ui-section-subtitle">ملخص سريع لآخر طلبات الإشراف التي أنشأتها مؤخرًا.</p>
                        </div>
                    </div>

                    <div v-if="recentRequests.length === 0">
                        <AppStatePanel title="لا توجد طلبات بعد" description="ستظهر هنا أحدث الطلبات بعد إنشائها." compact />
                    </div>

                    <div v-else class="space-y-3">
                        <article v-for="item in recentRequests" :key="item.id" class="ui-card-soft p-4 text-right">
                            <p class="text-sm font-black text-white">{{ item.school?.name }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ requestStatusLabel(item.status) }}</p>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </RoleLayout>
</template>
