<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import FrontLayout from '@/Layouts/FrontLayout.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';

import ContactForm from '@/Components/Shortcodes/ContactForm.vue';
import Quiz from '@/Components/Shortcodes/Quiz.vue';
import InfoSection from '@/Components/Shortcodes/InfoSection.vue';
import Banner from '@/Components/Shortcodes/Banner.vue';
import SectionTitle from '@/Components/Shortcodes/SectionTitle.vue';
import StatsCounter from '@/Components/Shortcodes/StatsCounter.vue';
import PricingTable from '@/Components/Shortcodes/PricingTable.vue';
import FaqAccordion from '@/Components/Shortcodes/FaqAccordion.vue';

const props = defineProps({
    homeContent: String,
    components: Array,
    registrationNotice: {
        type: String,
        default: '',
    },
});

const page = usePage();
const settings = computed(() => page.props.app_settings || {});
const siteName = computed(() => settings.value.site_name || 'إدارتك');
const backgroundEffectsEnabled = computed(() => ['1', 1, true, 'true', 'on'].includes(settings.value.home_background_effects_enabled));
const backgroundEffectIntensity = computed(() => {
    const value = settings.value.home_background_effect_intensity || 'normal';

    return ['subtle', 'normal', 'strong'].includes(value) ? value : 'normal';
});
const backgroundEffectClass = computed(() => backgroundEffectsEnabled.value ? `ui-home-stage--ambient-${backgroundEffectIntensity.value}` : '');

const staticShortcodes = { '[contact-form]': ContactForm };

const normalizeMediaPath = (path) => {
    if (!path || typeof path !== 'string') return null;

    return path
        .trim()
        .replace(/^https?:\/\/[^/]+/i, '')
        .replace(/^\/+/, '')
        .replace(/^storage\//i, '')
        .replace(/^public\/storage\//i, '')
        .replace(/^uploads\//i, 'uploads/');
};

const isSiteLogoMedia = (path) => {
    const normalizedPath = normalizeMediaPath(path);
    const normalizedSiteLogo = normalizeMediaPath(settings.value.site_logo);

    return Boolean(normalizedPath) && Boolean(normalizedSiteLogo) && normalizedPath === normalizedSiteLogo;
};

const normalizeComponentData = (data) => {
    if (!data || typeof data !== 'object') {
        return data;
    }

    const normalized = {
        ...data,
        design: data.design ? { ...data.design } : undefined,
    };

    if (normalized.type === 'section_title') {
        if (isSiteLogoMedia(normalized.image)) {
            normalized.image = '';
        }

        if (isSiteLogoMedia(normalized.bgImage)) {
            normalized.bgImage = '';
            normalized.bgType = 'none';
        }

        if (normalized.design && isSiteLogoMedia(normalized.design.backgroundImage)) {
            normalized.design.backgroundImage = '';
            normalized.design.backgroundType = 'none';
        }
    }

    return normalized;
};

const tryParseJSON = (str) => {
    try {
        const o = JSON.parse(str);
        if (o && typeof o === 'object') return o;
    } catch (e) {
        return false;
    }

    return false;
};

const sanitizeHtml = (html) => {
    if (!html) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    doc.querySelectorAll('script, style, iframe, object, embed, link, meta').forEach((el) => el.remove());

    doc.querySelectorAll('*').forEach((el) => {
        [...el.attributes].forEach((attr) => {
            const name = attr.name.toLowerCase();
            const value = (attr.value || '').trim().toLowerCase();
            if (name.startsWith('on') || value.startsWith('javascript:')) {
                el.removeAttribute(attr.name);
            }
        });
    });

    return doc.body.innerHTML;
};

const getRenderableHtml = (html) => {
    const sanitized = sanitizeHtml(html);
    if (!sanitized) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(sanitized, 'text/html');
    const textContent = (doc.body.textContent || '').replace(/\u00a0/g, ' ').trim();
    const hasRenderableMedia = Boolean(doc.body.querySelector('img, video, iframe, svg, canvas, table, hr'));

    return textContent || hasRenderableMedia ? sanitized : '';
};

const processedContent = computed(() => {
    let contentParts = [{ type: 'html', content: props.homeContent || '' }];

    if (!props.homeContent && !props.components.length) {
        return [{
            type: 'html',
            content: '<div class="text-center py-20 text-gray-500">لم يتم إضافة محتوى للصفحة الرئيسية بعد. اذهب إلى لوحة التحكم ثم إعدادات الموقع ثم الرئيسية.</div>',
        }];
    }

    if (props.components && props.components.length > 0) {
        props.components.forEach((comp) => {
            const newParts = [];
            contentParts.forEach((part) => {
                if (part.type !== 'html') {
                    newParts.push(part);
                    return;
                }

                const splitText = part.content.split(comp.shortcode);

                splitText.forEach((text, index) => {
                    if (text) newParts.push({ type: 'html', content: text });

                    if (index < splitText.length - 1) {
                        const jsonData = tryParseJSON(comp.content);
                        if (jsonData) {
                            const normalizedData = normalizeComponentData(jsonData);
                            const type = normalizedData.type || (Array.isArray(normalizedData) ? 'quiz' : 'html');
                            newParts.push({
                                type,
                                data: normalizedData,
                                title: comp.name,
                            });
                        } else {
                                newParts.push({
                                    type: 'html',
                                    content: comp.safe_content || comp.content || '',
                                    title: comp.name,
                                });
                        }
                    }
                });
            });
            contentParts = newParts;
        });
    }

    const regex = new RegExp(`(${Object.keys(staticShortcodes).map((k) => `\\${k.split('').join('\\')}`).join('|')})`, 'g');
    const finalParts = [];

    contentParts.forEach((part) => {
        if (part.type !== 'html') {
            finalParts.push(part);
            return;
        }

        part.content.split(regex).forEach((subPart) => {
            if (staticShortcodes[subPart]) {
                finalParts.push({ type: 'component', component: staticShortcodes[subPart] });
            } else if (subPart) {
                finalParts.push({ type: 'html', content: subPart });
            }
        });
    });

    return finalParts;
});
</script>

<template>
    <Head :title="`الرئيسية - ${siteName}`" />

    <FrontLayout>
        <div class="ui-home-stage pb-6 pt-0 sm:pb-8 lg:pb-10" :class="[{ 'ui-home-stage--ambient': backgroundEffectsEnabled }, backgroundEffectClass]">
            <span v-if="backgroundEffectsEnabled" class="ui-home-ambient-layer" aria-hidden="true"></span>
            <div class="ui-page-shell ui-home-flow min-w-0">
                <AppInlineAlert
                    v-if="registrationNotice"
                    variant="success"
                    title="طلب الانضمام قيد المراجعة"
                    :message="registrationNotice"
                />

                <template v-for="(item, index) in processedContent" :key="index">
                    <div v-if="item.type === 'banner'" class="ui-home-module ui-home-module--bleed ui-home-module--hero">
                        <Banner :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'section_title'" class="ui-home-module ui-home-module--narrow">
                        <SectionTitle :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'stats'" class="ui-home-module">
                        <StatsCounter :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'pricing'" class="ui-home-module">
                        <PricingTable :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'faq'" class="ui-home-module ui-home-module--narrow">
                        <FaqAccordion :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'info_section'" class="ui-home-module">
                        <InfoSection :data="item.data" />
                    </div>

                    <div v-else-if="item.type === 'quiz'" class="ui-home-module ui-home-module--narrow">
                        <Quiz :data="item.data" :title="item.title" />
                    </div>

                    <div v-else-if="item.type === 'component'" class="ui-home-module ui-home-module--narrow">
                        <component :is="item.component" />
                    </div>

                    <article
                        v-else-if="getRenderableHtml(item.content)"
                        class="ui-card-soft ui-home-richtext prose prose-lg max-w-none"
                        v-html="getRenderableHtml(item.content)"
                    />
                </template>
            </div>
        </div>
    </FrontLayout>
</template>
