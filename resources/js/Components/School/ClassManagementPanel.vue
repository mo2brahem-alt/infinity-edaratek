<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    stages: {
        type: Array,
        default: () => [],
    },
});

const actionDialog = useActionDialog();
const classroomNameInput = ref(null);
const classroomEditId = ref(null);

const stageOptions = computed(() => props.stages.map((stage) => ({ id: stage.id, name: stage.name })));
const defaultStageId = computed(() => stageOptions.value[0]?.id || '');

const classroomOptions = computed(() =>
    props.stages.flatMap((stage) =>
        (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            stage_name: stage.name,
            school_stage_id: stage.id,
        })),
    ),
);

const classroomForm = useForm({
    school_stage_id: defaultStageId.value,
    name: '',
    code: '',
    sort_order: 0,
    is_active: true,
});

const focusInput = () => {
    nextTick(() => {
        classroomNameInput.value?.focus?.();
    });
};

watch(
    () => stageOptions.value.map((stage) => Number(stage.id)).join(','),
    () => {
        const validStageIds = stageOptions.value.map((stage) => Number(stage.id));

        if (!validStageIds.includes(Number(classroomForm.school_stage_id))) {
            classroomForm.school_stage_id = defaultStageId.value;
        }
    },
);

const resetClassroomForm = (preferredStageId = null) => {
    classroomEditId.value = null;
    classroomForm.reset();

    const availableStageIds = stageOptions.value.map((stage) => String(stage.id));
    classroomForm.school_stage_id =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStageId.value;

    classroomForm.sort_order = 0;
    classroomForm.is_active = true;
    classroomForm.clearErrors();
    focusInput();
};

const editClassroom = (classroom) => {
    classroomEditId.value = classroom.id;
    classroomForm.school_stage_id = classroom.school_stage_id;
    classroomForm.name = classroom.name || '';
    classroomForm.code = classroom.code || '';
    classroomForm.sort_order = Number(classroom.sort_order || 0);
    classroomForm.is_active = Boolean(classroom.is_active);
    classroomForm.clearErrors();
    focusInput();
};

const submitClassroom = () => {
    const preferredStageId = classroomForm.school_stage_id;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            resetClassroomForm(preferredStageId);
        },
    };

    if (classroomEditId.value) {
        classroomForm.put(route('school.student_structure.classrooms.update', classroomEditId.value), options);
        return;
    }

    classroomForm.post(route('school.student_structure.classrooms.store'), options);
};

const extractDeleteErrorMessage = (errors = {}) => {
    const keys = ['classroom', 'confirm_impact'];

    for (const key of keys) {
        if (typeof errors[key] === 'string' && errors[key].trim() !== '') {
            return errors[key];
        }
    }

    const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
    return firstError || 'تعذر تنفيذ عملية الحذف بسبب وجود بيانات مرتبطة بهذا الفصل.';
};

const guardedDelete = (endpoint) => {
    const deleteForm = useForm({});

    deleteForm.delete(endpoint, {
        preserveScroll: true,
        onError: async (errors) => {
            await actionDialog.alert({
                title: 'تعذر حذف الفصل',
                message: extractDeleteErrorMessage(errors),
                confirmText: 'حسنًا',
                variant: 'warning',
            });
        },
    });
};

const removeClassroom = async (classroomId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الفصل الدراسي',
        message: 'سيتم الحذف فقط إذا لم توجد بيانات تشغيلية مرتبطة بالفصل. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف الفصل',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    guardedDelete(route('school.student_structure.classrooms.destroy', classroomId));
};

const statusLabel = (value) => (value ? 'نشط' : 'غير نشط');

focusInput();
</script>

<template>
    <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-bold">B) إدارة الفصول الدراسية</h3>
            <button type="button" class="rounded bg-gray-700 px-3 py-1 text-xs hover:bg-gray-600" @click="resetClassroomForm">جديد</button>
        </div>

        <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitClassroom">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs text-gray-400">المرحلة</label>
                    <select v-model="classroomForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                        <option value="" disabled>اختر المرحلة</option>
                        <option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                    </select>
                    <p v-if="classroomForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.school_stage_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">اسم الفصل</label>
                    <input ref="classroomNameInput" v-model="classroomForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="classroomForm.errors.name" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">الكود (اختياري)</label>
                    <input v-model="classroomForm.code" placeholder="CLS-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="classroomForm.errors.code" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.code }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                    <input v-model.number="classroomForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                    <p v-if="classroomForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.sort_order }}</p>
                </div>
                <div class="flex items-end gap-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="classroomForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                        <span>نشط</span>
                    </label>
                    <button
                        type="submit"
                        :disabled="classroomForm.processing || !classroomForm.school_stage_id"
                        class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500"
                    >
                        {{ classroomEditId ? 'تحديث الفصل' : 'إضافة فصل' }}
                    </button>
                </div>
            </div>
        </form>

        <div class="space-y-3">
            <div v-for="stage in stages" :key="`stage-classrooms-${stage.id}`" class="rounded border border-gray-700 bg-gray-800 p-3">
                <p class="mb-2 font-semibold text-gray-200">{{ stage.name }}</p>
                <div class="space-y-2">
                    <div v-for="classroom in stage.classrooms || []" :key="classroom.id" class="flex items-center justify-between rounded border border-gray-700 bg-gray-900 p-2">
                        <div>
                            <p class="text-sm font-semibold">{{ classroom.name }}</p>
                            <p class="text-xs text-gray-400">
                                {{ classroom.code || '-' }} | {{ statusLabel(classroom.is_active) }} |
                                طلاب: {{ classroom.students_count ?? (classroom.students || []).length }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editClassroom(classroom)">تعديل</button>
                            <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeClassroom(classroom.id)">حذف</button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-if="classroomOptions.length === 0" class="text-sm text-gray-500">لا توجد فصول مضافة بعد.</p>
        </div>
    </section>
</template>
