<script setup>
import { computed } from 'vue';
import { Layers3, Settings2, Sparkles, X } from 'lucide-vue-next';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    scope: { type: Object, default: null },
    countryName: { type: String, default: '' },
    educationTypeName: { type: String, default: '' },
    inventoryCards: { type: Array, default: () => [] },
    referenceSummary: { type: Object, default: null },
    referenceSupportedLabels: { type: Array, default: () => [] },
    referenceUnavailableLabels: { type: Array, default: () => [] },
    dataSections: { type: Array, default: () => [] },
});

defineEmits(['close', 'open-editor']);

const summaryCards = computed(() => [
    {
        key: 'template_name',
        label: 'اسم القالب',
        value: props.scope?.template_name || '-',
        helper: 'الاسم الذي يظهر في قائمة القوالب المحفوظة.',
    },
    {
        key: 'country',
        label: 'الدولة',
        value: props.countryName || '-',
        helper: 'مصدر المرجعيات والبيانات المرتبطة بالدولة.',
    },
    {
        key: 'education_type',
        label: 'نوع التعليم',
        value: props.educationTypeName || '-',
        helper: 'يحدد نوع المدارس التي سيطابقها هذا القالب عند التهيئة.',
    },
]);
</script>

<template>
    <teleport to="body">
        <div
            v-if="open"
            class="ui-theme-modal-backdrop fixed inset-0 z-[130] flex items-center justify-center p-3 sm:p-4"
            dir="rtl"
            @click.self="$emit('close')"
        >
            <div
                class="ui-theme-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl sm:max-h-[94vh]"
                role="dialog"
                aria-modal="true"
                aria-label="استعراض القالب"
            >
                <div class="ui-theme-modal-header flex items-start justify-between gap-3 border-b border-white/10 px-4 py-4 sm:px-6">
                    <div class="text-right">
                        <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                            <Settings2 class="h-4 w-4" />
                            <span>استعراض القالب</span>
                        </p>
                        <h3 class="mt-2 text-lg font-black text-white sm:text-xl">
                            {{ scope?.template_name || 'استعراض القالب' }}
                        </h3>
                        <p class="mt-2 text-xs leading-6 text-slate-400 sm:text-sm">
                            {{ countryName || 'الدولة غير محددة' }}
                            <span v-if="educationTypeName"> / {{ educationTypeName }}</span>
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                        aria-label="إغلاق نافذة استعراض القالب"
                        @click="$emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="ui-theme-modal-body min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <AppStatePanel
                        v-if="loading"
                        variant="loading"
                        title="جارٍ تجهيز استعراض القالب"
                        description="يتم الآن تحميل تفاصيل القالب والبيانات الافتراضية المرتبطة به."
                    />

                    <AppStatePanel
                        v-else-if="!scope"
                        variant="error"
                        title="تعذر استعراض القالب"
                        description="لم يتم العثور على تفاصيل القالب المطلوب الآن. حاول فتحه مرة أخرى من القائمة."
                    />

                    <template v-else>
                        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <article
                                v-for="item in summaryCards"
                                :key="item.key"
                                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
                            >
                                <p class="text-xs font-semibold text-slate-400">{{ item.label }}</p>
                                <p class="mt-2 text-sm font-black text-white">{{ item.value }}</p>
                                <p class="mt-2 text-xs leading-6 text-slate-500">{{ item.helper }}</p>
                            </article>
                        </section>

                        <section class="mt-5 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                                        <Sparkles class="h-4 w-4" />
                                        <span>بيانات الدولة المرجعية</span>
                                    </p>
                                    <p class="mt-2 text-sm font-bold text-white">ملخص المرجعيات المحفوظة داخل القالب</p>
                                    <p class="mt-2 text-xs leading-7 text-slate-400">
                                        {{ referenceSummary?.message || 'هذا القالب لا يحتوي على مرجعيات دولة محفوظة بعد.' }}
                                    </p>
                                </div>

                                <div v-if="referenceSummary" class="text-[11px] leading-6 text-slate-500">
                                    <p>المصدر: {{ referenceSummary.source?.label || 'واجهة الدولة الخارجية' }}</p>
                                    <p>آخر جلب: {{ referenceSummary.fetched_at || '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                                    <p class="text-xs font-bold text-emerald-200">تم جلبه وسيبقى محفوظًا داخل القالب</p>
                                    <p class="mt-2 text-sm leading-7 text-white">
                                        {{ referenceSupportedLabels.join('، ') || 'لا توجد بيانات مرجعية مدعومة محفوظة حاليًا.' }}
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                                    <p class="text-xs font-bold text-amber-200">غير متاح من المصدر الخارجي الحالي</p>
                                    <p class="mt-2 text-sm leading-7 text-white">
                                        {{ referenceUnavailableLabels.join('، ') || 'لا توجد عناصر ناقصة في هذا الجلب.' }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="mt-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                                        <Layers3 class="h-4 w-4" />
                                        <span>البيانات الافتراضية المحفوظة</span>
                                    </p>
                                    <p class="mt-2 text-sm font-bold text-white">ملخص العناصر الجاهزة داخل هذا القالب</p>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                <article
                                    v-for="card in inventoryCards"
                                    :key="card.key"
                                    class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
                                >
                                    <p class="text-xs font-semibold text-slate-400">{{ card.label }}</p>
                                    <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
                                    <p class="mt-2 text-xs leading-6 text-slate-500">{{ card.helper }}</p>
                                </article>
                            </div>
                        </section>

                        <section class="mt-5 grid gap-4 xl:grid-cols-2">
                            <article
                                v-for="section in dataSections"
                                :key="section.key"
                                class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-white">{{ section.title }}</p>
                                        <p class="mt-1 text-xs leading-6 text-slate-500">{{ section.description }}</p>
                                    </div>
                                    <span class="rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-300">
                                        {{ section.count }}
                                    </span>
                                </div>

                                <div v-if="section.items.length" class="mt-4 space-y-2">
                                    <article
                                        v-for="item in section.items"
                                        :key="`${section.key}-${item.title}`"
                                        class="rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-3"
                                    >
                                        <p class="text-sm font-semibold text-white">{{ item.title }}</p>
                                        <p v-if="item.meta" class="mt-1 text-xs leading-6 text-slate-500">{{ item.meta }}</p>
                                    </article>
                                </div>
                                <AppStatePanel
                                    v-else
                                    compact
                                    :title="section.emptyTitle"
                                    :description="section.emptyDescription"
                                />
                            </article>
                        </section>
                    </template>
                </div>

                <div class="ui-theme-modal-footer flex flex-col-reverse gap-3 border-t border-white/10 bg-slate-950/95 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs leading-6 text-slate-500">
                        هذه النافذة تعرض معلومات القالب دون إغراق الصفحة الرئيسية بالتفاصيل. افتح التحرير فقط عند الحاجة لتعديل العناصر أو إضافة بيانات جديدة.
                    </p>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
                            @click="$emit('close')"
                        >
                            إغلاق
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-500"
                            @click="$emit('open-editor', scope)"
                        >
                            فتح القالب للتحرير
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>
