<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    stages: {
        type: Array,
        default: () => [],
    },
});

const actionDialog = useActionDialog();
const stageNameInput = ref(null);
const stageEditId = ref(null);

const stageForm = useForm({
    name: '',
    code: '',
    school_day_start_time: '',
    school_day_end_time: '',
    sort_order: 0,
    is_active: true,
});

const focusInput = () => {
    nextTick(() => {
        stageNameInput.value?.focus?.();
    });
};

const normalizeTimeInputValue = (value) => {
    if (!value) return '';
    return String(value).slice(0, 5);
};

const formatTimeForDisplay = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 5);
};

const extractDeleteErrorMessage = (errors = {}) => {
    const keys = ['stage', 'confirm_impact'];

    for (const key of keys) {
        if (typeof errors[key] === 'string' && errors[key].trim() !== '') {
            return errors[key];
        }
    }

    const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
    return firstError || 'تعذر تنفيذ عملية الحذف بسبب وجود بيانات مرتبطة بهذه المرحلة.';
};

const guardedDelete = (endpoint) => {
    const deleteForm = useForm({});

    deleteForm.delete(endpoint, {
        preserveScroll: true,
        onError: async (errors) => {
            await actionDialog.alert({
                title: 'تعذر حذف المرحلة',
                message: extractDeleteErrorMessage(errors),
                confirmText: 'حسنًا',
                variant: 'warning',
            });
        },
    });
};

const stageCountById = computed(() => {
    const map = new Map();

    for (const stage of props.stages) {
        const classrooms = Array.isArray(stage.classrooms) ? stage.classrooms : [];
        const studentCount = classrooms.reduce(
            (sum, classroom) =>
                sum + Number(classroom.students_count ?? (Array.isArray(classroom.students) ? classroom.students.length : 0) ?? 0),
            0,
        );

        map.set(Number(stage.id), {
            classroomsCount: classrooms.length,
            studentsCount: studentCount,
        });
    }

    return map;
});

const resetStageForm = () => {
    stageEditId.value = null;
    stageForm.reset();
    stageForm.school_day_start_time = '';
    stageForm.school_day_end_time = '';
    stageForm.sort_order = 0;
    stageForm.is_active = true;
    stageForm.clearErrors();
    focusInput();
};

const editStage = (stage) => {
    stageEditId.value = stage.id;
    stageForm.name = stage.name || '';
    stageForm.code = stage.code || '';
    stageForm.school_day_start_time = normalizeTimeInputValue(stage.school_day_start_time);
    stageForm.school_day_end_time = normalizeTimeInputValue(stage.school_day_end_time);
    stageForm.sort_order = Number(stage.sort_order || 0);
    stageForm.is_active = Boolean(stage.is_active);
    stageForm.clearErrors();
    focusInput();
};

const submitStage = () => {
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetStageForm();
        },
    };

    if (stageEditId.value) {
        stageForm.put(route('school.student_structure.stages.update', stageEditId.value), options);
        return;
    }

    stageForm.post(route('school.student_structure.stages.store'), options);
};

const removeStage = async (stageId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف المرحلة الدراسية',
        message: 'سيتم الحذف فقط إذا لم توجد بيانات تشغيلية مرتبطة بالمرحلة. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف المرحلة',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    guardedDelete(route('school.student_structure.stages.destroy', stageId));
};

const statusLabel = (value) => (value ? 'نشط' : 'غير نشط');

focusInput();
</script>

<template>
    <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-bold">A) إدارة المراحل الدراسية</h3>
            <button type="button" class="rounded bg-gray-700 px-3 py-1 text-xs hover:bg-gray-600" @click="resetStageForm">جديد</button>
        </div>

        <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitStage">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <div>
                    <label class="mb-1 block text-xs text-gray-400">اسم المرحلة</label>
                    <input ref="stageNameInput" v-model="stageForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="stageForm.errors.name" class="mt-1 text-xs text-red-400">{{ stageForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">الكود (اختياري)</label>
                    <input v-model="stageForm.code" placeholder="STG-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="stageForm.errors.code" class="mt-1 text-xs text-red-400">{{ stageForm.errors.code }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">بداية اليوم الدراسي</label>
                    <input v-model="stageForm.school_day_start_time" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="stageForm.errors.school_day_start_time" class="mt-1 text-xs text-red-400">{{ stageForm.errors.school_day_start_time }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">نهاية اليوم الدراسي</label>
                    <input v-model="stageForm.school_day_end_time" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="stageForm.errors.school_day_end_time" class="mt-1 text-xs text-red-400">{{ stageForm.errors.school_day_end_time }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                    <input v-model.number="stageForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="stageForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ stageForm.errors.sort_order }}</p>
                </div>
                <div class="flex items-end gap-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="stageForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                        <span>نشط</span>
                    </label>
                    <button type="submit" :disabled="stageForm.processing" class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                        {{ stageEditId ? 'تحديث المرحلة' : 'إضافة مرحلة' }}
                    </button>
                </div>
            </div>
        </form>

        <div class="space-y-2">
            <div v-for="stage in stages" :key="stage.id" class="flex items-center justify-between rounded border border-gray-700 bg-gray-800 p-3">
                <div>
                    <p class="font-semibold">{{ stage.name }}</p>
                    <p class="text-xs text-gray-400">
                        {{ stage.code || '-' }} | {{ statusLabel(stage.is_active) }} | اليوم الدراسي: {{ formatTimeForDisplay(stage.school_day_start_time) }} - {{ formatTimeForDisplay(stage.school_day_end_time) }} |
                        فصول: {{ stageCountById.get(Number(stage.id))?.classroomsCount || 0 }} |
                        طلاب: {{ stageCountById.get(Number(stage.id))?.studentsCount || 0 }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editStage(stage)">تعديل</button>
                    <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeStage(stage.id)">حذف</button>
                </div>
            </div>
            <p v-if="stages.length === 0" class="text-sm text-gray-500">لا توجد مراحل دراسية مضافة بعد.</p>
        </div>
    </section>
</template>
