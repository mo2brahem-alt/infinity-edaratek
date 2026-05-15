<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { RefreshCcw } from 'lucide-vue-next';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    requests: {
        type: Array,
        default: () => [],
    },
});

const items = ref([...props.requests]);
const loading = ref(false);

const loadItems = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('manager.requests.index'));
        items.value = response.data;
    } finally {
        loading.value = false;
    }
};

const approve = async (id) => {
    await axios.post(route('manager.requests.approve', id));
    await loadItems();
};

const reject = async (id) => {
    await axios.post(route('manager.requests.reject', id), { notes: 'rejected_by_manager' });
    await loadItems();
};

const requestStatusLabel = (status) => {
    if (status === 'SUPERVISOR_REQUESTED') return 'بانتظار موافقة المدير';
    if (status === 'MANAGER_APPROVED') return 'موافقة المدير';
    if (status === 'ACTIVE_ASSOCIATION') return 'ارتباط مفعل';
    if (status === 'MANAGER_REJECTED') return 'مرفوض من المدير';
    if (status === 'SUPERVISOR_REJECTED') return 'مرفوض من المشرف';
    if (status === 'CANCELED') return 'ملغي';
    return status || '-';
};

const requestStatusClass = (status) => {
    if (status === 'ACTIVE_ASSOCIATION') return 'bg-emerald-500/15 text-emerald-200 border-emerald-400/25';
    if (status === 'MANAGER_APPROVED') return 'bg-blue-500/15 text-blue-200 border-blue-400/25';
    if (status === 'SUPERVISOR_REQUESTED') return 'bg-amber-500/15 text-amber-200 border-amber-400/25';
    return 'bg-rose-500/12 text-rose-200 border-rose-400/25';
};
</script>

<template>
    <Head title="طلبات مدير المدرسة" />

    <RoleLayout title="صندوق طلبات الإشراف" role="SCHOOL_MANAGER">
        <div class="ui-page-shell max-w-5xl">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <h1 class="ui-page-title">طلبات المشرفين لمدرستك</h1>
                        <p class="ui-page-copy">راجع طلبات الارتباط الواردة إلى المدرسة واتخذ القرار المناسب من مكان واضح ومتسق.</p>
                    </div>
                    <button class="ui-ghost-button" type="button" @click="loadItems">
                        <RefreshCcw class="h-4 w-4" />
                        <span>تحديث</span>
                    </button>
                </div>
            </section>

            <section class="ui-section">
                <div v-if="loading">
                    <AppStatePanel variant="loading" title="جارٍ تحميل الطلبات" description="نقوم بجلب آخر طلبات الإشراف الخاصة بمدرستك." compact />
                </div>

                <div v-else-if="items.length === 0">
                    <AppStatePanel title="لا توجد طلبات حالياً" description="ستظهر طلبات المشرفين هنا فور إنشائها أو تحديث حالتها." compact />
                </div>

                <div v-else class="space-y-4">
                    <article v-for="item in items" :key="item.id" class="ui-card-soft p-5 text-right">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-black text-white">{{ item.school?.name }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ item.supervisor?.name }} - {{ item.supervisor?.email }}</p>
                            </div>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold" :class="requestStatusClass(item.status)">
                                {{ requestStatusLabel(item.status) }}
                            </span>
                        </div>

                        <div v-if="item.status === 'SUPERVISOR_REQUESTED'" class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <button class="ui-secondary-button" type="button" @click="approve(item.id)">موافقة مبدئية</button>
                            <button class="ui-action-button" type="button" @click="reject(item.id)">رفض</button>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
