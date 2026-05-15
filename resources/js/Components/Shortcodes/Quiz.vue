<script setup>
import { computed, ref } from 'vue';
import { AlertCircle, CheckCircle, ChevronLeft, RefreshCw, XCircle } from 'lucide-vue-next';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'اختبار معلومات',
    },
});

const currentQuestionIndex = ref(0);
const selectedOption = ref(null);
const score = ref(0);
const showResult = ref(false);
const isAnswered = ref(false);

const currentQuestion = computed(() => props.data[currentQuestionIndex.value]);
const progress = computed(() => (props.data.length ? ((currentQuestionIndex.value + 1) / props.data.length) * 100 : 0));

const selectOption = (index) => {
    if (isAnswered.value) return;
    selectedOption.value = index;
};

const submitAnswer = () => {
    if (selectedOption.value === null) return;

    isAnswered.value = true;
    if (currentQuestion.value.options[selectedOption.value].isCorrect) {
        score.value += 1;
    }
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < props.data.length - 1) {
        currentQuestionIndex.value += 1;
        selectedOption.value = null;
        isAnswered.value = false;
        return;
    }

    showResult.value = true;
};

const resetQuiz = () => {
    currentQuestionIndex.value = 0;
    selectedOption.value = null;
    score.value = 0;
    showResult.value = false;
    isAnswered.value = false;
};
</script>

<template>
    <section v-if="data && data.length > 0" class="ui-public-widget mx-auto my-8 max-w-3xl overflow-hidden">
        <header class="flex items-center justify-between gap-4 border-b border-white/10 px-6 py-5 md:px-8">
            <div class="ui-public-header !mb-0 text-right">
                <span class="ui-public-badge w-fit">
                    <AlertCircle class="h-4 w-4" />
                    <span>اختبار تفاعلي</span>
                </span>
                <h3 class="ui-public-title text-2xl">{{ title }}</h3>
            </div>

            <span v-if="!showResult" class="ui-public-badge shrink-0">
                {{ currentQuestionIndex + 1 }} / {{ data.length }}
            </span>
        </header>

        <div v-if="!showResult" class="h-1.5 w-full bg-white/5">
            <div class="h-full bg-sky-500 transition-all duration-500 ease-out" :style="{ width: `${progress}%` }" />
        </div>

        <div class="p-6 md:p-8">
            <div v-if="!showResult">
                <h2 class="ui-public-title mb-6 text-2xl leading-10">{{ currentQuestion.text }}</h2>

                <div class="space-y-3">
                    <button
                        v-for="(option, index) in currentQuestion.options"
                        :key="index"
                        type="button"
                        class="ui-public-card flex w-full items-center justify-between gap-3 px-5 py-4 text-right transition"
                        :class="{
                            'ring-1 ring-sky-500/40': !isAnswered && selectedOption === index,
                            'border-green-500/40 bg-green-500/10 text-green-200': isAnswered && option.isCorrect,
                            'border-red-500/40 bg-red-500/10 text-red-200': isAnswered && !option.isCorrect && selectedOption === index,
                            'opacity-55': isAnswered && !option.isCorrect && selectedOption !== index,
                        }"
                        :disabled="isAnswered"
                        @click="selectOption(index)"
                    >
                        <span class="font-semibold leading-8 text-inherit">{{ option.text }}</span>
                        <CheckCircle v-if="isAnswered && option.isCorrect" class="h-5 w-5 shrink-0 text-green-400" />
                        <XCircle v-else-if="isAnswered && !option.isCorrect && selectedOption === index" class="h-5 w-5 shrink-0 text-red-400" />
                    </button>
                </div>

                <div class="mt-8 flex justify-end border-t border-white/10 pt-6">
                    <button
                        v-if="!isAnswered"
                        type="button"
                        class="ui-primary-button disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="selectedOption === null"
                        @click="submitAnswer"
                    >
                        <span>تأكيد الإجابة</span>
                    </button>

                    <button v-else type="button" class="ui-secondary-button" @click="nextQuestion">
                        <span>{{ currentQuestionIndex < data.length - 1 ? 'التالي' : 'عرض النتيجة' }}</span>
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-else class="py-6 text-center">
                <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border border-white/10 bg-white/5">
                    <span class="text-3xl font-black text-white">{{ Math.round((score / data.length) * 100) }}%</span>
                </div>
                <h2 class="ui-public-title text-3xl">{{ score === data.length ? 'ممتاز!' : 'انتهى الاختبار' }}</h2>
                <p class="ui-public-copy mt-3 mb-8">أجبت بشكل صحيح على {{ score }} من {{ data.length }}.</p>
                <button type="button" class="ui-primary-button mx-auto" @click="resetQuiz">
                    <RefreshCw class="h-4 w-4" />
                    <span>إعادة المحاولة</span>
                </button>
            </div>
        </div>
    </section>
</template>
