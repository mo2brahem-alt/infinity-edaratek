<script setup>
import { computed, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { Menu, X, Facebook, Twitter, Instagram, Linkedin } from 'lucide-vue-next';
import AppPageFeedback from '@/Components/AppPageFeedback.vue';
import { useProjectBranding } from '@/composables/useProjectBranding';

const page = usePage();
const settings = computed(() => page.props.app_settings || {});
const { siteName, projectLogoUrl } = useProjectBranding();
const headerMenus = computed(() => page.props.headerMenus || []);
const footerCols = computed(() => page.props.footerColumns || []);
const isMenuOpen = ref(false);
const openDesktopMenuId = ref(null);

const hasPageFeedback = computed(() => {
    const flash = page.props?.flash || {};

    return ['success', 'message', 'warning', 'info', 'error'].some((key) => String(flash[key] || '').trim() !== '');
});

const hasHeaderSocialLinks = computed(() => Boolean(
    settings.value.header_facebook
    || settings.value.header_twitter
    || settings.value.header_instagram
    || settings.value.header_linkedin
));

const buttonThemeClass = computed(() => `theme-btn-${settings.value.btn_style || 'solid'}`);
const buttonShapeClass = computed(() => {
    switch (settings.value.btn_shape) {
    case 'rounded-none':
        return 'theme-btn-shape-none';
    case 'rounded-md':
        return 'theme-btn-shape-md';
    case 'rounded-lg':
        return 'theme-btn-shape-lg';
    case 'rounded-full':
        return 'theme-btn-shape-full';
    default:
        return 'theme-btn-shape-xl';
    }
});

const buttonAnimationClass = computed(() => {
    switch (settings.value.btn_animation) {
    case 'hover-scale':
        return 'theme-btn-anim-scale';
    case 'hover-glow':
        return 'theme-btn-anim-glow';
    case 'hover-lift':
        return 'theme-btn-anim-lift';
    default:
        return 'theme-btn-anim-none';
    }
});

const siteLogo = computed(() => projectLogoUrl.value);
const showHeaderLogo = computed(() => Boolean(siteLogo.value) && !['0', 0, false, 'false'].includes(settings.value.header_show_logo));

const headerBrandPosition = computed(() => {
    const normalized = String(settings.value.header_brand_position || 'left').toLowerCase();

    return ['right', 'center', 'left'].includes(normalized) ? normalized : 'left';
});

const desktopHeaderLayoutClass = computed(() => `ui-site-nav-desktop-grid--brand-${headerBrandPosition.value}`);

const themeStyles = computed(() => ({
    '--bg-color': settings.value.bg_color || '#111827',
    '--primary': settings.value.primary_color || '#2563eb',
    '--secondary': settings.value.secondary_color || '#1e1b4b',
    '--text-color': settings.value.text_color || '#9ca3af',
    '--heading-color': settings.value.heading_color || '#ffffff',
    '--btn-bg': settings.value.btn_bg_color || '#2563eb',
    '--btn-text': settings.value.btn_text_color || '#ffffff',

    '--header-bg': settings.value.header_bg_color || '#111827',
    '--header-text': settings.value.header_text_color || '#ffffff',
    '--header-link-hover': settings.value.header_link_hover_color || settings.value.primary_color || '#2563eb',
    '--header-height': `${settings.value.header_height || 80}px`,
    '--header-logo-size': `${settings.value.header_logo_size || 40}px`,
    '--header-logo-width': `${settings.value.header_logo_width || settings.value.header_logo_size || 40}px`,
    '--header-logo-height': `${settings.value.header_logo_height || settings.value.header_logo_size || 40}px`,
    '--header-logo-padding-inline': `${settings.value.header_logo_padding_inline || 0}px`,
    '--header-logo-padding-block': `${settings.value.header_logo_padding_block || 0}px`,
    '--header-logo-margin-inline': `${settings.value.header_logo_margin_inline || 0}px`,
    '--header-logo-margin-block': `${settings.value.header_logo_margin_block || 0}px`,
    '--header-title-size': `${settings.value.header_title_size || 22}px`,
    '--header-menu-size': `${settings.value.header_menu_size || 15}px`,
    '--header-padding-x': `${settings.value.header_padding_x || 24}px`,
    '--header-cta-radius': `${settings.value.header_cta_radius || 10}px`,
    '--header-blur': `${settings.value.header_blur || 14}px`,
    '--header-border-opacity': `${(Number(settings.value.header_border_opacity ?? 10) / 100)}`,

    '--footer-bg': settings.value.footer_bg_color || '#1e1b4b',
    '--footer-text': settings.value.footer_text_color || '#9ca3af',
    '--footer-heading': settings.value.footer_heading_color || '#ffffff',
    '--footer-pt': `${settings.value.footer_padding_top || 64}px`,
    '--footer-pb': `${settings.value.footer_padding_bottom || 32}px`,
    '--footer-gap': `${settings.value.footer_columns_gap || 48}px`,
    '--footer-title-size': `${settings.value.footer_title_size || 18}px`,
    '--footer-text-size': `${settings.value.footer_text_size || 14}px`,
    '--footer-link-size': `${settings.value.footer_link_size || 14}px`,
}));

const toggleDesktopMenu = (menuId) => {
    openDesktopMenuId.value = openDesktopMenuId.value === menuId ? null : menuId;
};

const closeDesktopMenus = () => {
    openDesktopMenuId.value = null;
};

watch(() => page.url, () => {
    isMenuOpen.value = false;
    closeDesktopMenus();
});
</script>

<template>
    <div
        :style="themeStyles"
        class="ui-site-shell flex min-w-0 flex-col"
        :class="[buttonThemeClass, buttonShapeClass, buttonAnimationClass]"
    >
        <nav class="ui-site-nav" @keydown.esc="closeDesktopMenus">
            <div class="ui-site-container min-w-0" :style="{ paddingInline: 'var(--header-padding-x)' }">
                <div
                    class="hidden min-w-0 md:grid ui-site-nav-desktop-grid"
                    :class="desktopHeaderLayoutClass"
                    :style="{ minHeight: 'var(--header-height)' }"
                >
                    <Link
                        href="/"
                        class="ui-site-header-brand ui-site-brand-link group flex min-w-0 items-center gap-3"
                    >
                        <div v-if="showHeaderLogo" class="ui-site-brand-logo overflow-hidden">
                            <img :src="siteLogo" class="h-full w-full object-contain" :alt="siteName">
                        </div>
                        <span
                            v-if="!showHeaderLogo"
                            class="ui-site-brand-title truncate font-bold tracking-wide transition"
                            :style="{ color: 'var(--header-text)', fontSize: 'var(--header-title-size)' }"
                        >
                            {{ siteName }}
                        </span>
                    </Link>

                    <div class="ui-site-header-menus hidden min-w-0 items-center md:flex">
                        <div v-for="menu in headerMenus" :key="menu.id" class="relative">
                            <a
                                v-if="!(menu.items || []).length"
                                :href="menu.url"
                                class="ui-site-link py-2 font-medium"
                                :style="{ fontSize: 'var(--header-menu-size)' }"
                            >
                                {{ menu.title }}
                            </a>
                            <button
                                v-else
                                type="button"
                                class="ui-site-link flex items-center gap-1 py-2 font-medium"
                                :style="{ fontSize: 'var(--header-menu-size)' }"
                                :aria-expanded="openDesktopMenuId === menu.id"
                                aria-haspopup="menu"
                                @click="toggleDesktopMenu(menu.id)"
                            >
                                <span>{{ menu.title }}</span>
                            </button>

                            <div
                                v-if="(menu.items || []).length && openDesktopMenuId === menu.id"
                                class="ui-site-dropdown z-50"
                                role="menu"
                            >
                                <a
                                    v-for="item in menu.items"
                                    :key="item.id"
                                    :href="item.url"
                                    class="ui-site-dropdown-link"
                                    @click="closeDesktopMenus"
                                >
                                    {{ item.label }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="ui-site-header-actions hidden items-center gap-4 md:flex">
                        <div
                            v-if="hasHeaderSocialLinks"
                            class="flex items-center gap-3 border-l border-white/10 pl-4"
                        >
                            <a
                                v-if="settings.header_facebook"
                                :href="settings.header_facebook"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ui-site-link transition"
                                aria-label="فيسبوك"
                            >
                                <Facebook class="h-4 w-4" />
                            </a>
                            <a
                                v-if="settings.header_twitter"
                                :href="settings.header_twitter"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ui-site-link transition"
                                aria-label="تويتر"
                            >
                                <Twitter class="h-4 w-4" />
                            </a>
                            <a
                                v-if="settings.header_instagram"
                                :href="settings.header_instagram"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ui-site-link transition"
                                aria-label="إنستغرام"
                            >
                                <Instagram class="h-4 w-4" />
                            </a>
                            <a
                                v-if="settings.header_linkedin"
                                :href="settings.header_linkedin"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ui-site-link transition"
                                aria-label="لينكدإن"
                            >
                                <Linkedin class="h-4 w-4" />
                            </a>
                        </div>

                        <a
                            v-if="settings.header_contact_url"
                            :href="settings.header_contact_url"
                            class="btn-custom px-5 py-2 font-bold shadow-lg transition"
                            :style="{ fontSize: 'var(--header-menu-size)' }"
                        >
                            {{ settings.header_contact_text || 'تواصل معنا' }}
                        </a>

                        <Link
                            v-if="!$page.props.auth.user"
                            href="/login"
                            class="ui-site-link font-bold"
                            :style="{ fontSize: 'var(--header-menu-size)' }"
                        >
                            دخول
                        </Link>
                    </div>
                </div>

                <div
                    class="relative flex min-w-0 items-center justify-end md:hidden"
                    :style="{ minHeight: 'var(--header-height)' }"
                >
                    <Link
                        href="/"
                        class="ui-site-brand-link group absolute left-1/2 flex min-w-0 max-w-[calc(100%-9rem)] -translate-x-1/2 items-center justify-center gap-3"
                    >
                        <div v-if="showHeaderLogo" class="ui-site-brand-logo overflow-hidden">
                            <img :src="siteLogo" class="h-full w-full object-contain" :alt="siteName">
                        </div>
                        <span
                            v-if="!showHeaderLogo"
                            class="ui-site-brand-title truncate font-bold tracking-wide transition"
                            :style="{ color: 'var(--header-text)', fontSize: 'var(--header-title-size)' }"
                        >
                            {{ siteName }}
                        </span>
                    </Link>

                    <div v-if="hasHeaderSocialLinks" class="absolute left-0 flex items-center gap-3">
                        <a
                            v-if="settings.header_facebook"
                            :href="settings.header_facebook"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ui-site-link transition"
                            aria-label="فيسبوك"
                        >
                            <Facebook class="h-4 w-4" />
                        </a>
                        <a
                            v-if="settings.header_twitter"
                            :href="settings.header_twitter"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ui-site-link transition"
                            aria-label="تويتر"
                        >
                            <Twitter class="h-4 w-4" />
                        </a>
                        <a
                            v-if="settings.header_instagram"
                            :href="settings.header_instagram"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ui-site-link transition"
                            aria-label="إنستغرام"
                        >
                            <Instagram class="h-4 w-4" />
                        </a>
                        <a
                            v-if="settings.header_linkedin"
                            :href="settings.header_linkedin"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ui-site-link transition"
                            aria-label="لينكدإن"
                        >
                            <Linkedin class="h-4 w-4" />
                        </a>
                    </div>

                    <div class="md:hidden">
                        <button
                            type="button"
                            class="ui-site-link rounded-2xl border border-white/10 bg-white/5 p-2 transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                            :aria-expanded="isMenuOpen"
                            aria-label="فتح أو إغلاق القائمة"
                            @click="isMenuOpen = !isMenuOpen"
                        >
                            <Menu v-if="!isMenuOpen" class="h-7 w-7" />
                            <X v-else class="h-7 w-7" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="openDesktopMenuId" class="fixed inset-0 z-40 hidden md:block" @click="closeDesktopMenus" />

            <div v-if="isMenuOpen" class="ui-site-mobile-panel animate-fade-in">
                <div class="space-y-2 text-right">
                    <div v-for="menu in headerMenus" :key="menu.id">
                        <a
                            v-if="!(menu.items || []).length"
                            :href="menu.url"
                            class="ui-site-link block border-b border-white/5 py-3 text-right text-base font-medium"
                        >
                            {{ menu.title }}
                        </a>
                        <div v-else class="py-2 text-right">
                            <div class="mb-2 text-sm font-bold text-[var(--header-text)] opacity-60">{{ menu.title }}</div>
                            <a
                                v-for="item in menu.items"
                                :key="item.id"
                                :href="item.url"
                                class="ui-site-link block border-r-2 border-white/10 py-2 pr-4 text-right text-sm transition hover:border-[var(--header-link-hover)]"
                            >
                                {{ item.label }}
                            </a>
                        </div>
                    </div>

                    <a
                        v-if="settings.header_contact_url"
                        :href="settings.header_contact_url"
                        class="btn-custom mt-4 block w-full py-3 text-center font-bold"
                    >
                        {{ settings.header_contact_text || 'تواصل معنا' }}
                    </a>

                    <Link
                        v-if="!$page.props.auth.user"
                        href="/login"
                        class="ui-site-link block w-full py-2 text-center"
                    >
                        تسجيل الدخول
                    </Link>
                </div>
            </div>
        </nav>

        <main class="min-w-0 w-full flex-grow" :style="{ paddingTop: 'calc(var(--header-height) + 0.75rem)' }">
            <div v-if="hasPageFeedback" class="ui-site-container pt-4">
                <AppPageFeedback />
            </div>
            <slot />
        </main>

        <footer class="ui-site-footer transition-colors duration-300" :style="{ paddingTop: 'var(--footer-pt)', paddingBottom: 'var(--footer-pb)' }">
            <div class="ui-site-container">
                <div class="mb-12 grid grid-cols-1 md:grid-cols-4" :style="{ gap: 'var(--footer-gap)', textAlign: settings.footer_align || 'right' }">
                    <div class="col-span-1 md:col-span-1">
                        <div class="mb-6 flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-[var(--primary)] to-blue-700 font-bold text-white">E</div>
                            <span class="font-bold" :style="{ color: 'var(--footer-heading)', fontSize: 'var(--footer-title-size)' }">{{ siteName }}</span>
                        </div>
                        <p class="leading-loose opacity-80" :style="{ fontSize: 'var(--footer-text-size)' }">
                            {{ settings.footer_desc || 'نظام إدارة متكامل...' }}
                        </p>
                    </div>

                    <div v-for="col in footerCols" :key="col.id">
                        <h4 class="mb-6 inline-block border-b border-white/10 pb-2 font-bold" :style="{ color: 'var(--footer-heading)', fontSize: 'var(--footer-title-size)' }">
                            {{ col.title }}
                        </h4>
                        <ul class="space-y-3">
                            <li v-for="item in col.items" :key="item.id">
                                <a :href="item.url" class="ui-site-footer-link" :style="{ fontSize: 'var(--footer-link-size)' }">
                                    <span class="h-1.5 w-1.5 rounded-full bg-white/20"></span>
                                    {{ item.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-8 text-center opacity-50" :style="{ fontSize: 'var(--footer-text-size)' }">
                    {{ settings.footer_text || 'جميع الحقوق محفوظة' }}
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
.btn-custom {
    background-color: var(--btn-bg);
    color: var(--btn-text);
    transition: all 0.3s ease;
    border-radius: var(--header-cta-radius);
}

.theme-btn-solid .btn-custom {
    border: none;
}

.theme-btn-solid .btn-custom:hover {
    filter: brightness(110%);
    box-shadow: 0 10px 20px -10px var(--primary);
}

.theme-btn-glass .btn-custom {
    background: color-mix(in srgb, var(--btn-bg), transparent 20%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.theme-btn-glass .btn-custom:hover {
    background: var(--btn-bg);
}

.theme-btn-gradient .btn-custom {
    background-image: linear-gradient(45deg, var(--btn-bg), var(--secondary));
    border: none;
}

.theme-btn-gradient .btn-custom:hover {
    filter: brightness(120%);
}

.theme-btn-outline .btn-custom {
    background: transparent;
    border: 2px solid var(--btn-bg);
    color: var(--btn-bg);
}

.theme-btn-outline .btn-custom:hover {
    background: var(--btn-bg);
    color: var(--btn-text);
}

.theme-btn-shape-none .btn-custom {
    border-radius: 0;
}

.theme-btn-shape-md .btn-custom {
    border-radius: 0.375rem;
}

.theme-btn-shape-lg .btn-custom {
    border-radius: 0.5rem;
}

.theme-btn-shape-xl .btn-custom {
    border-radius: 0.75rem;
}

.theme-btn-shape-full .btn-custom {
    border-radius: 9999px;
}

.theme-btn-anim-scale .btn-custom:hover {
    transform: scale(1.04);
}

.theme-btn-anim-glow .btn-custom:hover {
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--btn-bg), white 20%), 0 18px 36px -18px color-mix(in srgb, var(--btn-bg), transparent 20%);
}

.theme-btn-anim-lift .btn-custom:hover {
    transform: translateY(-3px);
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

.ui-site-brand-logo {
    width: var(--header-logo-width);
    height: var(--header-logo-height);
    padding-inline: var(--header-logo-padding-inline);
    padding-block: var(--header-logo-padding-block);
    margin-inline: var(--header-logo-margin-inline);
    margin-block: var(--header-logo-margin-block);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    box-sizing: content-box;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (min-width: 768px) {
    .ui-site-nav-desktop-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: clamp(1rem, 2vw, 2rem);
    }

    .ui-site-header-brand,
    .ui-site-header-menus,
    .ui-site-header-actions {
        min-width: 0;
    }

    .ui-site-header-menus {
        display: flex;
        align-items: center;
        gap: clamp(1rem, 1.8vw, 2rem);
        flex-wrap: wrap;
    }

    .ui-site-nav-desktop-grid--brand-left {
        grid-template-areas: "brand menus actions";
    }

    .ui-site-nav-desktop-grid--brand-center {
        grid-template-areas: "menus brand actions";
    }

    .ui-site-nav-desktop-grid--brand-right {
        grid-template-areas: "actions menus brand";
    }

    .ui-site-header-brand {
        grid-area: brand;
    }

    .ui-site-header-menus {
        grid-area: menus;
    }

    .ui-site-header-actions {
        grid-area: actions;
        justify-content: flex-end;
    }

    .ui-site-nav-desktop-grid--brand-left .ui-site-header-brand {
        justify-self: start;
    }

    .ui-site-nav-desktop-grid--brand-left .ui-site-header-menus {
        justify-self: center;
    }

    .ui-site-nav-desktop-grid--brand-left .ui-site-header-actions {
        justify-self: end;
    }

    .ui-site-nav-desktop-grid--brand-center .ui-site-header-brand {
        justify-self: center;
    }

    .ui-site-nav-desktop-grid--brand-center .ui-site-header-menus {
        justify-self: start;
    }

    .ui-site-nav-desktop-grid--brand-center .ui-site-header-actions {
        justify-self: end;
    }

    .ui-site-nav-desktop-grid--brand-right .ui-site-header-brand {
        justify-self: end;
    }

    .ui-site-nav-desktop-grid--brand-right .ui-site-header-menus {
        justify-self: center;
    }

    .ui-site-nav-desktop-grid--brand-right .ui-site-header-actions {
        justify-self: start;
    }
}

@media (max-width: 767px) {
    .ui-site-brand-logo {
        width: calc(var(--header-logo-width) + 4px);
        height: calc(var(--header-logo-height) + 4px);
    }
}
</style>
