<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    staff: {
        type: Array,
        default: () => [],
    },
    associationRequests: {
        type: Array,
        default: () => [],
    },
});

const staff = computed(() => props.staff || []);
const page = usePage();
const actionDialog = useActionDialog();
const currentUserId = computed(() => Number(page.props?.auth?.user?.id || 0));

const tickets = ref([]);
const associationRequests = ref([...props.associationRequests]);
const subtaskForms = ref({});
const finalReportByTicket = ref({});
const subtaskErrors = ref({});
const finalErrors = ref({});
const loadError = ref('');
const loading = ref(false);
const actionLoading = ref({});

const taskForm = ref({
    title: '',
    description: '',
    priority: 'MEDIUM',
    due_date: '',
    assigned_to: staff.value[0]?.id ?? '',
});
const taskFormErrors = ref({});
const taskFormErrorMessage = ref('');

const statusClass = (status) => {
    if (status === 'CLOSED') return 'bg-gray-700 text-gray-100';
    if (status === 'WAITING_SUPERVISOR_REVIEW') return 'bg-amber-700 text-white';
    if (status === 'WAITING_MANAGER_REVIEW') return 'bg-indigo-700 text-white';
    if (status === 'IN_PROGRESS') return 'bg-blue-700 text-white';
    return 'bg-emerald-700 text-white';
};

const associationStatusClass = (status) => {
    if (status === 'APPROVED') return 'bg-emerald-700 text-white';
    if (status === 'REJECTED') return 'bg-red-700 text-white';
    return 'bg-amber-700 text-white';
};

const associationStatusLabel = (status) => {
    if (status === 'PENDING') return 'قيد الانتظار';
    if (status === 'APPROVED') return 'مقبول';
    if (status === 'REJECTED') return 'مرفوض';
    return status || '-';
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

const priorityLabel = (priority) => {
    if (priority === 'HIGH') return 'عالية';
    if (priority === 'MEDIUM') return 'متوسطة';
    if (priority === 'LOW') return 'منخفضة';
    return 'عادية';
};

const staffTypeLabel = (type) => {
    if (type === 'ADMINISTRATIVE') return 'إداري';
    if (type === 'EDUCATIONAL') return 'تعليمي';
    return 'غير محدد';
};

const assigneeLabel = (member) => {
    if (!member) return '-';
    const parts = [member.name];
    if (member.department?.name) parts.push(member.department.name);
    if (member.school_staff_type) parts.push(staffTypeLabel(member.school_staff_type));
    if (member.department_role?.name) parts.push(member.department_role.name);
    return parts.join(' - ');
};

const isInternalTicket = (ticket) => Number(ticket.created_by) === currentUserId.value;
const canAddSubtask = (ticket) => ticket?.status !== 'CLOSED';

const ticketSourceLabel = (ticket) => (isInternalTicket(ticket) ? 'مهمة داخلية' : 'مهمة واردة من المشرف');

const ticketSourceClass = (ticket) => (isInternalTicket(ticket)
    ? 'bg-blue-500/20 text-blue-200'
    : 'bg-amber-500/20 text-amber-200');

const ensureSubtaskForm = (ticketId) => {
    if (!subtaskForms.value[ticketId]) {
        subtaskForms.value[ticketId] = {
            title: '',
            description: '',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };
    }

    return subtaskForms.value[ticketId];
};

const normalizeTicketForms = (items) => {
    items.forEach((ticket) => {
        ensureSubtaskForm(ticket.id);
        if (typeof finalReportByTicket.value[ticket.id] === 'undefined') {
            finalReportByTicket.value[ticket.id] = ticket.manager_final_report || '';
        }
    });
};

const loadTickets = async () => {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(route('manager.tickets.index'));
        tickets.value = response.data;
        normalizeTicketForms(response.data || []);
    } catch (error) {
        loadError.value = error?.response?.data?.message || 'تعذر تحميل المهام.';
    } finally {
        loading.value = false;
    }
};

const loadAssociationRequests = async () => {
    try {
        const response = await axios.get(route('association_requests.index'));
        associationRequests.value = response.data;
    } catch (error) {
        loadError.value = error?.response?.data?.message || 'تعذر تحميل طلبات المصافحة.';
    }
};

const approveAssociation = async (id) => {
    actionLoading.value[`association-${id}`] = true;
    try {
        await axios.post(route('association_requests.approve', id));
        await Promise.all([loadAssociationRequests(), loadTickets()]);
    } finally {
        actionLoading.value[`association-${id}`] = false;
    }
};

const rejectAssociation = async (id) => {
    actionLoading.value[`association-${id}`] = true;
    try {
        await axios.post(route('association_requests.reject', id), {
            notes: 'rejected_by_manager',
        });
        await loadAssociationRequests();
    } finally {
        actionLoading.value[`association-${id}`] = false;
    }
};

const createInternalTask = async () => {
    taskFormErrors.value = {};
    taskFormErrorMessage.value = '';

    try {
        await axios.post(route('manager.tickets.store'), {
            title: taskForm.value.title,
            description: taskForm.value.description || null,
            priority: taskForm.value.priority || 'MEDIUM',
            due_date: taskForm.value.due_date || null,
            assigned_to: taskForm.value.assigned_to,
        });

        taskForm.value = {
            title: '',
            description: '',
            priority: 'MEDIUM',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };

        await loadTickets();
    } catch (error) {
        taskFormErrors.value = error?.response?.data?.errors || {};
        taskFormErrorMessage.value = error?.response?.data?.message || 'تعذر إنشاء المهمة الداخلية.';
    }
};

const createSubtask = async (ticketId) => {
    const payload = ensureSubtaskForm(ticketId);
    subtaskErrors.value[ticketId] = '';

    try {
        await axios.post(route('manager.subtasks.store'), {
            ticket_id: ticketId,
            title: payload.title,
            description: payload.description || null,
            due_date: payload.due_date || null,
            assigned_to: payload.assigned_to,
        });

        subtaskForms.value[ticketId] = {
            title: '',
            description: '',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };

        await loadTickets();
    } catch (error) {
        subtaskErrors.value[ticketId] = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {}).flat().join(' | ')
            || 'تعذر إنشاء المهمة الفرعية.';
    }
};

const approveSubtask = async (subtaskId) => {
    actionLoading.value[`subtask-${subtaskId}`] = true;
    try {
        await axios.post(route('manager.subtasks.approve', subtaskId));
        await loadTickets();
    } finally {
        actionLoading.value[`subtask-${subtaskId}`] = false;
    }
};

const submitFinalReport = async (ticketId) => {
    finalErrors.value[ticketId] = '';

    try {
        await axios.post(route('manager.tickets.final_report', ticketId), {
            manager_final_report: finalReportByTicket.value[ticketId] || '',
        });
        await loadTickets();
    } catch (error) {
        finalErrors.value[ticketId] = error?.response?.data?.errors?.manager_final_report?.[0]
            || error?.response?.data?.message
            || 'تعذر إرسال التقرير النهائي.';
    }
};

const closeInternalTicket = async (ticketId) => {
    const confirmed = await actionDialog.confirm({
        title: 'إغلاق المهمة الداخلية',
        message: 'سيتم إغلاق المهمة الحالية ومنع متابعة العمل عليها. هل تريد المتابعة؟',
        confirmText: 'نعم، أغلق المهمة',
        cancelText: 'إلغاء',
        variant: 'warning',
    });
    if (!confirmed) return;

    actionLoading.value[`close-${ticketId}`] = true;
    try {
        await axios.post(route('manager.tickets.close', ticketId));
        await loadTickets();
    } finally {
        actionLoading.value[`close-${ticketId}`] = false;
    }
};

onMounted(async () => {
    await Promise.allSettled([loadAssociationRequests(), loadTickets()]);
});
</script>

<template>
    <Head title="لوحة مدير المدرسة" />

    <RoleLayout title="لوحة مدير المدرسة" role="SCHOOL_MANAGER" :animate-background="true">
        <div class="ui-page-shell manager-dashboard-shell space-y-6">
            <div
                v-if="loadError"
                class="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"
            >
                {{ loadError }}
            </div>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-bold">إنشاء مهمة داخلية للهيكل المدرسي</h2>
                </div>
                <p class="mb-4 text-xs text-gray-400">
                    يمكنك إنشاء مهمة مباشرة لأي مستخدم ضمن الهيكل التعليمي أو الإداري لنفس مدرستك فقط.
                </p>

                <form class="space-y-3" @submit.prevent="createInternalTask">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div>
                            <input
                                v-model="taskForm.title"
                                class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                placeholder="عنوان المهمة"
                            />
                            <p v-if="taskFormErrors.title" class="mt-1 text-xs text-red-400">{{ taskFormErrors.title[0] }}</p>
                        </div>
                        <div>
                            <select
                                v-model="taskForm.assigned_to"
                                class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                :disabled="staff.length === 0"
                            >
                                <option value="" disabled>اختر مستخدمًا من نفس المدرسة</option>
                                <option v-for="member in staff" :key="member.id" :value="member.id">
                                    {{ assigneeLabel(member) }}
                                </option>
                            </select>
                            <p v-if="taskFormErrors.assigned_to" class="mt-1 text-xs text-red-400">{{ taskFormErrors.assigned_to[0] }}</p>
                        </div>
                    </div>

                    <textarea
                        v-model="taskForm.description"
                        rows="3"
                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                        placeholder="وصف المهمة"
                    />
                    <p v-if="taskFormErrors.description" class="text-xs text-red-400">{{ taskFormErrors.description[0] }}</p>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الأولوية</label>
                            <select v-model="taskForm.priority" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="LOW">منخفضة</option>
                                <option value="MEDIUM">متوسطة</option>
                                <option value="HIGH">عالية</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">تاريخ الاستحقاق</label>
                            <input v-model="taskForm.due_date" type="date" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button
                                class="w-full rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600 disabled:opacity-60"
                                :disabled="staff.length === 0"
                            >
                                إنشاء المهمة
                            </button>
                        </div>
                    </div>

                    <p v-if="taskFormErrorMessage" class="text-xs text-red-400">{{ taskFormErrorMessage }}</p>
                    <p v-if="staff.length === 0" class="text-xs text-amber-300">
                        لا يوجد مستخدمون نشطون في الهيكل المدرسي لإسناد المهام حاليًا.
                    </p>
                </form>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-bold">طلبات المصافحة</h2>
                        <button class="text-xs text-blue-400 hover:underline" @click="loadAssociationRequests">تحديث</button>
                    </div>

                    <div v-if="associationRequests.length === 0" class="rounded border border-dashed border-gray-700 p-3 text-sm text-gray-400">
                        لا توجد طلبات مصافحة حاليًا.
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="item in associationRequests"
                            :key="item.id"
                            class="rounded border border-gray-700 bg-gray-800 p-3"
                        >
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <p class="text-sm font-bold">{{ item.title }}</p>
                                <span class="rounded px-2 py-1 text-xs font-bold" :class="associationStatusClass(item.status)">
                                    {{ associationStatusLabel(item.status) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400">
                                {{ item.school?.name }} - المشرف: {{ item.supervisor?.name || '-' }}
                            </p>

                            <div v-if="item.status === 'PENDING'" class="mt-3 flex flex-wrap gap-2">
                                <button
                                    class="rounded bg-emerald-700 px-2 py-1 text-xs hover:bg-emerald-600 disabled:opacity-60"
                                    :disabled="actionLoading[`association-${item.id}`]"
                                    @click="approveAssociation(item.id)"
                                >
                                    موافقة
                                </button>
                                <button
                                    class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600 disabled:opacity-60"
                                    :disabled="actionLoading[`association-${item.id}`]"
                                    @click="rejectAssociation(item.id)"
                                >
                                    رفض
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5 lg:col-span-2">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-bold">مهام المدرسة</h2>
                        <button class="text-xs text-blue-400 hover:underline" @click="loadTickets">تحديث</button>
                    </div>

                    <div v-if="loading" class="text-sm text-gray-400">جار تحميل المهام...</div>

                    <div v-else-if="tickets.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                        لا توجد مهام مرتبطة بحسابك.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            class="rounded border border-gray-700 bg-gray-800 p-3"
                        >
                            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <p class="font-bold">{{ ticket.title }}</p>
                                        <span class="rounded px-2 py-0.5 text-[11px]" :class="ticketSourceClass(ticket)">
                                            {{ ticketSourceLabel(ticket) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        {{ ticket.school?.name }} - {{ priorityLabel(ticket.priority) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        تاريخ الاستحقاق: {{ ticket.due_date || 'غير محدد' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-1 text-xs font-bold" :class="statusClass(ticket.status)">
                                        {{ ticketStatusLabel(ticket.status) }}
                                    </span>
                                    <button
                                        v-if="isInternalTicket(ticket) && ticket.status !== 'CLOSED'"
                                        class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600 disabled:opacity-60"
                                        :disabled="actionLoading[`close-${ticket.id}`]"
                                        @click="closeInternalTicket(ticket.id)"
                                    >
                                        إغلاق
                                    </button>
                                </div>
                            </div>

                            <p class="mb-3 text-sm text-gray-300">{{ ticket.description }}</p>

                            <div class="mb-3 rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">المهام الفرعية الحالية</p>
                                <div v-if="(ticket.subtasks || []).length === 0" class="text-xs text-gray-500">
                                    لا توجد مهام فرعية.
                                </div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="subtask in ticket.subtasks"
                                        :key="subtask.id"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <p class="font-bold">{{ subtask.title }}</p>
                                                <p class="text-gray-400">
                                                    {{ subtask.assignee?.name || `#${subtask.assigned_to}` }} - {{ subtaskStatusLabel(subtask.status) }}
                                                </p>
                                            </div>
                                            <button
                                                v-if="subtask.status === 'SUBMITTED'"
                                                class="rounded bg-emerald-700 px-2 py-1 hover:bg-emerald-600 disabled:opacity-60"
                                                :disabled="actionLoading[`subtask-${subtask.id}`]"
                                                @click="approveSubtask(subtask.id)"
                                            >
                                                اعتماد
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">إضافة مهمة فرعية</p>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <input
                                        v-model="ensureSubtaskForm(ticket.id).title"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        placeholder="عنوان المهمة الفرعية"
                                        :disabled="!canAddSubtask(ticket)"
                                    />
                                    <select
                                        v-model="ensureSubtaskForm(ticket.id).assigned_to"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        :disabled="staff.length === 0 || !canAddSubtask(ticket)"
                                    >
                                        <option value="" disabled>اختر مستخدمًا من نفس المدرسة</option>
                                        <option v-for="member in staff" :key="member.id" :value="member.id">
                                            {{ assigneeLabel(member) }}
                                        </option>
                                    </select>
                                </div>

                                <textarea
                                    v-model="ensureSubtaskForm(ticket.id).description"
                                    rows="2"
                                    class="mt-2 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                    placeholder="وصف المهمة"
                                    :disabled="!canAddSubtask(ticket)"
                                />

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <input
                                        v-model="ensureSubtaskForm(ticket.id).due_date"
                                        type="date"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        :disabled="!canAddSubtask(ticket)"
                                    />
                                    <button
                                        class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600 disabled:opacity-60"
                                        :disabled="staff.length === 0 || !canAddSubtask(ticket)"
                                        @click="createSubtask(ticket.id)"
                                    >
                                        إنشاء مهمة فرعية
                                    </button>
                                </div>

                                <p v-if="subtaskErrors[ticket.id]" class="mt-2 text-xs text-red-400">{{ subtaskErrors[ticket.id] }}</p>
                                <p v-if="staff.length === 0" class="mt-2 text-xs text-amber-300">
                                    لا يوجد مستخدمون نشطون لإسناد المهام الفرعية.
                                </p>
                            </div>

                            <div v-if="!isInternalTicket(ticket)" class="rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">التقرير النهائي للمدير</p>
                                <textarea
                                    v-model="finalReportByTicket[ticket.id]"
                                    rows="3"
                                    class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                    placeholder="اكتب ملخص التنفيذ النهائي"
                                />
                                <div class="mt-2 flex justify-end">
                                    <button class="rounded bg-emerald-700 px-3 py-2 text-sm font-bold hover:bg-emerald-600" @click="submitFinalReport(ticket.id)">
                                        إرسال التقرير النهائي
                                    </button>
                                </div>
                                <p v-if="finalErrors[ticket.id]" class="mt-2 text-xs text-red-400">{{ finalErrors[ticket.id] }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </RoleLayout>
</template>
