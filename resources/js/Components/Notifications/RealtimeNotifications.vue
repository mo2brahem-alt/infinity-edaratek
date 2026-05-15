<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from 'lucide-vue-next';

const emit = defineEmits(['pulse-routes-changed']);

const notifications = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const isMarkingAll = ref(false);
let pollingTimer = null;

const inertiaRouteAliases = Object.freeze({
    'manager.tickets.index': 'manager.dashboard',
    'manager.tickets.show': 'manager.dashboard',
    'supervisor.tickets.index': 'supervisor.dashboard',
    'supervisor.tickets.show': 'supervisor.dashboard',
    'staff.subtasks.index': 'staff.dashboard',
    'staff.subtasks.show': 'staff.dashboard',
});

const unreadNotifications = computed(() => notifications.value.filter((item) => !item?.read_at));
const unreadCount = computed(() => unreadNotifications.value.length);

const normalizeInertiaRouteName = (routeName) => {
    const normalized = typeof routeName === 'string' ? routeName.trim() : '';
    if (normalized === '') {
        return '';
    }

    return inertiaRouteAliases[normalized] || normalized;
};

const pulseRoutes = computed(() => {
    const names = new Set();

    for (const item of unreadNotifications.value) {
        const data = item?.data && typeof item.data === 'object' ? item.data : {};
        const actionRoute = typeof data.action_route_name === 'string' ? data.action_route_name.trim() : '';
        const routeName = typeof data.route_name === 'string' ? data.route_name.trim() : '';

        const preferredRoute = actionRoute !== '' ? actionRoute : routeName;
        const normalizedRoute = normalizeInertiaRouteName(preferredRoute);
        if (normalizedRoute !== '') {
            names.add(normalizedRoute);
        }
    }

    return Array.from(names);
});

watch(
    pulseRoutes,
    (value) => {
        emit('pulse-routes-changed', value);
    },
    { immediate: true }
);

const fetchNotifications = async () => {
    if (isLoading.value) {
        return;
    }

    isLoading.value = true;

    try {
        const response = await window.axios.get(route('notifications.index'), {
            params: { limit: 60 },
        });

        const payload = Array.isArray(response.data) ? response.data : [];
        notifications.value = payload
            .slice()
            .sort((a, b) => Number(b?.id || 0) - Number(a?.id || 0));
    } catch {
        // keep last successful state
    } finally {
        isLoading.value = false;
    }
};

const startPolling = () => {
    fetchNotifications();
    pollingTimer = setInterval(fetchNotifications, 8000);
};

const stopPolling = () => {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
};

const resolveRawRouteName = (item) => {
    const data = item?.data && typeof item.data === 'object' ? item.data : {};
    const actionRoute = typeof data.action_route_name === 'string' ? data.action_route_name.trim() : '';
    if (actionRoute !== '') {
        return actionRoute;
    }

    const routeName = typeof data.route_name === 'string' ? data.route_name.trim() : '';
    return routeName;
};

const resolveRouteName = (item) => {
    return normalizeInertiaRouteName(resolveRawRouteName(item));
};

const resolveRouteParams = (item, target = 'route') => {
    const data = item?.data && typeof item.data === 'object' ? item.data : {};
    const key = target === 'action' ? 'action_route_params' : 'route_params';
    const value = data?.[key];

    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
};

const resolveHref = (item) => {
    const routeName = resolveRouteName(item);
    if (routeName === '') {
        return '#';
    }

    const data = item?.data && typeof item.data === 'object' ? item.data : {};
    const hasActionRoute = typeof data.action_route_name === 'string' && data.action_route_name.trim() !== '';
    const params = hasActionRoute ? resolveRouteParams(item, 'action') : resolveRouteParams(item, 'route');

    try {
        if (route().has(routeName)) {
            return route(routeName, params);
        }
    } catch {
        return '#';
    }

    return '#';
};

const markAsRead = async (item) => {
    if (!item || item.read_at) {
        return;
    }

    try {
        await window.axios.post(route('notifications.read', item.id));
        item.read_at = new Date().toISOString();
    } catch {
        // no-op
    }
};

const openNotification = async (item) => {
    await markAsRead(item);

    const href = resolveHref(item);
    isOpen.value = false;

    if (href !== '#') {
        router.visit(href);
    }
};

const markAllAsRead = async () => {
    if (isMarkingAll.value || unreadCount.value === 0) {
        return;
    }

    isMarkingAll.value = true;

    try {
        await window.axios.post(route('notifications.read_all'));
        const now = new Date().toISOString();
        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: item.read_at ?? now,
        }));
    } catch {
        // no-op
    } finally {
        isMarkingAll.value = false;
    }
};

const formatTime = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    }).format(date);
};

onMounted(() => {
    startPolling();
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <div class="notification-popover relative">
        <button
            type="button"
            class="notification-trigger relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-700 bg-gray-800 text-gray-200 transition hover:bg-gray-700"
            :class="{ 'ring-2 ring-amber-400/70 animate-pulse': unreadCount > 0 }"
            @click="isOpen = !isOpen"
            title="الإشعارات"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -left-1 -top-1 min-w-5 rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="isOpen"
            class="notification-panel absolute left-0 z-50 mt-2 w-[22rem] max-w-[90vw] rounded-xl border border-gray-700 bg-gray-900 p-3 shadow-2xl"
        >
            <div class="notification-panel-header mb-3 flex items-center justify-between">
                <p class="text-sm font-bold text-white">الإشعارات</p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-700 px-2 py-1 text-xs text-gray-200 transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="unreadCount === 0 || isMarkingAll"
                    @click="markAllAsRead"
                >
                    <CheckCheck class="h-3.5 w-3.5" />
                    <span>تعليم الكل كمقروء</span>
                </button>
            </div>

            <div v-if="notifications.length === 0" class="rounded-lg border border-gray-800 bg-gray-950 p-4 text-center text-sm text-gray-400">
                لا توجد إشعارات حالياً.
            </div>

            <div v-else class="notification-list max-h-96 space-y-2 overflow-y-auto">
                <button
                    v-for="item in notifications"
                    :key="item.id"
                    type="button"
                    class="w-full rounded-lg border px-3 py-2 text-right transition"
                    :class="item.read_at ? 'border-gray-800 bg-gray-950/60 hover:bg-gray-800/70' : 'border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/15'"
                    @click="openNotification(item)"
                >
                    <div class="notification-item-heading flex items-center justify-between gap-2">
                        <p class="notification-item-title line-clamp-1 text-sm font-semibold text-white">{{ item.title }}</p>
                        <span class="shrink-0 text-[11px] text-gray-400">{{ formatTime(item.created_at) }}</span>
                    </div>
                    <p v-if="item.body" class="mt-1 line-clamp-2 text-xs text-gray-300">{{ item.body }}</p>
                </button>
            </div>
        </div>

        <button
            v-if="isOpen"
            type="button"
            class="fixed inset-0 z-40 cursor-default border-0 bg-transparent p-0"
            aria-label="close"
            @click="isOpen = false"
        />
    </div>
</template>

<style scoped>
.notification-panel {
    transform-origin: top left;
}

.notification-list {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.55) rgba(15, 23, 42, 0.82);
}

.notification-list::-webkit-scrollbar {
    width: 0.35rem;
}

.notification-list::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.82);
    border-radius: 999px;
}

.notification-list::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.55);
    border-radius: 999px;
}

@media (max-width: 767px) {
    .notification-popover {
        position: static;
    }

    .notification-trigger {
        width: 2.35rem;
        height: 2.35rem;
        flex: 0 0 2.35rem;
    }

    .notification-panel {
        position: fixed;
        inset-inline: max(0.75rem, env(safe-area-inset-left)) max(0.75rem, env(safe-area-inset-right));
        top: calc(4.75rem + env(safe-area-inset-top));
        width: auto;
        max-width: none;
        max-height: min(72dvh, 34rem);
        margin-top: 0;
        padding: 0.85rem;
        overflow: hidden;
        border-radius: 1.1rem;
        box-shadow:
            0 24px 60px rgba(2, 6, 23, 0.58),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
    }

    .notification-panel-header {
        align-items: flex-start;
        gap: 0.65rem;
    }

    .notification-panel-header > button {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .notification-list {
        max-height: calc(min(72dvh, 34rem) - 6.5rem);
        padding-inline-end: 0.15rem;
        -webkit-overflow-scrolling: touch;
    }

    .notification-item-heading {
        align-items: flex-start;
    }

    .notification-item-title {
        min-width: 0;
        line-height: 1.45;
    }
}

@media (max-width: 380px) {
    .notification-panel {
        inset-inline: 0.5rem;
        top: calc(4.5rem + env(safe-area-inset-top));
        padding: 0.75rem;
    }

    .notification-panel-header {
        flex-direction: column;
        align-items: stretch;
    }

    .notification-panel-header > button {
        justify-content: center;
        width: 100%;
    }
}
</style>
