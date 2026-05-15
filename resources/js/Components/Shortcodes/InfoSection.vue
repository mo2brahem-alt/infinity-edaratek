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

const imageUrl = computed(() => resolveFileUrl(props.data?.image));
const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage));
const textAlignClass = computed(() => design.value.textAlign || 'text-right');
const isImageLeft = computed(() => props.data?.layout === 'image_left');

const hasSectionVisual = computed(() => Boolean(imageUrl.value));

const sectionStyle = computed(() => {
    const paddingTop = Math.max(48, normalizeNumber(design.value.paddingTop, 72));
    const paddingBottom = Math.max(48, normalizeNumber(design.value.paddingBottom, 72));
    const marginTop = Math.max(0, normalizeNumber(design.value.marginTop, 0));
    const marginBottom = Math.max(0, normalizeNumber(design.value.marginBottom, 0));

    const style = {
        marginTop: `clamp(0px, 2vw, ${marginTop}px)`,
        marginBottom: `clamp(0px, 2vw, ${marginBottom}px)`,
        paddingTop: `clamp(48px, 8vw, ${paddingTop}px)`,
        paddingBottom: `clamp(48px, 8vw, ${paddingBottom}px)`,
    };

    const bgType = design.value.backgroundType || 'none';
    if (bgType === 'color') {
        style.backgroundColor = design.value.backgroundColor || '#111827';
    } else if (bgType === 'gradient') {
        style.backgroundImage = design.value.backgroundGradient || 'linear-gradient(135deg, #111827 0%, #1f2937 100%)';
    } else if (bgType === 'image' && backgroundImageUrl.value) {
        const opacity = normalizeNumber(design.value.backgroundOpacity, 100) / 100;
        const overlay = Math.max(0, 1 - opacity);
        style.backgroundImage = `linear-gradient(rgba(0,0,0,${overlay}), rgba(0,0,0,${overlay})), url(${backgroundImageUrl.value})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
    } else if (props.data?.bgColor && props.data.bgColor !== 'transparent') {
        style.backgroundColor = props.data.bgColor;
    }

    return style;
});

const titleStyle = computed(() => ({
    color: props.data?.titleColor || '#ffffff',
    fontSize: `clamp(1.8rem, 4vw, ${Math.max(32, normalizeNumber(design.value.titleSize, 42))}px)`,
    fontWeight: normalizeNumber(design.value.titleWeight, 800),
    lineHeight: normalizeNumber(design.value.titleLineHeight, 1.2),
}));

const textStyle = computed(() => ({
    color: props.data?.textColor || '#9ca3af',
    fontSize: `clamp(1rem, 2vw, ${Math.max(17, normalizeNumber(design.value.bodySize, 18))}px)`,
    fontWeight: normalizeNumber(design.value.bodyWeight, 400),
    lineHeight: normalizeNumber(design.value.bodyLineHeight, 1.9),
}));

const imageStyle = computed(() => ({
    width: `${normalizeNumber(design.value.imageWidth, 100)}%`,
    height: `clamp(260px, 42vw, ${Math.max(320, normalizeNumber(design.value.imageHeight, 520))}px)`,
    objectFit: design.value.imageFit || 'cover',
    objectPosition: design.value.imagePosition || 'center center',
    borderRadius: `${normalizeNumber(design.value.imageRadius, 28)}px`,
}));
</script>

<template>
    <section class="w-full overflow-hidden transition-colors duration-300" :style="sectionStyle">
        <div class="ui-site-container min-w-0">
            <div
                class="info-section-layout flex min-w-0 items-center gap-4 sm:gap-5 lg:grid lg:gap-6"
                :class="hasSectionVisual ? 'lg:grid-cols-2 lg:gap-10' : ''"
            >
                <div class="info-section-copy-column min-w-0 flex-1" :class="isImageLeft ? 'lg:order-2' : 'lg:order-1'">
                    <div class="info-section-copy mx-auto flex h-full max-w-3xl flex-col justify-center gap-6" :class="textAlignClass">
                        <h2 v-if="props.data.title" class="leading-tight" :style="titleStyle">
                            {{ props.data.title }}
                        </h2>

                        <div class="info-section-body whitespace-pre-line opacity-90" :style="textStyle">
                            {{ props.data.text }}
                        </div>

                        <div>
                            <a
                                v-if="props.data.btnUrl"
                                :href="props.data.btnUrl"
                                class="info-section-cta inline-flex w-full items-center justify-center rounded-2xl border border-white/10 px-6 py-3.5 text-center font-bold shadow-lg transition hover:shadow-xl sm:w-auto"
                                :style="{ backgroundColor: props.data.btnBgColor || '#1f2937', color: props.data.btnTextColor || '#ffffff' }"
                            >
                                {{ props.data.btnText || 'المزيد' }}
                            </a>
                        </div>
                    </div>
                </div>

                <div
                    v-if="hasSectionVisual"
                    class="info-section-visual min-w-0 w-[42%] shrink-0 sm:w-[40%] lg:w-auto"
                    :class="isImageLeft ? 'lg:order-1' : 'lg:order-2'"
                >
                    <div class="info-section-visual-frame overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950/20 p-2 shadow-[0_24px_60px_-36px_rgba(15,23,42,0.65)]">
                        <img
                            :src="imageUrl"
                            class="info-section-image w-full transition duration-1000 hover:scale-[1.02]"
                            :style="imageStyle"
                            :alt="props.data.title || 'صورة القسم'"
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@media (max-width: 1023px) {
    .info-section-copy {
        gap: 0.85rem;
    }

    .info-section-copy h2 {
        font-size: clamp(1.3rem, 5vw, 1.7rem) !important;
        line-height: 1.35 !important;
    }

    .info-section-body {
        font-size: 0.95rem !important;
        line-height: 1.75 !important;
    }

    .info-section-visual-frame {
        padding: 0.35rem;
        border-radius: 1.35rem;
    }

    .info-section-image {
        height: clamp(170px, 46vw, 230px) !important;
        border-radius: 1rem !important;
    }
}

@media (max-width: 639px) {
    .info-section-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(6.5rem, 36%);
        direction: rtl;
        align-items: stretch;
        gap: 0.8rem;
    }

    .info-section-copy-column,
    .info-section-visual {
        display: flex;
        align-self: stretch;
        min-width: 0;
    }

    .info-section-copy {
        max-width: none;
        width: 100%;
        height: 100%;
        justify-content: center;
        gap: 0.65rem;
        text-align: right !important;
        margin-inline: 0;
        padding-inline: 0.15rem 0;
    }

    .info-section-copy h2 {
        font-size: clamp(1.05rem, 4.3vw, 1.35rem) !important;
        line-height: 1.3 !important;
        overflow-wrap: anywhere;
    }

    .info-section-body {
        font-size: 0.82rem !important;
        line-height: 1.55 !important;
        overflow-wrap: anywhere;
    }

    .info-section-visual {
        width: auto;
    }

    .info-section-visual-frame {
        width: 100%;
        height: 100%;
        padding: 0.3rem;
    }

    .info-section-image {
        height: 100% !important;
        min-height: 160px;
    }

    .info-section-cta {
        padding: 0.7rem 0.85rem;
        font-size: 0.82rem;
    }
}
</style>
