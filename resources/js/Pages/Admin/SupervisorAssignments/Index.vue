<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Link2, Plus, Trash2, UserRoundCheck } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    supervisors: { type: Array, default: () => [] },
    directorates: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
});

const actionDialog = useActionDialog();
const isCreateModalOpen = ref(false);

const form = useForm({
    supervisor_id: '',
    directorate_id: '',
    school_id: '',
    is_active: true,
});

const activeAssignmentsCount = computed(() => props.assignments.filter((assignment) => Boolean(assignment.is_active)).length);

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.is_active = true;
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    form.clearErrors();
};

const submit = () => {
    form.post(route('admin.supervisor_assignments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
            form.reset();
            form.is_active = true;
        },
    });
};

const destroyAssignment = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الإسناد',
        message: 'سيتم حذف هذا الإسناد نهائيًا. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route('admin.supervisor_assignments.destroy', id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="إسناد المشرفين" />

    <AdminLayout>
        <div class="space-y-6" dir="rtl">
            <section class="rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-6 text-right shadow-lg shadow-slate-950/20">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-3xl">
                        <p class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                            <Link2 class="h-4 w-4" />
                            <span>إدارة الربط الإشرافي</span>
                        </p>
                        <h1 class="mt-4 text-2xl font-black text-white sm:text-3xl">إسناد المشرفين للمدارس والنطاقات</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-400">
                            تعرض الصفحة الإسنادات الحالية فقط، بينما تتم إضافة الإسناد الجديد من خلال نافذة مستقلة حتى تبقى القائمة أوضح وأقل ازدحامًا.
                        </p>
                    </div>

                    <button type="button" class="ui-primary-button inline-flex items-center gap-2 self-start" @click="openCreateModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة إسناد</span>
                    </button>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">إجمالي الإسنادات</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ assignments.length }}</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">الإسنادات النشطة</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ activeAssignmentsCount }}</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">المشرفون المتاحون</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ supervisors.length }}</p>
                    </article>
                </div>
            </section>

            <section class="overflow-hidden rounded-[1.5rem] border border-slate-700/70 bg-slate-900/90 shadow-lg shadow-slate-950/20">
                <div class="flex items-center justify-between border-b border-slate-800/80 px-5 py-4">
                    <div class="text-right">
                        <h2 class="text-lg font-black text-white">الإسنادات الحالية</h2>
                        <p class="mt-1 text-sm text-slate-400">راجع الربط الحالي واحذف الإسنادات غير المطلوبة دون فتح نماذج داخل الصفحة.</p>
                    </div>
                    <span class="ui-chip">{{ assignments.length }} سجل</span>
                </div>

                <div v-if="assignments.length === 0" class="p-5">
                    <AppStatePanel
                        variant="empty"
                        compact
                        title="لا توجد إسنادات بعد"
                        description="ابدأ بإضافة أول إسناد من خلال زر الإضافة أعلى الصفحة."
                    />
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-right text-sm text-slate-300">
                        <thead class="bg-slate-950/70 text-xs font-bold text-slate-400">
                            <tr>
                                <th class="px-4 py-3">المشرف</th>
                                <th class="px-4 py-3">النطاق التعليمي</th>
                                <th class="px-4 py-3">المدرسة</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3 text-left">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="assignment in assignments" :key="assignment.id" class="border-t border-slate-800/80">
                                <td class="px-4 py-3">{{ assignment.supervisor?.name || '-' }}</td>
                                <td class="px-4 py-3">{{ assignment.directorate?.name || '-' }}</td>
                                <td class="px-4 py-3">{{ assignment.school?.name || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="ui-chip" :class="assignment.is_active ? 'text-emerald-200' : 'text-slate-300'">
                                        {{ assignment.is_active ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-rose-500/25 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-100 transition hover:bg-rose-500/15" @click="destroyAssignment(assignment.id)">
                                        <Trash2 class="h-4 w-4" />
                                        <span>حذف</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <AppModal
            :open="isCreateModalOpen"
            title="إضافة إسناد إشرافي"
            description="أنشئ الإسناد من نافذة مستقلة مع الحفاظ على الصفحة الأساسية للعرض فقط."
            max-width-class="max-w-2xl"
            @close="closeCreateModal"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span class="font-semibold text-white">المشرف</span>
                        <select v-model="form.supervisor_id" class="ui-select">
                            <option value="" disabled>اختر المشرف</option>
                            <option v-for="user in supervisors" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <p v-if="form.errors.supervisor_id" class="ui-field-error">{{ form.errors.supervisor_id }}</p>
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span class="font-semibold text-white">النطاق التعليمي</span>
                        <select v-model="form.directorate_id" class="ui-select">
                            <option value="">اختياري</option>
                            <option v-for="directorate in directorates" :key="directorate.id" :value="directorate.id">{{ directorate.name }}</option>
                        </select>
                        <p v-if="form.errors.directorate_id" class="ui-field-error">{{ form.errors.directorate_id }}</p>
                    </label>

                    <label class="space-y-2 text-sm text-slate-300 md:col-span-2">
                        <span class="font-semibold text-white">المدرسة</span>
                        <select v-model="form.school_id" class="ui-select">
                            <option value="">اختياري</option>
                            <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }} - {{ school.school_id }}</option>
                        </select>
                        <p v-if="form.errors.school_id" class="ui-field-error">{{ form.errors.school_id }}</p>
                    </label>

                    <label class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-300 md:col-span-2">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" />
                        <span>تفعيل الإسناد مباشرة بعد الحفظ</span>
                    </label>
                </div>

                <p v-if="form.errors.scope" class="ui-field-error">{{ form.errors.scope }}</p>
            </form>

            <template #footer>
                <div class="text-xs text-slate-500">
                    يتم حفظ نفس القيود الحالية على الخادم دون تغيير في الصلاحيات أو نطاق البيانات.
                </div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <button type="button" class="ui-secondary-button w-full sm:w-auto" @click="closeCreateModal">إلغاء</button>
                    <button type="button" class="ui-primary-button inline-flex w-full items-center justify-center gap-2 sm:w-auto" :disabled="form.processing" @click="submit">
                        <UserRoundCheck class="h-4 w-4" />
                        <span>{{ form.processing ? 'جارٍ الحفظ...' : 'حفظ الإسناد' }}</span>
                    </button>
                </div>
            </template>
        </AppModal>
    </AdminLayout>
</template>
