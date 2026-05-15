<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    maxWidthClass: { type: String, default: 'max-w-3xl' },
    panelClass: { type: String, default: '' },
    bodyClass: { type: String, default: '' },
    footerClass: { type: String, default: '' },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
    showCloseButton: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'after-open']);

const panelRef = ref(null);
const closeButtonRef = ref(null);
const lastFocusedElement = ref(null);
const previousBodyOverflow = ref('');
const previousRootOverflow = ref('');
const titleId = `app-modal-title-${Math.random().toString(36).slice(2, 10)}`;
const descriptionId = `app-modal-description-${Math.random().toString(36).slice(2, 10)}`;

const hasHeader = computed(() => props.title !== '' || props.description !== '' || props.showCloseButton);
const getFocusableElements = () => {
    if (!panelRef.value) return [];

    return [...panelRef.value.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )].filter((element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');
};

const focusFirstElement = async () => {
    await nextTick();

    if (!props.open) return;

    const focusable = getFocusableElements();
    const target = closeButtonRef.value || focusable[0] || panelRef.value;
    target?.focus?.();
    emit('after-open');
};

const restoreFocus = () => {
    const target = lastFocusedElement.value;
    if (target && typeof target.focus === 'function') {
        nextTick(() => target.focus());
    }
};

const requestClose = () => {
    emit('close');
};

const handleKeydown = (event) => {
    if (!props.open) return;

    if (event.key === 'Escape' && props.closeOnEscape) {
        event.preventDefault();
        requestClose();
        return;
    }

    if (event.key !== 'Tab') return;

    const focusable = getFocusableElements();
    if (focusable.length === 0) {
        event.preventDefault();
        panelRef.value?.focus?.();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
};

watch(
    () => props.open,
    async (open) => {
        if (typeof document === 'undefined') return;

        if (open) {
            lastFocusedElement.value = document.activeElement;
            previousBodyOverflow.value = document.body.style.overflow;
            previousRootOverflow.value = document.documentElement.style.overflow;
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            document.addEventListener('keydown', handleKeydown);
            await focusFirstElement();
            return;
        }

        document.body.style.overflow = previousBodyOverflow.value;
        document.documentElement.style.overflow = previousRootOverflow.value;
        document.removeEventListener('keydown', handleKeydown);
        restoreFocus();
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value;
        document.documentElement.style.overflow = previousRootOverflow.value;
        document.removeEventListener('keydown', handleKeydown);
    }
});
</script>

<template>
    <teleport to="body">
        <div
            v-if="open"
            class="ui-theme-modal-backdrop fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-4"
            dir="rtl"
            @click.self="closeOnBackdrop ? requestClose() : null"
        >
            <div
                ref="panelRef"
                class="ui-theme-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl sm:max-h-[94vh]"
                :class="[maxWidthClass, panelClass]"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="title ? titleId : undefined"
                :aria-describedby="description ? descriptionId : undefined"
                tabindex="-1"
            >
                <div
                    v-if="hasHeader"
                    class="ui-theme-modal-header flex items-start justify-between gap-3 border-b border-white/10 px-4 py-4 sm:px-6"
                >
                    <div class="min-w-0 text-right">
                        <h3 v-if="title" :id="titleId" class="text-base font-black text-white sm:text-xl">{{ title }}</h3>
                        <p v-if="description" :id="descriptionId" class="mt-1 text-xs leading-6 text-slate-400 sm:text-sm">{{ description }}</p>
                    </div>
                    <slot name="header-actions" />
                    <button
                        v-if="showCloseButton"
                        ref="closeButtonRef"
                        type="button"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                        aria-label="إغلاق النافذة"
                        @click="requestClose"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="ui-theme-modal-body min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-6" :class="bodyClass">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="ui-theme-modal-footer flex flex-col-reverse gap-3 border-t border-white/10 bg-slate-950/95 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    :class="footerClass"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </teleport>
</template>
