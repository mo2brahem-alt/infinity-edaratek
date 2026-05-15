<script setup>
import { computed } from 'vue';
import {
    BookOpenText,
    CalendarDays,
    Clock3,
    FileSpreadsheet,
    LayoutTemplate,
    School,
    Settings2,
    UserRound,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    steps: {
        type: Array,
        default: () => [],
    },
    currentStepKey: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'refresh', 'change-step', 'open-step', 'prev-step', 'next-step']);

const currentStepIndex = computed(() => {
    if (!props.currentStepKey) return 0;
    const index = props.steps.findIndex((step) => step.key === props.currentStepKey);
    return index >= 0 ? index : 0;
});

const currentStep = computed(() => props.steps[currentStepIndex.value] || null);
const canGoPrevious = computed(() => currentStepIndex.value > 0);
const canGoNext = computed(() => {
    if (!currentStep.value) return false;
    if (currentStepIndex.value >= props.steps.length - 1) return false;
    return !currentStep.value.blocked;
});

const statusLabel = (status) => {
    if (status === 'completed') return 'مكتملة';
    if (status === 'needs_attention') return 'بحاجة مراجعة';
    return 'غير مكتملة';
};

const statusClass = (status) => {
    if (status === 'completed') return 'bg-emerald-500/20 text-emerald-200 border-emerald-700/60';
    if (status === 'needs_attention') return 'bg-amber-500/20 text-amber-200 border-amber-700/60';
    return 'bg-gray-700/70 text-gray-300 border-gray-600';
};

const boolClass = (met) => (met ? 'text-emerald-300' : 'text-red-300');

const stepIconByKey = {
    school_users: UserRound,
    stages: School,
    years: CalendarDays,
    terms: CalendarDays,
    calendar: CalendarDays,
    holidays: CalendarDays,
    leave_types: Clock3,
    classrooms: School,
    subjects: BookOpenText,
    timetable_versions: LayoutTemplate,
    schedules: LayoutTemplate,
};

const iconForStep = (stepKey) => stepIconByKey[stepKey] || FileSpreadsheet;
</script>

<template>
    <teleport to="body">
        <div v-if="open" class="ui-theme-modal-backdrop fixed inset-0 z-[120] flex items-center justify-center p-4" dir="rtl">
            <div class="ui-theme-modal-panel max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-xl border border-gray-700 bg-gray-900 text-gray-100 shadow-2xl">
                <div class="ui-theme-modal-header flex items-center justify-between border-b border-gray-800 px-4 py-3">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                            <Settings2 class="h-5 w-5 text-blue-300" />
                            <span>الإعدادات السريعة</span>
                        </h2>
                        <p class="text-xs text-gray-400">مسار إعداد تدريجي مع حفظ الحالة وإمكانية الاستكمال لاحقًا</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded bg-gray-800 px-3 py-1 text-xs hover:bg-gray-700"
                        @click="$emit('close')"
                    >
                        <X class="h-3.5 w-3.5" />
                        <span>إغلاق</span>
                    </button>
                </div>

                <div class="grid max-h-[calc(92vh-68px)] grid-cols-1 md:grid-cols-[280px_1fr]">
                    <aside class="overflow-y-auto border-b border-gray-800 bg-gray-950/60 p-3 md:border-b-0 md:border-l">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <LayoutTemplate class="h-3.5 w-3.5 text-blue-300" />
                                <span>خطوات الإعداد</span>
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-gray-800 px-2 py-1 text-[11px] hover:bg-gray-700"
                                :disabled="loading"
                                @click="$emit('refresh')"
                            >
                                <Settings2 class="h-3 w-3" />
                                <span>تحديث الحالة</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <button
                                v-for="step in steps"
                                :key="step.key"
                                type="button"
                                class="w-full rounded border p-2 text-right transition"
                                :class="[
                                    step.key === currentStepKey
                                        ? 'border-blue-500 bg-blue-500/10'
                                        : 'border-gray-700 bg-gray-900/70 hover:border-gray-600',
                                ]"
                                @click="$emit('change-step', step.key)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="inline-flex items-center gap-1.5 text-sm font-semibold">
                                        <component :is="iconForStep(step.key)" class="h-3.5 w-3.5 text-blue-200/90" />
                                        <span>{{ step.order }}) {{ step.label }}</span>
                                    </p>
                                    <span class="rounded border px-2 py-0.5 text-[10px]" :class="statusClass(step.status)">
                                        {{ statusLabel(step.status) }}
                                    </span>
                                </div>
                                <p v-if="step.optional" class="mt-1 text-[11px] text-gray-500">خطوة اختيارية</p>
                                <p v-if="step.blocked" class="mt-1 text-[11px] text-red-300">توجد متطلبات غير مكتملة</p>
                            </button>
                        </div>
                    </aside>

                    <section class="ui-theme-modal-body overflow-y-auto p-4">
                        <div v-if="loading" class="rounded border border-gray-700 bg-gray-800 p-4 text-sm text-gray-300">
                            جاري تحميل حالة الإعداد...
                        </div>

                        <div v-else-if="error" class="rounded border border-red-700/50 bg-red-900/20 p-4 text-sm text-red-200">
                            {{ error }}
                        </div>

                        <div v-else-if="currentStep" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-700 bg-gray-800 p-3">
                                <div>
                                    <h3 class="inline-flex items-center gap-1.5 text-base font-bold">
                                        <component :is="iconForStep(currentStep.key)" class="h-4 w-4 text-blue-300" />
                                        <span>{{ currentStep.order }}) {{ currentStep.label }}</span>
                                    </h3>
                                    <p class="text-xs text-gray-400">
                                        الحالة:
                                        <span class="font-semibold" :class="currentStep.status === 'completed' ? 'text-emerald-300' : currentStep.status === 'needs_attention' ? 'text-amber-300' : 'text-gray-300'">
                                            {{ statusLabel(currentStep.status) }}
                                        </span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="currentStep.optional" class="rounded bg-gray-700 px-2 py-1 text-[11px] text-gray-300">اختيارية</span>
                                    <span
                                        class="rounded px-2 py-1 text-[11px]"
                                        :class="currentStep.editable ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'"
                                    >
                                        {{ currentStep.editable ? 'قابلة للتعديل' : 'عرض فقط' }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded border border-gray-700 bg-gray-800 p-3">
                                <h4 class="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold">
                                    <BookOpenText class="h-4 w-4 text-cyan-200" />
                                    <span>المتطلبات</span>
                                </h4>
                                <ul v-if="Array.isArray(currentStep.prerequisites) && currentStep.prerequisites.length > 0" class="space-y-2 text-xs text-right">
                                    <li
                                        v-for="dependency in currentStep.prerequisites"
                                        :key="`${currentStep.key}-${dependency.key}`"
                                        class="flex items-start justify-between gap-2 rounded border border-gray-700/70 bg-gray-900/60 px-3 py-2"
                                        :class="boolClass(dependency.met)"
                                    >
                                        <span
                                            class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[11px] font-black"
                                            :class="dependency.met ? 'border-emerald-400/50 bg-emerald-500/15 text-emerald-200' : 'border-rose-400/45 bg-rose-500/10 text-rose-200'"
                                            :aria-label="dependency.met ? 'مكتمل' : 'غير مكتمل'"
                                        >
                                            {{ dependency.met ? '✓' : '✕' }}
                                        </span>
                                        <div class="flex-1 leading-6">
                                            <span>{{ dependency.label }}</span>
                                            <span v-if="dependency.required" class="mr-1 text-gray-400">(إلزامي)</span>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="text-xs text-gray-500">لا توجد متطلبات مسبقة لهذه الخطوة.</p>
                            </div>

                            <div class="rounded border border-gray-700 bg-gray-800 p-3">
                                <h4 class="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold">
                                    <Clock3 class="h-4 w-4 text-amber-200" />
                                    <span>مؤشرات سريعة</span>
                                </h4>
                                <div v-if="currentStep.counts && Object.keys(currentStep.counts).length > 0" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <div
                                        v-for="(value, key) in currentStep.counts"
                                        :key="`${currentStep.key}-${key}`"
                                        class="rounded border border-gray-700 bg-gray-900 p-2 text-xs"
                                    >
                                        <p class="text-gray-400">{{ key }}</p>
                                        <p class="text-sm font-bold text-gray-100">{{ value }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-500">لا توجد مؤشرات متاحة.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-blue-700 px-3 py-2 text-sm font-semibold hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!currentStep.editable || currentStep.blocked"
                                    @click="$emit('open-step', currentStep.key)"
                                >
                                    <Settings2 class="h-3.5 w-3.5" />
                                    <span>فتح قسم الخطوة</span>
                                </button>
                                <p v-if="!currentStep.editable" class="text-xs text-amber-300">لا تملك صلاحية تعديل هذه الخطوة.</p>
                                <p v-else-if="currentStep.blocked" class="text-xs text-red-300">أكمل المتطلبات الإلزامية قبل الانتقال.</p>
                            </div>
                        </div>

                        <div v-else class="rounded border border-gray-700 bg-gray-800 p-4 text-sm text-gray-300">
                            لا توجد خطوات متاحة حاليًا.
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-800 pt-3">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-gray-800 px-3 py-2 text-sm hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canGoPrevious"
                                @click="$emit('prev-step')"
                            >
                                <LayoutTemplate class="h-3.5 w-3.5" />
                                <span>السابق</span>
                            </button>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-gray-800 px-3 py-2 text-sm hover:bg-gray-700"
                                    @click="$emit('close')"
                                >
                                    <FileSpreadsheet class="h-3.5 w-3.5" />
                                    <span>حفظ والاستكمال لاحقًا</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-emerald-700 px-3 py-2 text-sm font-semibold hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canGoNext"
                                    @click="$emit('next-step')"
                                >
                                    <LayoutTemplate class="h-3.5 w-3.5" />
                                    <span>التالي</span>
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </teleport>
</template>
