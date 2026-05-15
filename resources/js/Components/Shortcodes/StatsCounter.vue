<script setup>
import { onMounted, ref, computed } from 'vue';
const props = defineProps({ data: Object });
const sectionRef = ref(null);
const hasAnimated = ref(false);
const stats = ref(props.data.stats || []);
const design = computed(() => props.data?.design || {});

const resolveFileUrl = (path) => {
    if (!path) return null;
    return path.startsWith('http') ? path : `/media-files/${path}`;
};

const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage));

const sectionStyle = computed(() => {
    const style = {
        marginTop: `${design.value.marginTop ?? 0}px`,
        marginBottom: `${design.value.marginBottom ?? 0}px`,
        paddingTop: `${design.value.paddingTop ?? 80}px`,
        paddingBottom: `${design.value.paddingBottom ?? 80}px`,
        backgroundColor: design.value.backgroundType === 'color' ? (design.value.backgroundColor || undefined) : undefined,
        backgroundImage: design.value.backgroundType === 'gradient' ? (design.value.backgroundGradient || undefined) : undefined,
    };

    if (design.value.backgroundType === 'image' && backgroundImageUrl.value) {
        const opacity = Number(design.value.backgroundOpacity ?? 100) / 100;
        const overlay = Math.max(0, 1 - opacity);
        style.backgroundImage = `linear-gradient(rgba(15,23,42,${overlay}), rgba(15,23,42,${overlay})), url(${backgroundImageUrl.value})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
    }

    return style;
});

const animateNumbers = () => {
    stats.value.forEach((stat) => {
        let start = 0;
        const end = parseInt(stat.number);
        const duration = 2000;
        const step = Math.ceil(end / (duration / 16));
        const timer = setInterval(() => {
            start += step;
            if (start >= end) {
                stat.current = end;
                clearInterval(timer);
            } else stat.current = start;
        }, 16);
    });
};

onMounted(() => {
    stats.value = stats.value.map((s) => ({ ...s, current: 0 }));
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !hasAnimated.value) {
            animateNumbers();
            hasAnimated.value = true;
        }
    });
    if (sectionRef.value) observer.observe(sectionRef.value);
});
</script>
<template>
    <section ref="sectionRef" class="relative overflow-hidden border-y border-white/10" :class="data.cardBgColor || 'bg-gray-900'" :style="sectionStyle">
        <div class="relative z-10 mx-auto max-w-7xl px-6">
            <div v-if="data.title" class="ui-public-header text-center">
                <span class="ui-public-badge mx-auto">مؤشرات سريعة</span>
                <h2 class="ui-public-title" :style="{ color: data.titleColor || undefined, fontSize: `${design.titleSize ?? 40}px` }">{{ data.title }}</h2>
            </div>

            <div class="ui-public-grid grid-cols-1 text-center sm:grid-cols-2 md:grid-cols-4">
                <article v-for="(stat, index) in stats" :key="index" class="ui-public-card p-6 md:p-8">
                    <div :style="{ color: data.numberColor || '#3b82f6', fontSize: `${(design.titleSize ?? 40) + 10}px` }" class="mb-3 font-black transition-all">
                        {{ stat.current }}{{ stat.suffix }}
                    </div>
                    <p class="leading-8" :style="{ color: data.labelColor || undefined, fontSize: `${design.bodySize ?? 18}px` }">{{ stat.label }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
