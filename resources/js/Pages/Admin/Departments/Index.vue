<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Building2, Plus, Pencil, Trash2, X } from 'lucide-vue-next';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    departments: {
        type: Array,
        default: () => [],
    },
    staffTypes: {
        type: Array,
        default: () => ['ADMINISTRATIVE', 'EDUCATIONAL'],
    },
    managerAssignsStructurePermissions: {
        type: Boolean,
        default: true,
    },
    orgStructureRoleTemplates: {
        type: Array,
        default: () => [],
    },
});

const actionDialog = useActionDialog();
const isModalOpen = ref(false);
const isEditing = ref(false);
const editId = ref(null);

const createEmptyRole = () => ({
    org_structure_role_template_id: '',
    can_manage_student_structure: false,
    can_manage_student_attendance: false,
    can_manage_academic_planning: false,
    can_manage_student_leaves: false,
});

const form = useForm({
    name: '',
    staff_type: props.staffTypes[0] || 'ADMINISTRATIVE',
    org_structure_roles: [createEmptyRole()],
});

const isAdministrative = computed(() => form.staff_type === 'ADMINISTRATIVE');
const showRolePermissionToggles = computed(() => !props.managerAssignsStructurePermissions);

const templateOptions = computed(() => props.orgStructureRoleTemplates || []);
const hasActiveTemplateOptions = computed(() =>
    templateOptions.value.some((template) => Boolean(template.is_active))
);

const templateLabelById = computed(() => {
    const map = new Map();
    (props.orgStructureRoleTemplates || []).forEach((template) => {
        map.set(Number(template.id), template.name);
    });
    return map;
});

const resolveTemplateLabel = (templateId, fallbackName = '-') => {
    if (!templateId) {
        return fallbackName || '-';
    }

    return templateLabelById.value.get(Number(templateId)) || fallbackName || '-';
};

watch(
    () => form.staff_type,
    () => {
        if (isAdministrative.value) return;

        form.org_structure_roles = (form.org_structure_roles || []).map((role) => ({
            ...role,
            can_manage_student_structure: false,
            can_manage_student_attendance: false,
            can_manage_academic_planning: false,
            can_manage_student_leaves: false,
        }));
    }
);

const staffTypeLabel = (type) => {
    if (type === 'ADMINISTRATIVE') return 'إداري';
    if (type === 'EDUCATIONAL') return 'تعليمي';
    return type || '-';
};

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.name = '';
    form.staff_type = props.staffTypes[0] || 'ADMINISTRATIVE';
    form.org_structure_roles = [createEmptyRole()];
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (department) => {
    isEditing.value = true;
    editId.value = department.id;
    form.name = department.name || '';
    form.staff_type = department.staff_type || props.staffTypes[0] || 'ADMINISTRATIVE';
    form.org_structure_roles = (department.roles || []).length
        ? department.roles.map((role) => ({
              org_structure_role_template_id: role.org_structure_role_template_id || '',
              can_manage_student_structure: Boolean(role.can_manage_student_structure) && form.staff_type === 'ADMINISTRATIVE',
              can_manage_student_attendance: Boolean(role.can_manage_student_attendance) && form.staff_type === 'ADMINISTRATIVE',
              can_manage_academic_planning: Boolean(role.can_manage_academic_planning) && form.staff_type === 'ADMINISTRATIVE',
              can_manage_student_leaves: Boolean(role.can_manage_student_leaves) && form.staff_type === 'ADMINISTRATIVE',
          }))
        : [createEmptyRole()];
    form.clearErrors();
    isModalOpen.value = true;
};

const addRoleRow = () => {
    form.org_structure_roles = [...(form.org_structure_roles || []), createEmptyRole()];
};

const removeRoleRow = (index) => {
    if ((form.org_structure_roles || []).length <= 1) return;
    form.org_structure_roles = form.org_structure_roles.filter((_, idx) => idx !== index);
};

const payload = () => ({
    name: form.name,
    staff_type: form.staff_type,
    org_structure_roles: (form.org_structure_roles || [])
        .map((role) => ({
            org_structure_role_template_id: role.org_structure_role_template_id,
            can_manage_student_structure: showRolePermissionToggles.value && isAdministrative.value ? Boolean(role.can_manage_student_structure) : false,
            can_manage_student_attendance: showRolePermissionToggles.value && isAdministrative.value ? Boolean(role.can_manage_student_attendance) : false,
            can_manage_academic_planning: showRolePermissionToggles.value && isAdministrative.value ? Boolean(role.can_manage_academic_planning) : false,
            can_manage_student_leaves: showRolePermissionToggles.value && isAdministrative.value ? Boolean(role.can_manage_student_leaves) : false,
        }))
        .filter((role) => role.org_structure_role_template_id),
});

const submit = () => {
    if (isEditing.value) {
        form.transform(() => payload()).put(route('departments.update', editId.value), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });

        return;
    }

    form.transform(() => payload()).post(route('departments.store'), {
        onSuccess: () => {
            isModalOpen.value = false;
        },
    });
};

const deleteDept = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الإدارة',
        message: 'سيتم حذف الإدارة من الهيكل الحالي. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف الإدارة',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route('departments.destroy', id));
};

const roleTemplateError = (index) => form.errors[`org_structure_roles.${index}.org_structure_role_template_id`] || '';
</script>

<template>
    <Head title="إدارة الإدارات وأدوارها" />

    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="flex items-center gap-2 text-2xl font-bold text-white">
                <Building2 class="h-6 w-6 text-purple-500" />
                الهيكل الإداري والأدوار
            </h1>
            <button
                @click="openCreateModal"
                class="flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 font-bold text-white transition hover:bg-purple-700"
            >
                <Plus class="h-4 w-4" />
                إضافة إدارة جديدة
            </button>
        </div>

        <div class="ui-mobile-card-list">
            <article v-for="department in departments" :key="`department-mobile-${department.id}`" class="ui-mobile-row-card space-y-4 text-right">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-white">{{ department.name }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ staffTypeLabel(department.staff_type) }}</p>
                    </div>
                    <span class="rounded bg-gray-700 px-2 py-1 text-xs text-gray-200">{{ department.users_count }} مستخدم</span>
                </div>

                <div>
                    <p class="ui-mobile-row-label">الأدوار</p>
                    <div class="mt-2 flex flex-wrap justify-end gap-1">
                        <span
                            v-for="roleItem in department.roles"
                            :key="roleItem.id"
                            class="inline-flex items-center gap-1 rounded bg-gray-700 px-2 py-1 text-xs"
                        >
                            <span>{{ resolveTemplateLabel(roleItem.org_structure_role_template_id, roleItem.name) }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="ui-icon-button"
                        :aria-label="`تعديل الإدارة ${department.name}`"
                        @click="openEditModal(department)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="ui-icon-button"
                        :aria-label="`حذف الإدارة ${department.name}`"
                        @click="deleteDept(department.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </article>
        </div>

        <div class="hidden overflow-hidden rounded-xl border border-gray-700 bg-gray-800 shadow-lg lg:block">
            <table class="w-full text-right text-gray-300">
                <thead class="bg-gray-900/50 text-xs font-bold uppercase text-gray-400">
                    <tr>
                        <th class="px-6 py-4">اسم الإدارة</th>
                        <th class="px-6 py-4">النوع</th>
                        <th class="px-6 py-4">الأدوار</th>
                        <th class="px-6 py-4">عدد المستخدمين</th>
                        <th class="px-6 py-4 text-left">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr v-for="department in departments" :key="department.id" class="transition hover:bg-gray-700/50">
                        <td class="px-6 py-4 font-bold text-white">{{ department.name }}</td>
                        <td class="px-6 py-4">{{ staffTypeLabel(department.staff_type) }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="roleItem in department.roles"
                                    :key="roleItem.id"
                                    class="inline-flex items-center gap-1 rounded bg-gray-700 px-2 py-1 text-xs"
                                >
                                    <span>{{ resolveTemplateLabel(roleItem.org_structure_role_template_id, roleItem.name) }}</span>
                                    <span
                                        v-if="showRolePermissionToggles && department.staff_type === 'ADMINISTRATIVE' && roleItem.can_manage_student_structure"
                                        class="rounded bg-blue-500/20 px-1.5 py-0.5 text-[10px] text-blue-200"
                                    >
                                        الهيكل الطلابي
                                    </span>
                                    <span
                                        v-if="showRolePermissionToggles && department.staff_type === 'ADMINISTRATIVE' && roleItem.can_manage_student_attendance"
                                        class="rounded bg-emerald-500/20 px-1.5 py-0.5 text-[10px] text-emerald-200"
                                    >
                                        الحضور اليومي
                                    </span>
                                    <span
                                        v-if="showRolePermissionToggles && department.staff_type === 'ADMINISTRATIVE' && roleItem.can_manage_academic_planning"
                                        class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] text-amber-200"
                                    >
                                        الهيكل الدراسي
                                    </span>
                                    <span
                                        v-if="showRolePermissionToggles && department.staff_type === 'ADMINISTRATIVE' && roleItem.can_manage_student_leaves"
                                        class="rounded bg-indigo-500/20 px-1.5 py-0.5 text-[10px] text-indigo-200"
                                    >
                                        إجازات الطلاب
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="rounded bg-gray-700 px-2 py-1 text-xs">{{ department.users_count }} مستخدم</span>
                        </td>
                        <td class="flex justify-end gap-3 px-6 py-4">
                            <button
                                type="button"
                                :aria-label="`تعديل الإدارة ${department.name}`"
                                @click="openEditModal(department)"
                                class="rounded-lg p-2 text-blue-400 transition hover:bg-blue-500/10"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                :aria-label="`حذف الإدارة ${department.name}`"
                                @click="deleteDept(department.id)"
                                class="rounded-lg p-2 text-red-400 transition hover:bg-red-500/10"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="departments.length === 0">
                        <td colspan="5" class="py-8 text-center text-gray-500">لا توجد إدارات مضافة بعد.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isModalOpen" class="ui-theme-modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="ui-theme-modal-panel max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/10 bg-gray-900 p-5 shadow-2xl sm:p-6">
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white">
                        {{ isEditing ? 'تعديل الإدارة' : 'إضافة إدارة جديدة' }}
                    </h3>
                    <button @click="isModalOpen = false" class="text-gray-400 hover:text-white">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">اسم الإدارة</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="مثال: الشؤون الإدارية"
                                class="w-full rounded-lg border-gray-700 bg-gray-800 text-white focus:border-purple-500 focus:ring-purple-500"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-gray-400">النوع</label>
                            <select
                                v-model="form.staff_type"
                                class="w-full rounded-lg border-gray-700 bg-gray-800 text-white focus:border-purple-500 focus:ring-purple-500"
                            >
                                <option v-for="staffType in staffTypes" :key="staffType" :value="staffType">
                                    {{ staffTypeLabel(staffType) }}
                                </option>
                            </select>
                            <p v-if="form.errors.staff_type" class="mt-1 text-xs text-red-500">{{ form.errors.staff_type }}</p>
                        </div>
                    </div>

                    <div class="rounded border border-gray-700 bg-gray-800/50 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm text-gray-300">أدوار الهيكل الإداري (من قوالب السوبر أدمن)</label>
                            <button
                                type="button"
                                class="rounded bg-gray-700 px-2 py-1 text-xs text-white hover:bg-gray-600"
                                @click="addRoleRow"
                            >
                                إضافة دور
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div v-for="(role, index) in form.org_structure_roles" :key="index" class="rounded border border-gray-700 bg-gray-900 p-2">
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto_auto_auto_auto] md:items-center">
                                    <div>
                                        <select
                                            v-model="role.org_structure_role_template_id"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm text-white"
                                        >
                                            <option value="" disabled>اختر قالب الدور</option>
                                            <option
                                                v-for="template in templateOptions"
                                                :key="template.id"
                                                :value="template.id"
                                                :disabled="!template.is_active && Number(role.org_structure_role_template_id) !== Number(template.id)"
                                            >
                                                {{ template.name }}{{ template.is_active ? '' : ' (معطل)' }}
                                            </option>
                                        </select>
                                        <p v-if="roleTemplateError(index)" class="mt-1 text-xs text-red-500">
                                            {{ roleTemplateError(index) }}
                                        </p>
                                    </div>

                                    <label v-if="showRolePermissionToggles" class="inline-flex items-center gap-2 text-xs text-gray-300">
                                        <input
                                            v-model="role.can_manage_student_structure"
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-800 text-blue-500"
                                            :disabled="!isAdministrative"
                                        />
                                        <span>صلاحية الهيكل الطلابي</span>
                                    </label>

                                    <label v-if="showRolePermissionToggles" class="inline-flex items-center gap-2 text-xs text-gray-300">
                                        <input
                                            v-model="role.can_manage_student_attendance"
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-800 text-emerald-500"
                                            :disabled="!isAdministrative"
                                        />
                                        <span>صلاحية الحضور اليومي</span>
                                    </label>

                                    <label v-if="showRolePermissionToggles" class="inline-flex items-center gap-2 text-xs text-gray-300">
                                        <input
                                            v-model="role.can_manage_academic_planning"
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-800 text-amber-500"
                                            :disabled="!isAdministrative"
                                        />
                                        <span>صلاحية الهيكل الدراسي</span>
                                    </label>

                                    <label v-if="showRolePermissionToggles" class="inline-flex items-center gap-2 text-xs text-gray-300">
                                        <input
                                            v-model="role.can_manage_student_leaves"
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-800 text-indigo-500"
                                            :disabled="!isAdministrative"
                                        />
                                        <span>صلاحية إجازات الطلاب</span>
                                    </label>

                                    <button
                                        type="button"
                                        class="rounded bg-red-700 px-2 py-1 text-xs text-white hover:bg-red-600 disabled:opacity-50"
                                        :disabled="form.org_structure_roles.length <= 1"
                                        @click="removeRoleRow(index)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.org_structure_roles" class="mt-2 text-xs text-red-500">{{ form.errors.org_structure_roles }}</p>
                        <p v-if="!hasActiveTemplateOptions" class="mt-2 text-xs text-amber-300">
                            لا توجد قوالب أدوار مفعلة. أضفها أولًا من صفحة "إدارة أدوار المستخدمين" في السوبر أدمن.
                        </p>
                        <p v-if="showRolePermissionToggles && !isAdministrative" class="mt-2 text-xs text-amber-300">
                            صلاحيات الهيكل الطلابي والحضور اليومي والهيكل الدراسي وإجازات الطلاب متاحة فقط للأدوار التابعة للإدارات الإدارية.
                        </p>
                        <p v-if="!showRolePermissionToggles" class="mt-2 text-xs text-amber-300">
                            صلاحيات الهيكل الطلابي والحضور اليومي والهيكل الدراسي وإجازات الطلاب يتم إسنادها من مدير المدرسة عند إنشاء المستخدم داخل المدرسة.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="flex-1 rounded-lg bg-gray-800 py-2.5 text-white transition hover:bg-gray-700"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing || (!hasActiveTemplateOptions && !isEditing)"
                            class="flex-1 rounded-lg bg-purple-600 py-2.5 font-bold text-white transition hover:bg-purple-700 disabled:opacity-60"
                        >
                            {{ isEditing ? 'حفظ التعديلات' : 'إضافة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
