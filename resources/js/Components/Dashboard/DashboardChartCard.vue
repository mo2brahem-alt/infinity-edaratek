<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useThemeMode } from '@/composables/useThemeMode';

const { isDarkMode } = useThemeMode();

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    chart: {
        type: Object,
        default: () => ({}),
    },
    height: {
        type: Number,
        default: 300,
    },
    emptyText: {
        type: String,
        default: 'لا توجد بيانات كافية لعرض هذا الرسم.',
    },
});

const chartType = computed(() => props.chart?.type || 'bar');
const isDonut = computed(() => chartType.value === 'donut' || chartType.value === 'pie');
const chartSeries = computed(() => {
    const series = props.chart?.series;

    if (isDonut.value) {
        return Array.isArray(series) ? series.map((value) => Number(value || 0)) : [];
    }

    return Array.isArray(series) ? series : [];
});
const labels = computed(() => Array.isArray(props.chart?.labels) ? props.chart.labels : []);
const categories = computed(() => Array.isArray(props.chart?.categories) ? props.chart.categories : []);
const hasData = computed(() => {
    if (isDonut.value) {
        return chartSeries.value.some((value) => Number(value || 0) > 0);
    }

    return chartSeries.value.some((serie) => Array.isArray(serie?.data) && serie.data.some((value) => Number(value || 0) > 0));
});

const palette = computed(() => isDarkMode.value
    ? ['#38bdf8', '#34d399', '#fbbf24', '#fb7185', '#a78bfa', '#2dd4bf', '#f472b6']
    : ['#0284c7', '#059669', '#d97706', '#e11d48', '#7c3aed', '#0f766e', '#be185d']
);

const chartOptions = computed(() => {
    const dark = isDarkMode.value;
    const textColor = dark ? '#cbd5e1' : '#334155';
    const mutedColor = dark ? '#94a3b8' : '#64748b';
    const borderColor = dark ? 'rgba(71, 85, 105, 0.55)' : 'rgba(203, 213, 225, 0.85)';

    return {
        chart: {
            background: 'transparent',
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, easing: 'easeinout', speed: 450 },
        },
        colors: palette.value,
        dataLabels: {
            enabled: isDonut.value,
            style: {
                colors: isDonut.value ? ['#fff'] : [textColor],
                fontFamily: 'inherit',
                fontWeight: 800,
            },
        },
        grid: {
            borderColor,
            strokeDashArray: 5,
        },
        labels: labels.value,
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontFamily: 'inherit',
            labels: { colors: textColor },
            markers: { radius: 6 },
        },
        noData: {
            text: props.emptyText,
            align: 'center',
            verticalAlign: 'middle',
            style: {
                color: mutedColor,
                fontFamily: 'inherit',
                fontSize: '13px',
            },
        },
        plotOptions: {
            bar: {
                horizontal: Boolean(props.chart?.horizontal),
                borderRadius: 8,
                columnWidth: '52%',
                barHeight: '58%',
                distributed: false,
            },
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        name: { color: textColor, fontFamily: 'inherit' },
                        value: { color: dark ? '#f8fafc' : '#0f172a', fontFamily: 'inherit', fontWeight: 900 },
                        total: {
                            show: true,
                            label: 'الإجمالي',
                            color: mutedColor,
                            formatter: (w) => w.globals.seriesTotals.reduce((sum, value) => sum + value, 0),
                        },
                    },
                },
            },
        },
        stroke: {
            curve: 'smooth',
            width: chartType.value === 'area' || chartType.value === 'line' ? 3 : 0,
        },
        theme: {
            mode: dark ? 'dark' : 'light',
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            x: { show: true },
            y: {
                formatter: (value) => Number.isInteger(Number(value)) ? Number(value).toLocaleString('ar') : Number(value).toFixed(1),
            },
        },
        xaxis: {
            categories: categories.value,
            axisBorder: { color: borderColor },
            axisTicks: { color: borderColor },
            labels: {
                rotate: categories.value.length > 6 ? -35 : 0,
                trim: true,
                style: {
                    colors: categories.value.map(() => mutedColor),
                    fontFamily: 'inherit',
                    fontSize: '11px',
                },
            },
            tooltip: { enabled: false },
        },
        yaxis: {
            labels: {
                style: {
                    colors: [mutedColor],
                    fontFamily: 'inherit',
                    fontSize: '11px',
                },
                formatter: (value) => Math.round(Number(value || 0)).toLocaleString('ar'),
            },
        },
        fill: {
            opacity: chartType.value === 'area' ? 0.24 : 1,
            type: chartType.value === 'area' ? 'gradient' : 'solid',
            gradient: {
                shadeIntensity: 0.35,
                opacityFrom: dark ? 0.35 : 0.4,
                opacityTo: 0.05,
                stops: [0, 85, 100],
            },
        },
        responsive: [
            {
                breakpoint: 640,
                options: {
                    chart: { height: Math.max(240, props.height - 40) },
                    legend: { fontSize: '11px' },
                    plotOptions: {
                        bar: {
                            columnWidth: '64%',
                            barHeight: '70%',
                        },
                    },
                },
            },
        ],
    };
});
</script>

<template>
    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <div class="mb-4 flex flex-col gap-1 text-right">
            <h3 class="text-base font-black text-slate-950 dark:text-white">{{ title }}</h3>
            <p v-if="description" class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>

        <VueApexCharts
            v-if="hasData"
            :key="`${chartType}-${isDarkMode ? 'dark' : 'light'}`"
            :type="chartType"
            :height="height"
            :options="chartOptions"
            :series="chartSeries"
            dir="ltr"
        />

        <div v-else class="grid min-h-56 place-items-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-5 text-center text-sm font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">
            {{ emptyText }}
        </div>
    </article>
</template>
