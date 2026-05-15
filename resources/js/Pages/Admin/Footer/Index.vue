<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Columns, Link2, Plus, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppModal from '@/Components/AppModal.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    columns: { type: Array, default: () => [] },
});

const actionDialog = useActionDialog();
const colForm = useForm({ title: '' });
const itemForm = useForm({ footer_column_id: null, label: '', url: '#' });

const showColumnModal = ref(false);
const showItemModal = ref(false);

const totalLinks = computed(() => props.columns.reduce((total, column) => total + (Array.isArray(column.items) ? column.items.length : 0), 0));

const openColumnModal = () => {
    colForm.reset();
    colForm.clearErrors();
    showColumnModal.value = true;
};

const closeColumnModal = () => {
    showColumnModal.value = false;
    colForm.clearErrors();
};

const submitCol = () => {
    colForm.post(route('admin.footer.column.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeColumnModal();
            colForm.reset();
        },
    });
};

const openItemModal = (colId) => {
    itemForm.reset();
    itemForm.clearErrors();
    itemForm.footer_column_id = colId;
    itemForm.url = '#';
    showItemModal.value = true;
};

const closeItemModal = () => {
    showItemModal.value = false;
    itemForm.reset();
    itemForm.clearErrors();
};

const submitItem = () => {
    itemForm.post(route('admin.footer.item.store'), {
        preserveScroll: true,
        onSuccess: () => closeItemModal(),
    });
};

const deleteCol = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف العمود',
        message: 'سيتم حذف هذا العمود وجميع روابطه المرتبطة. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route('admin.footer.column.delete', id), { preserveScroll: true });
};

const deleteItem = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الرابط',
        message: 'سيتم حذف هذا الرابط من الفوتر نهائيًا. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route('admin.footer.item.delete', id), { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <div class="space-y-6" dir="rtl">
            <section class="rounded-[1.75rem] border border-slate-700/70 bg-slate-900/90 p-6 text-right shadow-lg shadow-slate-950/20">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-3xl">
                        <p class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                            <Columns class="h-4 w-4" />
                            <span>إدارة الفوتر</span>
                        </p>
                        <h1 class="mt-4 text-2xl font-black text-white sm:text-3xl">أعمدة وروابط الفوتر</h1>
                        <p class="mt-3 text-sm leading-7 text-slate-400">
                            تعرض الصفحة الأعمدة والروابط الحالية فقط، بينما تتم إضافة الأعمدة والروابط من نوافذ مستقلة حتى يبقى العرض اليومي أبسط وأكثر تركيزًا.
                        </p>
                    </div>

                    <button type="button" class="ui-primary-button inline-flex items-center gap-2 self-start" @click="openColumnModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة عمود</span>
                    </button>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">الأعمدة</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ columns.length }}</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">الروابط</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ totalLinks }}</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-400">حالة الصفحة</p>
                        <p class="mt-2 text-sm font-bold text-slate-200">عرض فقط + نوافذ إدخال منفصلة</p>
                    </article>
                </div>
            </section>

            <section v-if="columns.length === 0" class="rounded-[1.5rem] border border-slate-700/70 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/20">
                <AppStatePanel
                    variant="empty"
                    compact
                    title="لا توجد أعمدة فوتر بعد"
                    description="ابدأ بإضافة عمود جديد، ثم أضف الروابط المرتبطة به من داخل نافذة مستقلة."
                />
            </section>

            <section v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="col in columns" :key="col.id" class="rounded-[1.5rem] border border-slate-700/70 bg-slate-900/90 p-5 text-right shadow-lg shadow-slate-950/20">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-800/80 pb-3">
                        <div>
                            <h2 class="text-lg font-black text-white">{{ col.title }}</h2>
                            <p class="mt-1 text-xs text-slate-400">{{ Array.isArray(col.items) ? col.items.length : 0 }} روابط ضمن هذا العمود</p>
                        </div>
                        <button type="button" class="rounded-xl p-2 text-rose-300 transition hover:bg-rose-500/10" :aria-label="`حذف العمود ${col.title}`" @click="deleteCol(col.id)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <ul class="mt-4 space-y-2">
                        <li v-for="item in col.items" :key="item.id" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-800/80 bg-slate-950/50 px-3 py-2 text-sm text-slate-200">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white">{{ item.label }}</p>
                                <p class="mt-1 truncate text-xs text-slate-400" dir="ltr">{{ item.url }}</p>
                            </div>
                            <button type="button" class="rounded-lg p-2 text-rose-300 transition hover:bg-rose-500/10" :aria-label="`حذف الرابط ${item.label}`" @click="deleteItem(item.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </li>
                    </ul>

                    <button type="button" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-600 px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/5" @click="openItemModal(col.id)">
                        <Link2 class="h-4 w-4" />
                        <span>إضافة رابط</span>
                    </button>
                </article>
            </section>
        </div>

        <AppModal
            :open="showColumnModal"
            title="إضافة عمود فوتر"
            description="أدخل عنوان العمود فقط، ثم أضف الروابط التابعة له من نافذة مستقلة."
            max-width-class="max-w-xl"
            @close="closeColumnModal"
        >
            <div class="space-y-3">
                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">عنوان العمود</span>
                    <input v-model="colForm.title" type="text" class="ui-input" placeholder="مثال: روابط مهمة" />
                    <p v-if="colForm.errors.title" class="ui-field-error">{{ colForm.errors.title }}</p>
                </label>
            </div>

            <template #footer>
                <div class="text-xs text-slate-500">سيظل العرض الرئيسي مخصصًا للقائمة فقط بعد إضافة العمود.</div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <button type="button" class="ui-secondary-button w-full sm:w-auto" @click="closeColumnModal">إلغاء</button>
                    <button type="button" class="ui-primary-button w-full sm:w-auto" :disabled="colForm.processing" @click="submitCol">
                        {{ colForm.processing ? 'جارٍ الحفظ...' : 'حفظ العمود' }}
                    </button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="showItemModal"
            title="إضافة رابط للفوتر"
            description="أدخل نص الرابط والمسار أو الرابط الكامل داخل نافذة مستقلة."
            max-width-class="max-w-xl"
            @close="closeItemModal"
        >
            <div class="space-y-4">
                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">نص الرابط</span>
                    <input v-model="itemForm.label" class="ui-input" placeholder="مثال: من نحن" />
                    <p v-if="itemForm.errors.label" class="ui-field-error">{{ itemForm.errors.label }}</p>
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span class="font-semibold text-white">الرابط</span>
                    <input v-model="itemForm.url" class="ui-input" dir="ltr" placeholder="/about" />
                    <p v-if="itemForm.errors.url" class="ui-field-error">{{ itemForm.errors.url }}</p>
                </label>
            </div>

            <template #footer>
                <div class="text-xs text-slate-500">يرتبط الرابط بالعمود المحدد دون تغيير في المسارات أو منطق الحفظ الحالي.</div>
                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <button type="button" class="ui-secondary-button w-full sm:w-auto" @click="closeItemModal">إلغاء</button>
                    <button type="button" class="ui-primary-button w-full sm:w-auto" :disabled="itemForm.processing" @click="submitItem">
                        {{ itemForm.processing ? 'جارٍ الحفظ...' : 'حفظ الرابط' }}
                    </button>
                </div>
            </template>
        </AppModal>
    </AdminLayout>
</template>
