<script setup>
import { computed, ref } from 'vue';
import { Camera, Download, FilePlus2, Paperclip, Trash2, Upload } from 'lucide-vue-next';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'المرفقات',
    },
    helperText: {
        type: String,
        default: '',
    },
    existingAttachments: {
        type: Array,
        default: () => [],
    },
    pendingFiles: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Array,
        default: () => [],
    },
    accept: {
        type: String,
        default: 'application/pdf,image/jpeg,image/png,image/webp,.doc,.docx,.xls,.xlsx',
    },
    cameraAccept: {
        type: String,
        default: 'image/*',
    },
    multiple: {
        type: Boolean,
        default: true,
    },
    allowCamera: {
        type: Boolean,
        default: false,
    },
    busy: {
        type: Boolean,
        default: false,
    },
    canDeleteExisting: {
        type: Boolean,
        default: true,
    },
    showUploader: {
        type: Boolean,
        default: true,
    },
    pendingTitle: {
        type: String,
        default: 'مرفقات سيتم رفعها عند الحفظ',
    },
    existingTitle: {
        type: String,
        default: 'المرفقات الحالية',
    },
    emptyText: {
        type: String,
        default: 'لا توجد مرفقات بعد.',
    },
});

const emit = defineEmits(['select-files', 'remove-pending', 'delete-existing']);

const cameraInputRef = ref(null);
const fileInputRef = ref(null);

const normalizedErrors = computed(() =>
    (props.errors || [])
        .filter((value) => typeof value === 'string' && value.trim() !== '')
        .map((value) => value.trim())
);

const hasPendingFiles = computed(() => (props.pendingFiles || []).length > 0);
const hasExistingAttachments = computed(() => (props.existingAttachments || []).length > 0);

const formatFileSize = (size) => {
    const bytes = Number(size || 0);

    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 بايت';
    }

    if (bytes < 1024) {
        return `${bytes} بايت`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`;
};

const formatDateTime = (value) => {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat('ar-EG', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch (_error) {
        return String(value);
    }
};

const emitSelectedFiles = (fileList) => {
    const files = Array.from(fileList || []).filter(Boolean);
    if (files.length === 0) return;

    emit('select-files', files);
};

const onCameraFilesSelected = (event) => {
    emitSelectedFiles(event?.target?.files);
    if (event?.target) event.target.value = '';
};

const onDeviceFilesSelected = (event) => {
    emitSelectedFiles(event?.target?.files);
    if (event?.target) event.target.value = '';
};

const openCameraPicker = () => {
    if (props.busy) return;
    cameraInputRef.value?.click?.();
};

const openFilePicker = () => {
    if (props.busy) return;
    fileInputRef.value?.click?.();
};
</script>

<template>
    <section dir="rtl" class="rounded-xl border border-gray-700/70 bg-gray-800/40 p-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h3 class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                    <Paperclip class="h-4 w-4 text-sky-300" />
                    <span>{{ title }}</span>
                </h3>
                <p v-if="helperText" class="mt-1 text-xs leading-6 text-gray-300">
                    {{ helperText }}
                </p>
            </div>

            <div v-if="showUploader" class="flex flex-wrap items-center gap-2">
                <button
                    v-if="allowCamera"
                    type="button"
                    class="inline-flex items-center gap-2 rounded bg-sky-700 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-sky-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="busy"
                    aria-label="التقاط صورة للمرفقات"
                    @click="openCameraPicker"
                >
                    <Camera class="h-3.5 w-3.5" />
                    <span>التقاط صورة</span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded bg-indigo-700 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="busy"
                    aria-label="اختيار ملفات للمرفقات"
                    @click="openFilePicker"
                >
                    <Upload class="h-3.5 w-3.5" />
                    <span>رفع مرفق</span>
                </button>
            </div>
        </div>

        <input
            v-if="showUploader && allowCamera"
            ref="cameraInputRef"
            type="file"
            :accept="cameraAccept"
            :multiple="multiple"
            capture="environment"
            class="hidden"
            @change="onCameraFilesSelected"
        />
        <input
            v-if="showUploader"
            ref="fileInputRef"
            type="file"
            :accept="accept"
            :multiple="multiple"
            class="hidden"
            @change="onDeviceFilesSelected"
        />

        <div v-if="normalizedErrors.length > 0" class="mt-3 space-y-2">
            <AppInlineAlert
                v-for="(errorMessage, index) in normalizedErrors"
                :key="`attachment-error-${index}`"
                variant="danger"
                :message="errorMessage"
            />
        </div>

        <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-2">
            <div class="rounded-lg border border-gray-700 bg-gray-900/80 p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-200">{{ pendingTitle }}</p>
                    <span class="text-[11px] text-gray-400">الحد الأقصى 10 ملفات، حتى 10 ميجابايت لكل ملف</span>
                </div>

                <div v-if="hasPendingFiles" class="space-y-2">
                    <div
                        v-for="(fileItem, fileIndex) in pendingFiles"
                        :key="`${fileItem.name}-${fileItem.size}-${fileIndex}`"
                        class="flex items-center justify-between gap-3 rounded border border-gray-700 bg-gray-950/80 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-gray-100">{{ fileItem.name }}</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ formatFileSize(fileItem.size) }}</p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-[11px] font-bold text-red-100 transition hover:bg-red-600"
                            aria-label="إزالة المرفق من القائمة"
                            @click="$emit('remove-pending', fileIndex)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>إزالة</span>
                        </button>
                    </div>
                </div>
                <div v-else class="rounded border border-dashed border-gray-700 px-3 py-4 text-center text-xs text-gray-400">
                    لم يتم اختيار ملفات بعد.
                </div>
            </div>

            <div class="rounded-lg border border-gray-700 bg-gray-900/80 p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-200">{{ existingTitle }}</p>
                    <span class="inline-flex items-center gap-1 text-[11px] text-gray-400">
                        <FilePlus2 class="h-3.5 w-3.5" />
                        <span>{{ existingAttachments.length }}</span>
                    </span>
                </div>

                <div v-if="hasExistingAttachments" class="space-y-2">
                    <div
                        v-for="attachment in existingAttachments"
                        :key="attachment.id"
                        class="flex items-center justify-between gap-3 rounded border border-gray-700 bg-gray-950/80 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <a
                                :href="attachment.download_url"
                                class="truncate text-xs font-semibold text-sky-300 transition hover:text-sky-200"
                            >
                                {{ attachment.file_name }}
                            </a>
                            <p class="mt-1 text-[11px] text-gray-400">
                                {{ formatFileSize(attachment.file_size) }}
                                <span v-if="attachment.mime_type"> • {{ attachment.mime_type }}</span>
                            </p>
                            <p
                                v-if="showUploader && (attachment.uploaded_by || attachment.uploaded_at)"
                                class="mt-1 text-[11px] text-gray-500"
                            >
                                <span v-if="attachment.uploaded_by">بواسطة {{ attachment.uploaded_by }}</span>
                                <span v-if="attachment.uploaded_by && attachment.uploaded_at"> • </span>
                                <span v-if="attachment.uploaded_at">{{ formatDateTime(attachment.uploaded_at) }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                :href="attachment.download_url"
                                class="inline-flex items-center gap-1 rounded bg-sky-700 px-2 py-1 text-[11px] font-bold text-white transition hover:bg-sky-600"
                                aria-label="تحميل المرفق"
                            >
                                <Download class="h-3.5 w-3.5" />
                                <span>تحميل</span>
                            </a>
                            <button
                                v-if="canDeleteExisting"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-[11px] font-bold text-red-100 transition hover:bg-red-600"
                                aria-label="حذف المرفق"
                                @click="$emit('delete-existing', attachment)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>حذف</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded border border-dashed border-gray-700 px-3 py-4 text-center text-xs text-gray-400">
                    {{ emptyText }}
                </div>
            </div>
        </div>
    </section>
</template>
