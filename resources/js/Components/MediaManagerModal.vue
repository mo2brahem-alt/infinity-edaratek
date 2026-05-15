<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { X, Upload, Trash2, Check, Image as ImageIcon, Video as VideoIcon } from 'lucide-vue-next';
import axios from 'axios';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    show: Boolean,
    allowedType: { type: String, default: 'all' },
});

const emit = defineEmits(['close', 'select']);

const actionDialog = useActionDialog();
const activeTab = ref('library');
const mediaList = ref([]);
const selectedMedia = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref('');
const previousBodyOverflow = ref('');
const previousRootOverflow = ref('');

const fetchMedia = async () => {
    const res = await axios.get(route('admin.media.index'));
    mediaList.value = res.data;
};

const filteredMedia = computed(() => {
    if (props.allowedType === 'all') return mediaList.value;
    return mediaList.value.filter((media) => media.file_type === props.allowedType);
});

const uploadFile = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    uploadError.value = '';
    const formData = new FormData();
    formData.append('file', file);

    isUploading.value = true;

    try {
        const res = await axios.post(route('admin.media.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            },
        });

        mediaList.value.unshift(res.data);
        activeTab.value = 'library';
        selectedMedia.value = res.data;
    } catch (error) {
        uploadError.value = error?.response?.data?.message || 'تعذر رفع الملف. حاول مرة أخرى.';
        await actionDialog.alert({
            title: 'تعذر رفع الملف',
            message: uploadError.value,
            confirmText: 'حسنًا',
            variant: 'danger',
        });
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
        if (event?.target) {
            event.target.value = '';
        }
    }
};

const deleteMedia = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الملف',
        message: 'سيتم حذف هذا الملف نهائيًا من مكتبة الوسائط. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف الملف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    await axios.delete(route('admin.media.destroy', id));
    mediaList.value = mediaList.value.filter((media) => media.id !== id);

    if (selectedMedia.value?.id === id) {
        selectedMedia.value = null;
    }
};

const confirmSelection = () => {
    if (!selectedMedia.value) return;

    emit('select', selectedMedia.value);
    emit('close');
};

onMounted(() => {
    fetchMedia();
});

watch(
    () => props.show,
    (show) => {
        if (typeof document === 'undefined') return;

        if (show) {
            previousBodyOverflow.value = document.body.style.overflow;
            previousRootOverflow.value = document.documentElement.style.overflow;
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            return;
        }

        document.body.style.overflow = previousBodyOverflow.value;
        document.documentElement.style.overflow = previousRootOverflow.value;
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = previousBodyOverflow.value;
        document.documentElement.style.overflow = previousRootOverflow.value;
    }
});
</script>

<template>
    <div v-if="show" class="ui-theme-modal-backdrop fixed inset-0 z-[100] flex items-center justify-center p-4" dir="rtl">
        <div
            class="ui-theme-modal-panel flex min-h-[70dvh] max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-gray-900 shadow-2xl sm:h-[80vh] sm:min-h-0 sm:max-h-[80vh]"
            role="dialog"
            aria-modal="true"
            aria-label="مدير الوسائط"
        >
            <div class="ui-theme-modal-header flex items-center justify-between border-b border-white/10 bg-gray-800 px-4 py-3 sm:p-4">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white">
                    <ImageIcon class="h-5 w-5 text-blue-400" />
                    <span>مدير الوسائط</span>
                </h3>

                <button type="button" aria-label="إغلاق مدير الوسائط" class="text-gray-400 transition hover:text-white" @click="$emit('close')">
                    <X class="h-6 w-6" />
                </button>
            </div>

            <div class="flex border-b border-white/10 bg-gray-800/50">
                <button
                    type="button"
                    class="flex-1 px-3 py-3 text-sm font-bold text-gray-400 transition hover:text-white"
                    :class="{ 'border-b-2 border-blue-500 text-blue-400': activeTab === 'library' }"
                    @click="activeTab = 'library'"
                >
                    مكتبة الوسائط
                </button>
                <button
                    type="button"
                    class="flex-1 px-3 py-3 text-sm font-bold text-gray-400 transition hover:text-white"
                    :class="{ 'border-b-2 border-blue-500 text-blue-400': activeTab === 'upload' }"
                    @click="activeTab = 'upload'"
                >
                    رفع ملف جديد
                </button>
            </div>

            <div class="ui-theme-modal-body relative flex-1 overflow-y-auto bg-gray-900 p-4 sm:p-6">
                <div v-if="activeTab === 'upload'" class="flex h-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-700 bg-gray-800/30 transition hover:bg-gray-800/50">
                    <div v-if="isUploading" class="w-64 text-center">
                        <div class="mb-2 font-bold text-blue-400">جارٍ الرفع... {{ uploadProgress }}%</div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-700">
                            <div class="h-full bg-blue-500 transition-all duration-300" :style="`width: ${uploadProgress}%`" />
                        </div>
                    </div>

                    <div v-else class="text-center">
                        <Upload class="mx-auto mb-4 h-16 w-16 text-gray-500" />
                        <label class="inline-block cursor-pointer rounded-lg bg-blue-600 px-6 py-3 font-bold text-white transition hover:bg-blue-700">
                            اختر ملفًا من جهازك
                            <input
                                type="file"
                                class="hidden"
                                accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime,video/ogg"
                                @change="uploadFile"
                            />
                        </label>
                        <p class="mt-4 text-sm text-gray-500">صور (JPG, PNG, WebP, GIF, SVG) أو فيديو (MP4, WebM, MOV, OGG)</p>
                        <p v-if="uploadError" class="mt-3 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                            {{ uploadError }}
                        </p>
                    </div>
                </div>

                <div v-if="activeTab === 'library'">
                    <div v-if="filteredMedia.length === 0" class="py-20 text-center text-gray-500">
                        لا توجد ملفات، قم برفع ملف جديد.
                    </div>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 md:grid-cols-6">
                        <div
                            v-for="media in filteredMedia"
                            :key="media.id"
                            class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg border border-white/5 bg-gray-800 transition hover:border-white/20"
                            :class="{ 'scale-95 ring-4 ring-blue-500': selectedMedia?.id === media.id }"
                            @click="selectedMedia = media"
                        >
                            <img v-if="media.file_type === 'image'" :src="media.url" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center bg-gray-900 text-gray-500">
                                <VideoIcon class="h-10 w-10" />
                            </div>

                            <button
                                type="button"
                                aria-label="حذف الملف"
                                class="absolute left-2 top-2 rounded-md bg-red-600 p-1.5 text-white opacity-100 shadow-lg transition hover:bg-red-700 sm:opacity-0 sm:group-hover:opacity-100"
                                @click.stop="deleteMedia(media.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>

                            <div v-if="selectedMedia?.id === media.id" class="absolute right-2 top-2 rounded-full bg-blue-600 p-1 text-white shadow-lg">
                                <Check class="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ui-theme-modal-footer flex flex-col gap-3 border-t border-white/10 bg-gray-800 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-400">
                    <span v-if="selectedMedia">
                        تم اختيار: <span class="font-bold text-white">{{ selectedMedia.file_name }}</span>
                    </span>
                    <span v-else>لم يتم اختيار أي ملف</span>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-8 py-2.5 font-bold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                    :disabled="!selectedMedia"
                    @click="confirmSelection"
                >
                    <Check class="h-4 w-4" />
                    <span>استخدام الملف</span>
                </button>
            </div>
        </div>
    </div>
</template>
