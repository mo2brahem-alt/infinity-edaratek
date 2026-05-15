<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import FrontLayout from '@/Layouts/FrontLayout.vue';

import ContactForm from '@/Components/Shortcodes/ContactForm.vue';
import Quiz from '@/Components/Shortcodes/Quiz.vue';
import InfoSection from '@/Components/Shortcodes/InfoSection.vue';
import Banner from '@/Components/Shortcodes/Banner.vue';
import SectionTitle from '@/Components/Shortcodes/SectionTitle.vue';

const props = defineProps({
    page: Object,
    components: Array,
});

const staticShortcodes = { '[contact-form]': ContactForm };

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

const processedContent = computed(() => {
    let contentParts = [{ type: 'html', content: props.page.safe_content || props.page.content || '' }];

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
                            if (Array.isArray(jsonData)) newParts.push({ type: 'quiz', data: jsonData, title: comp.name });
                            else if (jsonData.type === 'info_section') newParts.push({ type: 'info_section', data: jsonData });
                            else if (jsonData.type === 'banner') newParts.push({ type: 'banner', data: jsonData });
                            else if (jsonData.type === 'section_title') newParts.push({ type: 'section_title', data: jsonData });
                            else newParts.push({ type: 'html', content: comp.safe_content || comp.content });
                        } else {
                            newParts.push({ type: 'html', content: comp.safe_content || comp.content });
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
            if (staticShortcodes[subPart]) finalParts.push({ type: 'component', component: staticShortcodes[subPart] });
            else if (subPart) finalParts.push({ type: 'html', content: subPart });
        });
    });

    return finalParts;
});
</script>

<template>
    <Head :title="page.title" />

    <FrontLayout>
        <div class="ui-site-container py-6 sm:py-8">
            <div class="ui-page-shell">
                <section class="ui-page-hero">
                    <div class="ui-page-heading text-right">
                        <h1 class="ui-page-title">{{ page.title }}</h1>
                    </div>
                </section>

                <template v-for="(item, index) in processedContent" :key="index">
                    <Banner v-if="item.type === 'banner'" :data="item.data" />

                    <SectionTitle v-else-if="item.type === 'section_title'" :data="item.data" />

                    <InfoSection v-else-if="item.type === 'info_section'" :data="item.data" />

                    <div v-else-if="item.type === 'quiz'" class="ui-site-container max-w-4xl px-0">
                        <Quiz :data="item.data" :title="item.title" />
                    </div>

                    <div v-else-if="item.type === 'component'" class="ui-site-container max-w-4xl px-0">
                        <component :is="item.component" />
                    </div>

                    <article v-else class="ui-card-soft prose prose-lg max-w-none p-6 sm:p-8" v-html="sanitizeHtml(item.content)" />
                </template>
            </div>
        </div>
    </FrontLayout>
</template>
