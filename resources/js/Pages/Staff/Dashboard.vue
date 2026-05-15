<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { RefreshCcw } from 'lucide-vue-next';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const subtasks = ref([]);
const loading = ref(false);
const replyById = ref({});
const fileById = ref({});
const errorsById = ref({});
const actionLoading = ref({});

const statusClass = (status) => {
    if (status === 'APPROVED') return 'bg-emerald-500/15 text-emerald-200 border-emerald-400/25';
    if (status === 'SUBMITTED') return 'bg-amber-500/15 text-amber-200 border-amber-400/25';
    if (status === 'IN_PROGRESS') return 'bg-blue-500/15 text-blue-200 border-blue-400/25';
    return 'bg-slate-500/15 text-slate-200 border-slate-400/25';
};

const subtaskStatusLabel = (status) => {
    if (status === 'OPEN') return 'مفتوحة';
    if (status === 'IN_PROGRESS') return 'قيد التنفيذ';
    if (status === 'SUBMITTED') return 'تم التسليم';
    if (status === 'APPROVED') return 'معتمدة';
    return status || '-';
};

const loadSubtasks = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('staff.subtasks.index'));
        subtasks.value = response.data;
    } finally {
        loading.value = false;
    }
};

const onFileChange = (event, subtaskId) => {
    fileById.value[subtaskId] = event.target.files?.[0] || null;
};

const submitReply = async (subtaskId) => {
    const message = replyById.value[subtaskId] || '';
    errorsById.value[subtaskId] = '';

    if (!message.trim()) {
        errorsById.value[subtaskId] = 'الرجاء كتابة رد قبل الإرسال.';
        return;
    }

    actionLoading.value[`reply-${subtaskId}`] = true;

    try {
        const formData = new FormData();
        formData.append('message', message);

        if (fileById.value[subtaskId]) {
            formData.append('attachment', fileById.value[subtaskId]);
        }

        await axios.post(route('staff.subtasks.reply', subtaskId), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        replyById.value[subtaskId] = '';
        fileById.value[subtaskId] = null;
        await loadSubtasks();
    } catch (error) {
        errorsById.value[subtaskId] =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors || {}).flat().join(' | ') ||
            'تعذر إرسال الرد.';
    } finally {
        actionLoading.value[`reply-${subtaskId}`] = false;
    }
};

const submitTask = async (subtaskId) => {
    actionLoading.value[`submit-${subtaskId}`] = true;
    try {
        await axios.post(route('staff.subtasks.submit', subtaskId));
        await loadSubtasks();
    } finally {
        actionLoading.value[`submit-${subtaskId}`] = false;
    }
};

onMounted(loadSubtasks);
</script>

<template>
    <Head title="لوحة الكادر" />

    <RoleLayout title="لوحة الكادر" role="STAFF" :permissions="props.permissions" :animate-background="true">
        <div class="ui-page-shell max-w-6xl">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <h1 class="ui-page-title">المهام الفرعية المسندة إليك</h1>
                        <p class="ui-page-copy">تابع المهام الموكلة إليك، وأرسل التحديثات أو المرفقات، ثم سلّم المهمة عند اكتمال العمل.</p>
                    </div>
                    <button class="ui-ghost-button" type="button" @click="loadSubtasks">
                        <RefreshCcw class="h-4 w-4" />
                        <span>تحديث</span>
                    </button>
                </div>
            </section>

            <section class="ui-section">
                <div v-if="loading">
                    <AppStatePanel variant="loading" title="جارٍ تحميل المهام" description="يتم الآن جلب آخر المهام الفرعية وحالة الردود المرتبطة بها." compact />
                </div>

                <div v-else-if="subtasks.length === 0">
                    <AppStatePanel title="لا توجد مهام مسندة حالياً" description="ستظهر هنا المهام الفرعية الجديدة فور إسنادها إليك." compact />
                </div>

                <div v-else class="space-y-4">
                    <article v-for="subtask in subtasks" :key="subtask.id" class="ui-card-soft p-5 text-right">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-black text-white">{{ subtask.title }}</p>
                                <p class="mt-1 text-sm text-slate-400">
                                    المهمة الأصلية: {{ subtask.ticket?.title }} - المدرسة: {{ subtask.school?.name }}
                                </p>
                            </div>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold" :class="statusClass(subtask.status)">
                                {{ subtaskStatusLabel(subtask.status) }}
                            </span>
                        </div>

                        <p v-if="subtask.description" class="mt-4 text-sm leading-7 text-slate-300">{{ subtask.description }}</p>

                        <div v-if="(subtask.messages || []).length > 0" class="mt-4 rounded-2xl border border-white/10 bg-slate-950/35 p-4">
                            <p class="mb-3 text-xs font-black text-slate-300">آخر الردود</p>
                            <div class="space-y-3">
                                <div v-for="message in subtask.messages" :key="message.id" class="rounded-2xl border border-white/10 bg-slate-900/50 p-3 text-sm">
                                    <p class="text-slate-300">{{ message.message }}</p>
                                    <div v-if="(message.attachments || []).length > 0" class="mt-2 space-y-1">
                                        <a
                                            v-for="attachment in message.attachments"
                                            :key="attachment.id"
                                            :href="attachment.url"
                                            target="_blank"
                                            class="ui-text-link block text-sm"
                                        >
                                            {{ attachment.file_name }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <textarea
                                v-model="replyById[subtask.id]"
                                rows="3"
                                class="ui-textarea"
                                placeholder="أضف تحديثًا أو ردًا على المهمة"
                            />

                            <input
                                type="file"
                                class="block w-full rounded-2xl border border-gray-700 bg-gray-900 p-2 text-xs file:ml-2 file:rounded-xl file:border-0 file:bg-slate-700 file:px-3 file:py-1.5 file:text-white"
                                @change="onFileChange($event, subtask.id)"
                            />

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <button
                                    class="ui-primary-button"
                                    type="button"
                                    :disabled="actionLoading[`reply-${subtask.id}`]"
                                    @click="submitReply(subtask.id)"
                                >
                                    {{ actionLoading[`reply-${subtask.id}`] ? 'جارٍ الإرسال...' : 'إرسال رد' }}
                                </button>
                                <button
                                    class="ui-secondary-button"
                                    type="button"
                                    :disabled="actionLoading[`submit-${subtask.id}`] || subtask.status === 'SUBMITTED' || subtask.status === 'APPROVED'"
                                    @click="submitTask(subtask.id)"
                                >
                                    {{ actionLoading[`submit-${subtask.id}`] ? 'جارٍ التسليم...' : 'تسليم المهمة' }}
                                </button>
                            </div>

                            <p v-if="errorsById[subtask.id]" class="ui-field-error">{{ errorsById[subtask.id] }}</p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
