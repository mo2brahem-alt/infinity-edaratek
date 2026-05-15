<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Camera, CheckCircle, Lock, Mail, Phone, Save, ShieldCheck, User } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RoleLayout from '@/Layouts/RoleLayout.vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
});

const user = usePage().props.auth.user;
const photoPreview = ref(null);
const photoInput = ref(null);

const normalizedRole = computed(() => String(user?.primary_role || user?.role || '').toLowerCase());
const isSuperAdmin = computed(() => normalizedRole.value === 'super_admin');

const roleLayoutRole = computed(() => {
    if (normalizedRole.value === 'supervisor') return 'SUPERVISOR';
    if (normalizedRole.value === 'school_manager') return 'SCHOOL_MANAGER';
    if (normalizedRole.value === 'staff') return 'STAFF';
    return 'USER';
});

const layoutComponent = computed(() => (isSuperAdmin.value ? AdminLayout : RoleLayout));
const layoutProps = computed(() => (
    isSuperAdmin.value
        ? {}
        : { title: 'الملف الشخصي', role: roleLayoutRole.value }
));

const currentPhotoUrl = computed(() => {
    if (photoPreview.value) {
        return photoPreview.value;
    }

    const path = String(user?.profile_photo_path || '').trim();
    if (path === '') {
        return null;
    }

    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
        return path;
    }

    return `/media-files/${path}`;
});

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    photo: null,
    _method: 'PATCH',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const selectNewPhoto = () => {
    photoInput.value?.click?.();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value?.files?.[0];
    if (!photo) {
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        photoPreview.value = event?.target?.result || null;
    };
    reader.readAsDataURL(photo);
    form.photo = photo;
};

const updateProfileInformation = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
        },
    });
};

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <Head title="الملف الشخصي" />

    <component :is="layoutComponent" v-bind="layoutProps">
        <div class="ui-page-shell max-w-6xl">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker">
                            <ShieldCheck class="h-4 w-4" />
                            <span>مركز الحساب</span>
                        </span>
                        <h1 class="ui-page-title">إعدادات الحساب</h1>
                        <p class="ui-page-copy">
                            حدّث بيانات الحساب، البريد، رقم الجوال، والصورة الشخصية من مكان واحد بتجربة أوضح وأكثر اتساقًا.
                        </p>
                    </div>

                    <div class="ui-card-soft flex items-center gap-4 px-4 py-3 text-right">
                        <div class="ui-avatar h-14 w-14 text-lg">
                            <img v-if="currentPhotoUrl" :src="currentPhotoUrl" alt="الصورة الشخصية" class="h-full w-full rounded-full object-cover" />
                            <span v-else>{{ user.name.charAt(0) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-black text-white">{{ user.name }}</p>
                            <p class="truncate text-sm text-slate-400">{{ user.email }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="status" class="ui-inline-alert ui-inline-alert--success mt-6 text-right">
                    {{ status }}
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <section class="ui-form-shell">
                    <div class="ui-section-header">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">المعلومات الشخصية</h2>
                            <p class="ui-section-subtitle">بيانات الحساب الأساسية التي تظهر داخل النظام وتستخدم في التواصل.</p>
                        </div>
                    </div>

                    <form class="space-y-6" @submit.prevent="updateProfileInformation">
                        <div class="ui-card-soft flex flex-col items-center gap-4 p-5 text-center">
                            <div class="relative">
                                <div class="h-28 w-28 overflow-hidden rounded-full border border-white/10 bg-slate-900/60">
                                    <img v-if="currentPhotoUrl" :src="currentPhotoUrl" alt="معاينة الصورة الشخصية" class="h-full w-full object-cover" />
                                    <div v-else class="flex h-full w-full items-center justify-center text-3xl font-black text-white">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                </div>
                                <div class="absolute inset-x-0 -bottom-2 flex justify-center">
                                    <button
                                        type="button"
                                        class="ui-icon-button h-11 w-11 rounded-full"
                                        aria-label="اختيار صورة شخصية جديدة"
                                        @click="selectNewPhoto"
                                    >
                                        <Camera class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1 text-center">
                                <p class="text-sm font-bold text-white">الصورة الشخصية</p>
                                <p class="ui-helper-text">ارفع صورة واضحة بصيغة JPG أو PNG بحجم مناسب.</p>
                            </div>

                            <button
                                type="button"
                                class="ui-secondary-button"
                                aria-controls="profile-photo-input"
                                @click="selectNewPhoto"
                            >
                                <Camera class="h-4 w-4" />
                                <span>اختيار صورة جديدة</span>
                            </button>

                            <input
                                id="profile-photo-input"
                                ref="photoInput"
                                type="file"
                                class="hidden"
                                accept=".jpg,.jpeg,.png"
                                @change="updatePhotoPreview"
                            />

                            <p v-if="form.errors.photo" class="ui-field-error">{{ form.errors.photo }}</p>
                        </div>

                        <div class="ui-form-grid">
                            <div class="space-y-2">
                                <label for="profile-name" class="ui-field-label">الاسم الكامل</label>
                                <div class="relative">
                                    <input id="profile-name" v-model="form.name" name="name" data-field-label="الاسم الكامل" type="text" maxlength="255" autocomplete="name" class="ui-input pr-11" required />
                                    <User class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                </div>
                                <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-2">
                                <label for="profile-email" class="ui-field-label">البريد الإلكتروني</label>
                                <div class="relative">
                                    <input id="profile-email" v-model="form.email" name="email" data-field-label="البريد الإلكتروني" type="email" maxlength="255" autocomplete="email" class="ui-input pr-11" required />
                                    <Mail class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                </div>
                                <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="profile-phone" class="ui-field-label">رقم الجوال</label>
                            <div class="relative">
                                <input id="profile-phone" v-model="form.phone" type="text" inputmode="tel" autocomplete="tel" placeholder="05xxxxxxxx أو +9665xxxxxxxx" class="ui-input pr-11" />
                                <Phone class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            </div>
                            <p class="ui-helper-text">يمكنك إدخال الرقم المحلي أو الدولي، وسيتم التحقق منه تلقائيًا.</p>
                            <p v-if="form.errors.phone" class="ui-field-error">{{ form.errors.phone }}</p>
                        </div>

                        <div class="flex flex-col items-stretch gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <div v-if="form.recentlySuccessful" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-300">
                                <CheckCircle class="h-4 w-4" />
                                <span>تم حفظ التغييرات بنجاح.</span>
                            </div>
                            <button :disabled="form.processing" type="submit" class="ui-primary-button min-w-[12rem] self-end">
                                <Save class="h-4 w-4" />
                                <span>{{ form.processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}</span>
                            </button>
                        </div>
                    </form>
                </section>

                <section class="ui-form-shell">
                    <div class="ui-section-header">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">الأمان وكلمة المرور</h2>
                            <p class="ui-section-subtitle">حدّث كلمة المرور من هذا القسم للحفاظ على أمان الحساب والوصول.</p>
                        </div>
                    </div>

                    <form class="space-y-5" @submit.prevent="updatePassword">
                        <div class="space-y-2">
                            <label for="current-password" class="ui-field-label">كلمة المرور الحالية</label>
                            <input id="current-password" v-model="passwordForm.current_password" name="current_password" data-field-label="كلمة المرور الحالية" type="password" autocomplete="current-password" class="ui-input" />
                            <p v-if="passwordForm.errors.current_password" class="ui-field-error">{{ passwordForm.errors.current_password }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="new-password" class="ui-field-label">كلمة المرور الجديدة</label>
                            <input id="new-password" v-model="passwordForm.password" name="password" data-field-label="كلمة المرور الجديدة" type="password" autocomplete="new-password" class="ui-input" />
                            <p v-if="passwordForm.errors.password" class="ui-field-error">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="password-confirmation" class="ui-field-label">تأكيد كلمة المرور</label>
                            <input id="password-confirmation" v-model="passwordForm.password_confirmation" name="password_confirmation" data-field-label="تأكيد كلمة المرور" type="password" autocomplete="new-password" class="ui-input" />
                        </div>

                        <div v-if="mustVerifyEmail" class="ui-inline-alert ui-inline-alert--info text-right">
                            عند تغيير البريد الإلكتروني قد تحتاج إلى إعادة التحقق من البريد قبل تفعيل بعض الإجراءات الحساسة.
                        </div>

                        <div class="flex flex-col items-stretch gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <div v-if="passwordForm.recentlySuccessful" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-300">
                                <CheckCircle class="h-4 w-4" />
                                <span>تم تحديث كلمة المرور بنجاح.</span>
                            </div>
                            <button :disabled="passwordForm.processing" type="submit" class="ui-primary-button min-w-[12rem] self-end">
                                <Lock class="h-4 w-4" />
                                <span>{{ passwordForm.processing ? 'جارٍ التحديث...' : 'تحديث كلمة المرور' }}</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </component>
</template>
