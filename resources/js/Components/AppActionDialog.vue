<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { AlertTriangle, CheckCircle2, Info, ShieldAlert, X } from 'lucide-vue-next';
import { useActionDialogState } from '@/composables/useActionDialog';

const { state, cancel, submit } = useActionDialogState();
const inputRef = ref(null);
const previousBodyOverflow = ref('');
const previousRootOverflow = ref('');

const variantMeta = computed(() => {
    if (state.variant === 'danger') {
        return {
            icon: ShieldAlert,
            iconClass: 'app-action-dialog__icon app-action-dialog__icon--danger',
            confirmClass: 'app-action-dialog__confirm app-action-dialog__confirm--danger',
        };
    }

    if (state.variant === 'warning') {
        return {
            icon: AlertTriangle,
            iconClass: 'app-action-dialog__icon app-action-dialog__icon--warning',
            confirmClass: 'app-action-dialog__confirm app-action-dialog__confirm--warning',
        };
    }

    if (state.variant === 'success') {
        return {
            icon: CheckCircle2,
            iconClass: 'app-action-dialog__icon app-action-dialog__icon--success',
            confirmClass: 'app-action-dialog__confirm app-action-dialog__confirm--success',
        };
    }

    return {
        icon: Info,
        iconClass: 'app-action-dialog__icon app-action-dialog__icon--info',
        confirmClass: 'app-action-dialog__confirm app-action-dialog__confirm--info',
    };
});

watch(
    () => state.open,
    async (isOpen) => {
        if (typeof document !== 'undefined') {
            if (isOpen) {
                previousBodyOverflow.value = document.body.style.overflow;
                previousRootOverflow.value = document.documentElement.style.overflow;
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = previousBodyOverflow.value;
                document.documentElement.style.overflow = previousRootOverflow.value;
            }
        }

        if (!isOpen || state.kind !== 'prompt') {
            return;
        }

        await nextTick();
        inputRef.value?.focus?.();
    }
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value;
        document.documentElement.style.overflow = previousRootOverflow.value;
    }
});
</script>

<template>
    <teleport to="body">
        <div
            v-if="state.open"
            class="app-action-dialog fixed inset-0 z-[220] flex items-center justify-center p-4"
            dir="rtl"
            @click.self="cancel"
        >
            <div class="app-action-dialog__backdrop absolute inset-0" />

            <div class="app-action-dialog__panel relative w-full max-w-lg overflow-hidden rounded-2xl border shadow-2xl">
                <div class="app-action-dialog__header flex items-start justify-between gap-3 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <component :is="variantMeta.icon" :class="variantMeta.iconClass" />
                        <div>
                            <h3 class="text-lg font-bold">{{ state.title }}</h3>
                            <p v-if="state.message" class="mt-1 text-sm leading-7 app-action-dialog__message">
                                {{ state.message }}
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="state.kind !== 'alert'"
                        type="button"
                        class="app-action-dialog__close inline-flex h-9 w-9 items-center justify-center rounded-xl border transition"
                        aria-label="إغلاق نافذة التأكيد"
                        @click="cancel"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="state.kind === 'prompt'" class="px-5 pb-4">
                    <label v-if="state.inputLabel" class="mb-2 block text-sm font-semibold app-action-dialog__label">
                        {{ state.inputLabel }}
                    </label>

                    <textarea
                        v-if="state.inputMultiline"
                        ref="inputRef"
                        v-model="state.inputValue"
                        rows="4"
                        class="app-action-dialog__field w-full rounded-xl border px-4 py-3 text-sm outline-none transition"
                        :placeholder="state.inputPlaceholder"
                    />

                    <input
                        v-else
                        ref="inputRef"
                        v-model="state.inputValue"
                        type="text"
                        class="app-action-dialog__field w-full rounded-xl border px-4 py-3 text-sm outline-none transition"
                        :placeholder="state.inputPlaceholder"
                        @keyup.enter="submit"
                    />

                    <p v-if="state.errorMessage" class="mt-2 text-sm app-action-dialog__error">
                        {{ state.errorMessage }}
                    </p>
                </div>

                <div class="app-action-dialog__footer flex flex-wrap items-center justify-end gap-3 px-5 pb-5">
                    <button
                        v-if="state.kind !== 'alert'"
                        type="button"
                        class="app-action-dialog__cancel rounded-xl border px-4 py-2 text-sm font-semibold transition"
                        @click="cancel"
                    >
                        {{ state.cancelText }}
                    </button>

                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition"
                        :class="variantMeta.confirmClass"
                        @click="submit"
                    >
                        {{ state.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
