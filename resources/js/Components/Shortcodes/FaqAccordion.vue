<script setup>
import { computed, ref } from 'vue';
import { ChevronDown, HelpCircle } from 'lucide-vue-next';

const props = defineProps({ data: Object });

const activeIndex = ref(null);
const design = computed(() => props.data?.design || {});
const resolveFileUrl = (path) => {
    if (!path) return null;
    return path.startsWith('http') ? path : `/media-files/${path}`;
};
const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage));

const toggle = (index) => {
    activeIndex.value = activeIndex.value === index ? null : index;
};

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
</script>

<template>
    <section class="mx-auto max-w-4xl px-6" :style="sectionStyle">
        <div class="ui-public-header" :class="design.textAlign || 'text-center'">
            <span class="ui-public-badge mx-auto" :class="design.textAlign === 'text-right' ? 'mr-0 ml-auto' : design.textAlign === 'text-left' ? 'ml-0 mr-auto' : ''">
                <HelpCircle class="h-4 w-4" />
                <span>الأسئلة الشائعة</span>
            </span>
            <h2 class="ui-public-title" :style="{ color: data.titleColor || undefined, fontSize: `${design.titleSize ?? 38}px` }">
                {{ data.title }}
            </h2>
        </div>

        <div class="space-y-4">
            <article
                v-for="(item, index) in data.items"
                :key="index"
                class="ui-public-card overflow-hidden transition-all duration-300"
                :class="{ 'ring-1 ring-sky-500/30': activeIndex === index }"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 p-5 text-right"
                    :aria-expanded="activeIndex === index"
                    :aria-label="`عرض إجابة السؤال: ${item.question}`"
                    @click="toggle(index)"
                >
                    <span class="font-bold leading-8" :style="{ color: data.questionColor || undefined, fontSize: `${design.subtitleSize ?? 20}px` }">
                        {{ item.question }}
                    </span>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 transition" :class="{ 'rotate-180 bg-sky-600 text-white': activeIndex === index }">
                        <ChevronDown class="h-5 w-5" />
                    </span>
                </button>

                <div v-show="activeIndex === index" class="animate-fade-in px-5 pb-5 pt-0">
                    <p class="border-t border-white/10 pt-4 leading-8" :style="{ color: data.answerColor || undefined, fontSize: `${design.bodySize ?? 18}px` }">
                        {{ item.answer }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>

<style scoped>
.animate-fade-in { animation: fadeIn 0.28s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
</style>
