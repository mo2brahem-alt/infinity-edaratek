<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ data: Object });
const page = usePage();
const settings = computed(() => page.props.app_settings || {});
const BANNER_DEFAULTS = {
    titleColor: '#ffffff',
    subtitleColor: '#e5e7eb',
    btnBgColor: '#2563eb',
    btnTextColor: '#ffffff',
    glassBgColor: '#111827',
};

const design = computed(() => props.data?.design || {});

const normalizeNumber = (value, fallback) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const parseHeightToken = (value) => {
    const normalized = String(value || '').trim();

    if (normalized === 'min-h-screen') {
        return '100vh';
    }

    const match = normalized.match(/^min-h-\[(\d+)px\]$/);

    if (match) {
        return `${match[1]}px`;
    }

    return null;
};

const resolveFileUrl = (path) => {
    if (!path) return null;
    return path.startsWith('http') ? path : `/media-files/${path}`;
};

const mediaUrl = computed(() => resolveFileUrl(props.data?.media));
const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage));
const isVideoBanner = computed(() => props.data?.mediaType === 'video');
const resolvedBannerMinHeight = computed(() => {
    const configuredHeight = parseHeightToken(props.data?.height);

    if (configuredHeight) {
        return configuredHeight;
    }

    return isVideoBanner.value ? '520px' : '420px';
});
const bannerFrameStyle = computed(() => ({
    minHeight: resolvedBannerMinHeight.value,
    height: resolvedBannerMinHeight.value,
}));

const hexToRgba = (hex, alpha = 1) => {
    if (!hex) return `rgba(17, 24, 39, ${alpha})`;
    const normalized = hex.replace('#', '').trim();
    const full = normalized.length === 3
        ? normalized.split('').map((char) => char + char).join('')
        : normalized;

    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return `rgba(17, 24, 39, ${alpha})`;
    }

    const r = parseInt(full.slice(0, 2), 16);
    const g = parseInt(full.slice(2, 4), 16);
    const b = parseInt(full.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const normalizeColor = (value) => String(value || '').trim().toLowerCase();
const shouldInheritThemeColor = (value, defaultValue) => {
    const normalized = normalizeColor(value);

    return normalized === '' || normalized === normalizeColor(defaultValue);
};

const resolvedTitleColor = computed(() => (
    shouldInheritThemeColor(props.data?.titleColor, BANNER_DEFAULTS.titleColor)
        ? (settings.value.heading_color || BANNER_DEFAULTS.titleColor)
        : props.data?.titleColor
));

const resolvedSubtitleColor = computed(() => (
    shouldInheritThemeColor(props.data?.subtitleColor, BANNER_DEFAULTS.subtitleColor)
        ? (settings.value.subheading_color || settings.value.text_color || BANNER_DEFAULTS.subtitleColor)
        : props.data?.subtitleColor
));

const resolvedButtonBgColor = computed(() => (
    shouldInheritThemeColor(props.data?.btnBgColor, BANNER_DEFAULTS.btnBgColor)
        ? (settings.value.btn_bg_color || settings.value.primary_color || BANNER_DEFAULTS.btnBgColor)
        : props.data?.btnBgColor
));

const resolvedButtonTextColor = computed(() => (
    shouldInheritThemeColor(props.data?.btnTextColor, BANNER_DEFAULTS.btnTextColor)
        ? (settings.value.btn_text_color || BANNER_DEFAULTS.btnTextColor)
        : props.data?.btnTextColor
));

const resolvedGlassBgColor = computed(() => (
    shouldInheritThemeColor(props.data?.glassBgColor, BANNER_DEFAULTS.glassBgColor)
        ? (settings.value.glass_color_1 || settings.value.bg_color || BANNER_DEFAULTS.glassBgColor)
        : props.data?.glassBgColor
));

const textAlignClass = computed(() => props.data?.alignment || design.value.textAlign || 'text-center');
const hasCustomVerticalOffsets = computed(() => (
    normalizeNumber(props.data?.glassMarginTop, 0) !== 0
    || normalizeNumber(props.data?.glassMarginBottom, 0) !== 0
));
const hasCustomHorizontalOffsets = computed(() => (
    normalizeNumber(props.data?.glassMarginRight, 0) !== 0
    || normalizeNumber(props.data?.glassMarginLeft, 0) !== 0
));
const contentShellClass = computed(() => (
    hasCustomVerticalOffsets.value ? 'items-start' : 'items-end lg:items-center'
));

const contentPositionClass = computed(() => {
    if (hasCustomHorizontalOffsets.value) {
        return '';
    }

    if (textAlignClass.value === 'text-right') return 'ml-auto';
    if (textAlignClass.value === 'text-left') return 'mr-auto';
    return 'mx-auto';
});
const contentShellStyle = computed(() => ({
    paddingTop: `${Math.max(0, normalizeNumber(design.value.paddingTop, 0))}px`,
    paddingBottom: `${Math.max(0, normalizeNumber(design.value.paddingBottom, 0))}px`,
}));

const sectionStyle = computed(() => {
    const marginTop = Math.max(0, normalizeNumber(design.value.marginTop, 0));
    const marginBottom = Math.max(0, normalizeNumber(design.value.marginBottom, 0));

    const style = {
        marginTop: `clamp(0px, 2vw, ${marginTop}px)`,
        marginBottom: `clamp(0px, 2vw, ${marginBottom}px)`,
    };

    const bgType = design.value.backgroundType || 'none';
    if (bgType === 'color') {
        style.backgroundColor = design.value.backgroundColor || '#111827';
    }

    if (bgType === 'gradient') {
        style.backgroundImage = design.value.backgroundGradient || 'linear-gradient(135deg, #111827 0%, #1f2937 100%)';
    }

    if (bgType === 'image' && backgroundImageUrl.value) {
        const opacity = normalizeNumber(design.value.backgroundOpacity, 100) / 100;
        const overlay = Math.max(0, 1 - opacity);
        style.backgroundImage = `linear-gradient(rgba(0,0,0,${overlay}), rgba(0,0,0,${overlay})), url(${backgroundImageUrl.value})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
    }

    return style;
});

const mediaStyle = computed(() => ({
    objectPosition: design.value.imagePosition || 'center center',
    backgroundColor: '#020617',
}));

const normalizeObjectFit = (value, fallback = 'cover') => {
    const normalized = String(value || '').trim().toLowerCase();

    if (['cover', 'contain', 'fill', 'none', 'scale-down'].includes(normalized)) {
        return normalized;
    }

    return fallback;
};

const videoMediaStyle = computed(() => ({
    ...mediaStyle.value,
    objectFit: normalizeObjectFit(design.value.imageFit, 'cover'),
}));

const imageMediaStyle = computed(() => ({
    ...mediaStyle.value,
    objectFit: normalizeObjectFit(design.value.imageFit, 'cover'),
}));

const glassOpacity = computed(() => {
    const raw = normalizeNumber(props.data?.glassOpacity, 30);
    return Math.min(1, Math.max(0, raw / 100));
});

const glassBlur = computed(() => Math.max(0, normalizeNumber(props.data?.glassBlur, 10)));
const cardHeight = computed(() => {
    const value = normalizeNumber(props.data?.glassHeight, 0);

    return value > 0 ? `${value}px` : 'auto';
});
const cardMarginTop = computed(() => `${normalizeNumber(props.data?.glassMarginTop, 0)}px`);
const cardMarginBottom = computed(() => `${normalizeNumber(props.data?.glassMarginBottom, 0)}px`);
const cardMarginRight = computed(() => `${normalizeNumber(props.data?.glassMarginRight, 0)}px`);
const cardMarginLeft = computed(() => `${normalizeNumber(props.data?.glassMarginLeft, 0)}px`);

const cardStyle = computed(() => ({
    '--banner-card-radius': `${normalizeNumber(design.value.imageRadius, 28)}px`,
    '--banner-card-blur': `${glassBlur.value}px`,
    minHeight: cardHeight.value,
    marginTop: cardMarginTop.value,
    marginBottom: cardMarginBottom.value,
    marginRight: cardMarginRight.value,
    marginLeft: cardMarginLeft.value,
    backgroundColor: hexToRgba(resolvedGlassBgColor.value || BANNER_DEFAULTS.glassBgColor, glassOpacity.value),
    borderWidth: glassOpacity.value > 0 || glassBlur.value > 0 ? '1px' : '0px',
    borderColor: `rgba(255, 255, 255, ${Math.max(0, glassOpacity.value * 0.24)})`,
    backdropFilter: glassBlur.value > 0 ? `blur(${glassBlur.value}px)` : 'none',
    WebkitBackdropFilter: glassBlur.value > 0 ? `blur(${glassBlur.value}px)` : 'none',
    boxShadow: glassOpacity.value > 0 || glassBlur.value > 0
        ? '0 24px 60px -36px rgba(15, 23, 42, 0.75)'
        : 'none',
}));

const titleStyle = computed(() => ({
    color: resolvedTitleColor.value || BANNER_DEFAULTS.titleColor,
    fontSize: `clamp(2rem, 5.5vw, ${Math.max(36, normalizeNumber(design.value.titleSize, 52))}px)`,
    fontWeight: normalizeNumber(design.value.titleWeight, 800),
    lineHeight: normalizeNumber(design.value.titleLineHeight, 1.2),
}));

const subtitleStyle = computed(() => ({
    color: resolvedSubtitleColor.value || BANNER_DEFAULTS.subtitleColor,
    fontSize: `clamp(1rem, 2.6vw, ${Math.max(18, normalizeNumber(design.value.subtitleSize, 20))}px)`,
    fontWeight: normalizeNumber(design.value.bodyWeight, 400),
    lineHeight: normalizeNumber(design.value.bodyLineHeight, 1.8),
}));

const buttonStyle = computed(() => ({
    '--banner-btn-bg': resolvedButtonBgColor.value || BANNER_DEFAULTS.btnBgColor,
    '--banner-btn-text': resolvedButtonTextColor.value || BANNER_DEFAULTS.btnTextColor,
    '--btn-bg': resolvedButtonBgColor.value || BANNER_DEFAULTS.btnBgColor,
    '--btn-text': resolvedButtonTextColor.value || BANNER_DEFAULTS.btnTextColor,
}));
</script>

<template>
    <section class="relative w-full overflow-hidden bg-slate-950" :style="sectionStyle">
        <div
            class="banner-frame relative isolate flex w-full overflow-hidden"
            :style="bannerFrameStyle"
        >
            <div class="absolute inset-0 z-0">
                <div v-if="!mediaUrl" class="flex h-full w-full items-center justify-center bg-gray-900 text-gray-500">لا توجد وسائط</div>
                <video
                    v-else-if="isVideoBanner"
                    :src="mediaUrl"
                    autoplay
                    loop
                    muted
                    playsinline
                    class="banner-video h-full w-full"
                    :style="videoMediaStyle"
                />
                <img
                    v-else
                    :src="mediaUrl"
                    class="h-full w-full transition duration-[5s]"
                    :style="imageMediaStyle"
                    :alt="props.data.title || 'بانر الصفحة الرئيسية'"
                >
                <div class="banner-media-overlay absolute inset-0 transition-opacity duration-500" :class="props.data.overlay || 'bg-black/45'"></div>
            </div>

            <div
                class="relative z-10 flex h-full w-full min-w-0 px-4 py-4 sm:px-6 sm:py-6 lg:px-10 xl:px-16"
                :class="contentShellClass"
                :style="contentShellStyle"
            >
                <div
                    class="banner-content-card w-full min-w-0 max-w-xl border border-white/10 p-5 sm:max-w-2xl sm:p-6 lg:max-w-3xl lg:p-10"
                    :class="[textAlignClass, contentPositionClass]"
                    :style="cardStyle"
                >
                    <h1 v-if="props.data.title" class="mb-4 leading-tight drop-shadow-lg sm:mb-6" :style="titleStyle">
                        {{ props.data.title }}
                    </h1>

                    <p v-if="props.data.subtitle" class="mb-6 max-w-2xl drop-shadow-md sm:mb-8" :style="subtitleStyle">
                        {{ props.data.subtitle }}
                    </p>

                    <a
                        v-if="props.data.btnUrl"
                        :href="props.data.btnUrl"
                        class="banner-cta-button btn-custom inline-flex w-full items-center justify-center px-6 py-3.5 text-center font-bold shadow-lg transition-all duration-300 active:scale-95 sm:w-auto sm:px-8"
                        :style="buttonStyle"
                    >
                        {{ props.data.btnText || 'اضغط هنا' }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.banner-content-card {
    border-radius: var(--banner-card-radius);
    min-height: var(--banner-card-min-height);
    margin-top: var(--banner-card-margin-top);
    margin-bottom: var(--banner-card-margin-bottom);
    margin-right: var(--banner-card-margin-right);
    margin-left: var(--banner-card-margin-left);
}

@media (max-width: 639px) {
    .banner-frame {
        width: 100vw;
        min-height: 5cm !important;
        height: 5cm !important;
    }

    .banner-video {
        object-fit: cover !important;
    }

    .banner-media-overlay {
        opacity: 0.72;
    }

    .banner-content-card {
        min-height: auto;
        width: min(88vw, 22rem);
        margin: 0 auto;
        padding: 1rem;
        border-radius: min(var(--banner-card-radius), 1.5rem);
        backdrop-filter: blur(1px) !important;
        -webkit-backdrop-filter: blur(1px) !important;
        box-shadow: 0 18px 45px -32px rgba(15, 23, 42, 0.75);
    }

    .banner-cta-button {
        width: 100%;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background-color: color-mix(in srgb, var(--banner-btn-bg) 26%, rgba(15, 23, 42, 0.78)) !important;
        color: var(--banner-btn-text);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        box-shadow: 0 14px 36px -24px rgba(15, 23, 42, 0.72);
    }
}
</style>
