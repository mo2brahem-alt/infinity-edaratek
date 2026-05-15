<script setup>
import { computed } from 'vue';
import { LoaderCircle, Search, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'ابحث هنا',
    },
    ariaLabel: {
        type: String,
        default: 'حقل البحث',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    clearable: {
        type: Boolean,
        default: true,
    },
    inputClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const hasValue = computed(() => String(props.modelValue || '').trim() !== '');

const updateValue = (event) => {
    emit('update:modelValue', event.target.value);
};

const clearValue = () => {
    emit('update:modelValue', '');
    emit('clear');
};
</script>

<template>
    <div class="ui-search-shell" dir="rtl">
        <Search class="ui-search-icon" />
        <input
            :value="modelValue"
            type="text"
            class="ui-search-control"
            :class="inputClass"
            :placeholder="placeholder"
            :aria-label="ariaLabel"
            @input="updateValue"
        />

        <button
            v-if="clearable && hasValue && !loading"
            type="button"
            class="ui-search-action"
            aria-label="مسح البحث"
            @click="clearValue"
        >
            <X class="h-4 w-4" />
        </button>

        <span v-else-if="loading" class="ui-search-action pointer-events-none" aria-hidden="true">
            <LoaderCircle class="h-4 w-4 animate-spin" />
        </span>
    </div>
</template>
