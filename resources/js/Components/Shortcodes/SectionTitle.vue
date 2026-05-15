<script setup>
import { computed } from 'vue';

const props = defineProps({ data: Object });

const design = computed(() => props.data?.design || {});

const normalizeNumber = (value, fallback) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const resolveFileUrl = (path) => {
    if (!path) return null;
    return path.startsWith('http') ? path : `/media-files/${path}`;
};

const alignClass = computed(() => design.value.textAlign || props.data?.alignment || 'text-center');
const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage || props.data?.bgImage));

const sectionStyle = computed(() => {
    const marginTop = Math.max(0, normalizeNumber(design.value.marginTop ?? props.data?.marginTop, 32));
    const marginBottom = Math.max(0, normalizeNumber(design.value.marginBottom ?? props.data?.marginBottom, 32));
    const paddingTop = Math.max(12, normalizeNumber(design.value.paddingTop, 20));
    const paddingBottom = Math.max(12, normalizeNumber(design.value.paddingBottom, 20));

    const style = {
        marginTop: `clamp(0px, 1.5vw, ${marginTop}px)`,
        marginBottom: `clamp(0px, 1.5vw, ${marginBottom}px)`,
        paddingTop: `clamp(12px, 2vw, ${paddingTop}px)`,
        paddingBottom: `clamp(12px, 2vw, ${paddingBottom}px)`,
        textAlign: alignClass.value.replace('text-', ''),
    };

    const bgType = design.value.backgroundType || props.data?.bgType || 'none';
    if (bgType === 'color') {
        style.backgroundColor = design.value.backgroundColor || '#111827';
    }

    if (bgType === 'gradient') {
        style.backgroundImage = design.value.backgroundGradient || 'linear-gradient(135deg, #111827 0%, #1f2937 100%)';
    }

    if (bgType === 'image' && backgroundImageUrl.value) {
        const opacity = normalizeNumber(design.value.backgroundOpacity ?? props.data?.bgImageOpacity, 100) / 100;
        const overlay = Math.max(0, 1 - opacity);
        style.backgroundImage = `linear-gradient(rgba(15,23,42,${overlay}), rgba(15,23,42,${overlay})), url(${backgroundImageUrl.value})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
    }

    return style;
});

const titleStyle = computed(() => ({
    color: props.data?.titleColor || '#ffffff',
    fontSize: `clamp(1.8rem, 4.2vw, ${Math.max(32, normalizeNumber(design.value.titleSize ?? props.data?.titleSize, 48))}px)`,
    fontWeight: normalizeNumber(design.value.titleWeight, 800),
    lineHeight: normalizeNumber(design.value.titleLineHeight, 1.2),
}));

const subtitleStyle = computed(() => ({
    color: props.data?.subtitleColor || '#3b82f6',
    fontSize: `clamp(0.95rem, 2vw, ${Math.max(16, normalizeNumber(design.value.subtitleSize ?? props.data?.subtitleSize, 18))}px)`,
    fontWeight: normalizeNumber(design.value.bodyWeight, 500),
    lineHeight: normalizeNumber(design.value.bodyLineHeight, 1.7),
}));
</script>

<template>
    <section class="w-full overflow-hidden" :style="sectionStyle">
        <div class="ui-site-container">
            <div class="mx-auto max-w-4xl px-1" :class="alignClass">
                <div class="ui-public-header">
                    <template v-if="data.style === 'gradient'">
                        <span
                            v-if="data.subtitle"
                            class="mb-1 block font-bold uppercase tracking-[0.18em]"
                            :style="subtitleStyle"
                        >
                            {{ data.subtitle }}
                        </span>
                        <h2
                            class="bg-gradient-to-r from-sky-500 via-blue-500 to-indigo-500 bg-clip-text font-black leading-tight text-transparent"
                            :style="titleStyle"
                        >
                            {{ data.title }}
                        </h2>
                    </template>

                    <template v-else-if="data.style === 'bold'">
                        <h2 class="font-black leading-tight" :style="titleStyle">
                            {{ data.title }}
                        </h2>
                        <p
                            v-if="data.subtitle"
                            class="max-w-3xl"
                            :class="{ 'mx-auto': alignClass === 'text-center' }"
                            :style="subtitleStyle"
                        >
                            {{ data.subtitle }}
                        </p>
                    </template>

                    <template v-else>
                        <h2 class="font-black leading-tight" :style="titleStyle">
                            {{ data.title }}
                        </h2>
                        <p
                            v-if="data.subtitle"
                            class="max-w-3xl"
                            :class="{ 'mx-auto': alignClass === 'text-center' }"
                            :style="subtitleStyle"
                        >
                            {{ data.subtitle }}
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </section>
</template>
