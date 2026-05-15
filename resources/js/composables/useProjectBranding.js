import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const normalizeMediaPath = (path) => {
    const value = String(path || '').trim();

    if (value === '') return null;
    if (value.startsWith('http://') || value.startsWith('https://')) return value;
    if (value.startsWith('/media-files/') || value.startsWith('/admin/media/')) return value;
    if (value.startsWith('/storage/')) return `/media-files/${value.replace(/^\/storage\//i, '')}`;
    if (value.startsWith('/')) return value;

    return `/media-files/${value
        .replace(/^\/?public\/storage\//i, '')
        .replace(/^\/?storage\//i, '')
        .replace(/^\/?media-files\//i, '')
        .replace(/^\/+/, '')}`;
};

const numericSetting = (value, fallback, min, max) => {
    const number = Number(value);
    const normalized = Number.isFinite(number) ? number : fallback;

    return Math.min(max, Math.max(min, normalized));
};

const enabledSetting = (value, fallback = true) => {
    if (value === undefined || value === null || value === '') return fallback;

    return !['0', 0, false, 'false', 'off'].includes(value);
};

export function useProjectBranding() {
    const page = usePage();
    const settings = computed(() => page.props?.app_settings || {});

    const siteName = computed(() => String(settings.value.site_name || 'إدارتك'));
    const projectLogoUrl = computed(() => normalizeMediaPath(settings.value.site_logo) || '/images/logo.png');
    const showHeaderLogo = computed(() => enabledSetting(settings.value.header_show_logo, true));

    const headerLogoStyle = computed(() => ({
        width: `${numericSetting(settings.value.header_logo_width || settings.value.header_logo_size, 40, 24, 320)}px`,
        height: `${numericSetting(settings.value.header_logo_height || settings.value.header_logo_size, 40, 24, 240)}px`,
        paddingInline: `${numericSetting(settings.value.header_logo_padding_inline, 0, 0, 80)}px`,
        paddingBlock: `${numericSetting(settings.value.header_logo_padding_block, 0, 0, 80)}px`,
        marginInline: `${numericSetting(settings.value.header_logo_margin_inline, 0, 0, 120)}px`,
        marginBlock: `${numericSetting(settings.value.header_logo_margin_block, 0, 0, 120)}px`,
    }));

    return {
        settings,
        siteName,
        projectLogoUrl,
        showHeaderLogo,
        headerLogoStyle,
    };
}
