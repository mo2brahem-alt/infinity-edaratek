<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    schools: {
        type: Array,
        default: () => [],
    },
});

const tickets = ref([]);
const actionDialog = useActionDialog();
const loading = ref(false);
const loadingDetails = ref({});
const ticketDetails = ref({});
const errors = ref({});
const formError = ref('');

const form = reactive({
    title: '',
    description: '',
    priority: 'MEDIUM',
    due_date: '',
    school_id: '',
    assigned_to: '',
});

const selectedSchool = computed(() => {
    return props.schools.find((school) => school.id === Number(form.school_id)) || null;
});

const managerOptions = computed(() => {
    if (!selectedSchool.value?.manager) return [];
    return [selectedSchool.value.manager];
});

watch(
    () => form.school_id,
    () => {
        form.assigned_to = selectedSchool.value?.manager?.id ?? '';
    }
);

const statusClass = (status) => {
    if (status === 'CLOSED') return 'bg-gray-700 text-gray-100';
    if (status === 'WAITING_SUPERVISOR_REVIEW') return 'bg-amber-700 text-white';
    if (status === 'WAITING_MANAGER_REVIEW') return 'bg-indigo-700 text-white';
    if (status === 'IN_PROGRESS') return 'bg-blue-700 text-white';
    return 'bg-emerald-700 text-white';
};

const ticketStatusLabel = (status) => {
    if (status === 'OPEN') return 'مفتوحة';
    if (status === 'IN_PROGRESS') return 'قيد التنفيذ';
    if (status === 'WAITING_MANAGER_REVIEW') return 'بانتظار مراجعة المدير';
    if (status === 'WAITING_SUPERVISOR_REVIEW') return 'بانتظار مراجعة المشرف';
    if (status === 'CLOSED') return 'مغلقة';
    return status || '-';
};

const subtaskStatusLabel = (status) => {
    if (status === 'OPEN') return 'مفتوحة';
    if (status === 'IN_PROGRESS') return 'قيد التنفيذ';
    if (status === 'SUBMITTED') return 'تم التسليم';
    if (status === 'APPROVED') return 'معتمدة';
    return status || '-';
};

const resetForm = () => {
    form.title = '';
    form.description = '';
    form.priority = 'MEDIUM';
    form.due_date = '';
    form.school_id = '';
    form.assigned_to = '';
    errors.value = {};
    formError.value = '';
};

const loadTickets = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('supervisor.tickets.index'));
        tickets.value = response.data;
    } finally {
        loading.value = false;
    }
};

const loadTicketDetails = async (ticketId) => {
    loadingDetails.value[ticketId] = true;
    try {
        const response = await axios.get(route('supervisor.tickets.show', ticketId));
        ticketDetails.value[ticketId] = response.data;
    } finally {
        loadingDetails.value[ticketId] = false;
    }
};

const createTicket = async () => {
    errors.value = {};
    formError.value = '';

    try {
        await axios.post(route('supervisor.tickets.store'), { ...form });
        resetForm();
        await loadTickets();
    } catch (error) {
        errors.value = error?.response?.data?.errors || {};
        formError.value = error?.response?.data?.message || '';
    }
};

const closeTicket = async (ticketId) => {
    const confirmed = await actionDialog.confirm({
        title: 'إغلاق المهمة',
        message: 'سيتم إغلاق المهمة الحالية بعد هذا الإجراء. هل تريد المتابعة؟',
        confirmText: 'نعم، أغلق المهمة',
        cancelText: 'إلغاء',
        variant: 'warning',
    });
    if (!confirmed) return;
    await axios.post(route('supervisor.tickets.close', ticketId));
    await loadTickets();
};

onMounted(loadTickets);
</script>

<template>
    <Head title="لوحة المشرف" />

    <RoleLayout title="لوحة المشرف" role="SUPERVISOR" :animate-background="true">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 lg:col-span-1">
                <h2 class="mb-1 text-lg font-bold">إنشاء مهمة للمدرسة</h2>
                <p class="mb-4 text-xs text-gray-400">
                    يمكن إنشاء مهمة فقط لمدرسة نشطة مرتبطة بك كمشرف.
                </p>

                <form class="space-y-3" @submit.prevent="createTicket">
                    <div>
                        <input
                            v-model="form.title"
                            placeholder="عنوان المهمة"
                            class="w-full rounded border border-gray-700 bg-gray-800 p-2"
                        />
                        <p v-if="errors.title" class="mt-1 text-xs text-red-400">{{ errors.title[0] }}</p>
                    </div>

                    <div>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            placeholder="وصف المهمة"
                            class="w-full rounded border border-gray-700 bg-gray-800 p-2"
                        />
                        <p v-if="errors.description" class="mt-1 text-xs text-red-400">{{ errors.description[0] }}</p>
                    </div>

                    <div>
                        <select
                            v-model="form.school_id"
                            class="w-full rounded border border-gray-700 bg-gray-800 p-2"
                        >
                            <option value="" disabled>اختر مدرسة نشطة</option>
                            <option
                                v-for="school in schools"
                                :key="school.id"
                                :value="school.id"
                            >
                                {{ school.name }} - {{ school.school_id }}
                            </option>
                        </select>
                        <p v-if="errors.school_id" class="mt-1 text-xs text-red-400">{{ errors.school_id[0] }}</p>
                    </div>

                    <div>
                        <select
                            v-model="form.assigned_to"
                            class="w-full rounded border border-gray-700 bg-gray-800 p-2"
                        >
                            <option value="" disabled>مدير المدرسة</option>
                            <option
                                v-for="manager in managerOptions"
                                :key="manager.id"
                                :value="manager.id"
                            >
                                {{ manager.name }} ({{ manager.email }})
                            </option>
                        </select>
                        <p v-if="errors.assigned_to" class="mt-1 text-xs text-red-400">{{ errors.assigned_to[0] }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <select
                            v-model="form.priority"
                            class="rounded border border-gray-700 bg-gray-800 p-2"
                        >
                            <option value="LOW">منخفض</option>
                            <option value="MEDIUM">متوسط</option>
                            <option value="HIGH">مرتفع</option>
                        </select>
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="rounded border border-gray-700 bg-gray-800 p-2"
                        />
                    </div>

                    <p v-if="formError" class="text-xs text-red-400">{{ formError }}</p>

                    <button class="w-full rounded bg-blue-600 p-2 font-bold hover:bg-blue-500">
                        إنشاء المهمة
                    </button>
                </form>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 lg:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">مهام المشرف</h2>
                        <p class="text-xs text-gray-400">عدد المدارس النشطة المسندة: {{ schools.length }}</p>
                    </div>
                    <button class="text-sm text-blue-400 hover:underline" @click="loadTickets">تحديث</button>
                </div>

                <div v-if="loading" class="text-sm text-gray-400">جار تحميل المهام...</div>

                <div v-else-if="tickets.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                    لا توجد مهام حتى الآن.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        class="rounded border border-gray-700 bg-gray-800 p-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-bold">{{ ticket.title }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ ticket.school?.name }} - {{ ticket.manager?.name }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    تاريخ الاستحقاق: {{ ticket.due_date || 'غير محدد' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded px-2 py-1 text-xs font-bold" :class="statusClass(ticket.status)">
                                    {{ ticketStatusLabel(ticket.status) }}
                                </span>
                                <button
                                    class="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600"
                                    :disabled="loadingDetails[ticket.id]"
                                    @click="loadTicketDetails(ticket.id)"
                                >
                                    {{ loadingDetails[ticket.id] ? '...' : 'تفاصيل' }}
                                </button>
                                <button
                                    v-if="ticket.status !== 'CLOSED'"
                                    class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600"
                                    @click="closeTicket(ticket.id)"
                                >
                                    إغلاق
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-gray-300">{{ ticket.description }}</p>

                        <div
                            v-if="ticketDetails[ticket.id]"
                            class="mt-3 space-y-2 rounded border border-gray-700 bg-gray-900 p-3"
                        >
                            <p class="text-xs text-gray-400">
                                المهام الفرعية: {{ ticketDetails[ticket.id].subtasks?.length || 0 }}
                            </p>

                            <div
                                v-for="subtask in ticketDetails[ticket.id].subtasks || []"
                                :key="subtask.id"
                                class="rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                            >
                                <p class="font-bold">{{ subtask.title }}</p>
                                <p class="text-gray-400">
                                    {{ subtask.assignee?.name || 'بدون موظف' }} - {{ subtaskStatusLabel(subtask.status) }}
                                </p>
                            </div>

                            <div v-if="ticketDetails[ticket.id].manager_final_report" class="rounded border border-emerald-700 bg-emerald-900/20 p-2 text-xs">
                                <p class="mb-1 font-bold text-emerald-300">التقرير النهائي من مدير المدرسة</p>
                                <p class="text-gray-200">{{ ticketDetails[ticket.id].manager_final_report }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
