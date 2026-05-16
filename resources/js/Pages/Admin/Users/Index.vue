<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    Check,
    Clock3,
    Mail,
    Pencil,
    Phone,
    Plus,
    School,
    Shield,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppFilterBar from '@/Components/AppFilterBar.vue';
import AppSearchField from '@/Components/AppSearchField.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    users: {
        type: Array,
        default: () => [],
    },
    pendingApprovals: {
        type: Array,
        default: () => [],
    },
    approvalStats: {
        type: Object,
        default: () => ({ pending: 0, approved: 0, rejected: 0 }),
    },
    roles: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    schools: {
        type: Array,
        default: () => [],
    },
});

const actionDialog = useActionDialog();

const isModalOpen = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const search = ref('');
const selectedRole = ref('all');
const selectedDepartment = ref('all');

const form = useForm({
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
    role_name: '',
    department_id: '',
});

const deleteForm = useForm({});
const approveForm = useForm({});
const rejectForm = useForm({});

const userInitial = (name) => String(name || '').trim().charAt(0).toUpperCase() || 'U';

const roleNameForUser = (user) => user?.roles?.[0]?.name || '';
const departmentNameForUser = (user) => user?.department?.name || 'غير محدد';

const approvalRoleLabel = (user) => roleNameForUser(user) || user?.role || 'غير محدد';
const approvalStatusLabel = (status) => {
    if (status === 'pending_approval') return 'قيد المراجعة';
    if (status === 'approved') return 'تمت الموافقة';
    if (status === 'rejected') return 'مرفوض';

    return 'غير معروف';
};

const formatDate = (value) => {
    if (!value) return '-';

    return new Date(value).toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const schoolTypeLabel = (type) => {
    if (type === 'boys') return 'بنين';
    if (type === 'girls') return 'بنات';
    if (type === 'mixed') return 'مختلطة';

    return 'غير محدد';
};

const schoolStatusMeta = (status) => {
    if (status === 'ACTIVE') {
        return {
            label: 'مفعلة',
            className: 'border-emerald-400/25 bg-emerald-400/10 text-emerald-200',
        };
    }

    if (status === 'SUSPENDED') {
        return {
            label: 'معلقة',
            className: 'border-amber-400/25 bg-amber-400/10 text-amber-200',
        };
    }

    return {
        label: status || 'غير محددة',
        className: 'border-slate-500/25 bg-slate-500/10 text-slate-300',
    };
};

const supervisionStatusLabel = (status) => {
    if (status === 'ACTIVE_ASSOCIATION') return 'ارتباط نشط';
    if (status === 'WAITING_SUPERVISOR_CONFIRM') return 'بانتظار تأكيد المشرف';
    if (status === 'WAITING_MANAGER_APPROVAL') return 'بانتظار موافقة المدير';
    if (status === 'SUSPENDED') return 'غير مرتبط';

    return status || 'غير محددة';
};

const schoolLocationLabel = (school) => {
    const parts = [
        school?.directorate?.country,
        school?.directorate?.governorate,
        school?.directorate?.name,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' / ') : 'غير محدد';
};

const activeSchoolsCount = computed(() => props.schools.filter((school) => school.status === 'ACTIVE').length);
const linkedSchoolsCount = computed(() => props.schools.filter((school) => school.manager).length);
const schoolUsersCount = computed(() => props.schools.reduce((total, school) => total + Number(school.users_count || 0), 0));

const filteredUsers = computed(() => {
    const normalizedSearch = search.value.trim().toLowerCase();

    return props.users.filter((user) => {
        const roleName = String(roleNameForUser(user) || '');
        const departmentId = String(user.department_id || '');
        const haystack = [
            user.name,
            user.email,
            user.mobile,
            roleName,
            departmentNameForUser(user),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        if (normalizedSearch !== '' && !haystack.includes(normalizedSearch)) {
            return false;
        }

        if (selectedRole.value !== 'all' && roleName !== selectedRole.value) {
            return false;
        }

        if (selectedDepartment.value !== 'all' && departmentId !== selectedDepartment.value) {
            return false;
        }

        return true;
    });
});

const activeFilterCount = computed(() =>
    [search.value.trim() !== '', selectedRole.value !== 'all', selectedDepartment.value !== 'all']
        .filter(Boolean)
        .length
);

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    form.clearErrors();
    form.role_name = props.roles[0]?.name || '';
    form.department_id = props.departments[0]?.id ? String(props.departments[0].id) : '';
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    editId.value = user.id;

    form.name = user.name || '';
    form.email = user.email || '';
    form.mobile = user.mobile || '';
    form.password = '';
    form.password_confirmation = '';
    form.role_name = roleNameForUser(user);
    form.department_id = user.department_id ? String(user.department_id) : '';
    form.clearErrors();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.clearErrors();
};

const clearFilters = () => {
    search.value = '';
    selectedRole.value = 'all';
    selectedDepartment.value = 'all';
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('users.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const deleteUser = async (user) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف المستخدم',
        message: `سيتم حذف المستخدم "${user.name}" نهائيًا من لوحة الإدارة. هل تريد المتابعة؟`,
        confirmText: 'نعم، احذف المستخدم',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    deleteForm.delete(route('users.destroy', user.id), {
        preserveScroll: true,
    });
};

const approvePendingUser = async (user) => {
    const confirmed = await actionDialog.confirm({
        title: 'الموافقة على الحساب',
        message: `سيتم تفعيل حساب "${user.name}" والسماح له بتسجيل الدخول واستخدام المنصة. هل تريد المتابعة؟`,
        confirmText: 'نعم، موافقة',
        cancelText: 'إلغاء',
        variant: 'success',
    });

    if (!confirmed) {
        return;
    }

    approveForm.post(route('users.approve', user.id), {
        preserveScroll: true,
    });
};

const rejectPendingUser = async (user) => {
    const confirmed = await actionDialog.confirm({
        title: 'رفض طلب الحساب',
        message: `سيبقى حساب "${user.name}" غير مفعّل بعد رفض الطلب. هل تريد المتابعة؟`,
        confirmText: 'نعم، رفض الطلب',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    rejectForm.post(route('users.reject', user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="إدارة الحسابات" />

    <AdminLayout>
        <div class="ui-page-shell">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker">
                            <Users class="h-4 w-4" />
                            <span>إدارة حسابات المنصة</span>
                        </span>
                        <h1 class="ui-page-title">إدارة الحسابات</h1>
                        <p class="ui-page-copy">
                            راجع حسابات المستخدمين والمدارس المرتبطة بها من مكان واحد، مع الحفاظ على أدوات الاعتماد والبحث والتعديل الحالية.
                        </p>
                    </div>

                    <button type="button" class="ui-primary-button self-end" @click="openCreateModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة مستخدم</span>
                    </button>
                </div>

                <div class="ui-stat-grid mt-6">
                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">إجمالي المستخدمين</p>
                                <h2 class="ui-stat-value">{{ filteredUsers.length }}</h2>
                            </div>
                            <div class="ui-stat-icon"><Users class="h-5 w-5" /></div>
                        </div>
                        <p class="text-sm leading-7 text-slate-400">من أصل {{ users.length }} مستخدم في لوحة الإدارة.</p>
                    </article>

                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">المدارس المفعلة</p>
                                <h2 class="ui-stat-value">{{ activeSchoolsCount }}</h2>
                            </div>
                            <div class="ui-stat-icon"><School class="h-5 w-5" /></div>
                        </div>
                        <p class="text-sm leading-7 text-slate-400">من أصل {{ schools.length }} مدرسة مسجلة داخل النظام.</p>
                    </article>

                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">مدارس لها مدير</p>
                                <h2 class="ui-stat-value">{{ linkedSchoolsCount }}</h2>
                            </div>
                            <div class="ui-stat-icon"><Building2 class="h-5 w-5" /></div>
                        </div>
                        <p class="text-sm leading-7 text-slate-400">إجمالي المستخدمين المرتبطين بالمدارس: {{ schoolUsersCount }}.</p>
                    </article>
                </div>
            </section>

            <section class="ui-section-header">
                <div class="ui-section-heading text-right">
                    <h2 class="ui-section-title">المستخدمون</h2>
                    <p class="ui-section-subtitle">
                        هذا القسم يحتفظ بتجربة المستخدمين الحالية: الاعتماد، البحث، الفلترة، الإضافة، التعديل والحذف.
                    </p>
                </div>
            </section>

            <section class="ui-table-shell">
                <div class="ui-table-header">
                    <div class="ui-section-header !mb-0">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">طلبات الانضمام المعلقة</h2>
                            <p class="ui-section-subtitle">
                                راجع حسابات مديري المدارس والمشرفين قبل السماح لهم بتسجيل الدخول واستخدام المنصة.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="ui-stat-grid mt-6">
                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">قيد المراجعة</p>
                                <h2 class="ui-stat-value">{{ approvalStats.pending || 0 }}</h2>
                            </div>
                            <div class="ui-stat-icon"><Clock3 class="h-5 w-5" /></div>
                        </div>
                    </article>

                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">تمت الموافقة</p>
                                <h2 class="ui-stat-value">{{ approvalStats.approved || 0 }}</h2>
                            </div>
                            <div class="ui-stat-icon"><Check class="h-5 w-5" /></div>
                        </div>
                    </article>

                    <article class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">مرفوض</p>
                                <h2 class="ui-stat-value">{{ approvalStats.rejected || 0 }}</h2>
                            </div>
                            <div class="ui-stat-icon"><Ban class="h-5 w-5" /></div>
                        </div>
                    </article>
                </div>

                <div v-if="pendingApprovals.length === 0" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="empty"
                        title="لا توجد طلبات انضمام معلقة حاليًا"
                        description="عند تسجيل مشرف أو مدير مدرسة جديد سيظهر هنا بانتظار موافقة السوبر أدمن."
                        compact
                    />
                </div>

                <div v-else class="grid gap-4 p-4 md:grid-cols-2 md:p-6">
                    <article v-for="user in pendingApprovals" :key="user.id" class="ui-mobile-row-card text-right">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="ui-avatar shrink-0">{{ userInitial(user.name) }}</div>
                                <div class="min-w-0">
                                    <p class="truncate text-base font-black text-white">{{ user.name }}</p>
                                    <p class="truncate text-xs text-slate-400" dir="ltr">{{ user.email }}</p>
                                </div>
                            </div>

                            <span class="ui-chip">{{ approvalStatusLabel(user.approval_status) }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <p class="ui-mobile-row-label">نوع الحساب</p>
                                <p class="mt-1 text-sm text-slate-300">{{ approvalRoleLabel(user) }}</p>
                            </div>
                            <div>
                                <p class="ui-mobile-row-label">رقم الجوال</p>
                                <p class="mt-1 text-sm text-slate-300" dir="ltr">{{ user.mobile || 'غير مضاف' }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="ui-mobile-row-label">تاريخ التسجيل</p>
                                <p class="mt-1 text-sm text-slate-300">
                                    {{ user.created_at ? new Date(user.created_at).toLocaleString('ar-EG') : '-' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="ui-secondary-button"
                                :disabled="rejectForm.processing"
                                @click="rejectPendingUser(user)"
                            >
                                رفض
                            </button>
                            <button
                                type="button"
                                class="ui-primary-button"
                                :disabled="approveForm.processing"
                                @click="approvePendingUser(user)"
                            >
                                موافقة
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <AppFilterBar
                title="الفلاتر والبحث"
                description="ابحث في الحسابات الإدارية وصَفِّ النتائج حسب الدور أو الإدارة من شريط موحّد وواضح."
            >
                <template #meta>
                    <span class="ui-chip">النتائج: {{ filteredUsers.length }}</span>
                    <span v-if="activeFilterCount > 0" class="ui-chip">فلاتر مفعلة: {{ activeFilterCount }}</span>
                </template>

                <div class="ui-filter-row">
                    <AppSearchField
                        v-model="search"
                        class="flex-1"
                        placeholder="ابحث بالاسم أو البريد أو الجوال أو الإدارة"
                        aria-label="بحث في المستخدمين"
                    />

                    <select v-model="selectedRole" class="ui-select md:max-w-[14rem]" aria-label="فلترة حسب الدور">
                        <option value="all">كل الأدوار</option>
                        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                    </select>

                    <select v-model="selectedDepartment" class="ui-select md:max-w-[14rem]" aria-label="فلترة حسب الإدارة">
                        <option value="all">كل الإدارات</option>
                        <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                            {{ department.name }}
                        </option>
                    </select>
                </div>

                <template #footer>
                    <button type="button" class="ui-ghost-button" :disabled="activeFilterCount === 0" @click="clearFilters">
                        مسح الفلاتر
                    </button>
                </template>
            </AppFilterBar>

            <section class="ui-table-shell">
                <div class="ui-table-header">
                    <div class="ui-section-header !mb-0">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">قائمة المستخدمين</h2>
                            <p class="ui-section-subtitle">عرض مرن على الشاشات الكبيرة، وبطاقات مبسطة على الموبايل والأجهزة اللوحية الصغيرة.</p>
                        </div>
                    </div>
                </div>

                <div v-if="users.length === 0" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="empty"
                        title="لا يوجد مستخدمون مضافون بعد"
                        description="ابدأ بإنشاء أول حساب إداري، ثم اربطه بالدور والقسم المناسبين ليظهر ضمن القائمة مباشرة."
                    >
                        <button type="button" class="ui-primary-button" @click="openCreateModal">
                            <UserPlus class="h-4 w-4" />
                            <span>إضافة أول مستخدم</span>
                        </button>
                    </AppStatePanel>
                </div>

                <div v-else-if="filteredUsers.length === 0" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="no-results"
                        title="لا توجد نتائج مطابقة للفلاتر"
                        description="جرّب توسيع البحث أو إزالة الفلاتر الحالية لعرض جميع المستخدمين مرة أخرى."
                        compact
                    >
                        <button type="button" class="ui-secondary-button" @click="clearFilters">مسح الفلاتر</button>
                    </AppStatePanel>
                </div>

                <template v-else>
                    <div class="hidden lg:block ui-table-container">
                        <table class="ui-data-table min-w-[980px]">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>البيانات الشخصية</th>
                                    <th>الدور</th>
                                    <th>الإدارة</th>
                                    <th class="text-left">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in filteredUsers" :key="user.id">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="ui-avatar shrink-0">{{ userInitial(user.name) }}</div>
                                            <div class="min-w-0 text-right">
                                                <p class="truncate font-black text-white">{{ user.name }}</p>
                                                <p class="text-xs text-slate-400">حساب إداري</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-2 text-right">
                                            <p class="flex items-center justify-end gap-2 text-slate-200">
                                                <span dir="ltr">{{ user.email }}</span>
                                                <Mail class="h-3.5 w-3.5 text-slate-500" />
                                            </p>
                                            <p v-if="user.mobile" class="flex items-center justify-end gap-2 text-xs text-slate-400">
                                                <span dir="ltr">{{ user.mobile }}</span>
                                                <Phone class="h-3.5 w-3.5 text-slate-500" />
                                            </p>
                                        </div>
                                    </td>
                                    <td>
                                        <span v-if="roleNameForUser(user)" class="ui-chip">
                                            <Shield class="h-3.5 w-3.5" />
                                            <span>{{ roleNameForUser(user) }}</span>
                                        </span>
                                        <span v-else class="text-xs text-slate-500">لا يوجد دور</span>
                                    </td>
                                    <td>
                                        <span class="ui-chip">
                                            <Building2 class="h-3.5 w-3.5" />
                                            <span>{{ departmentNameForUser(user) }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                class="ui-icon-button"
                                                :aria-label="`تعديل بيانات المستخدم ${user.name}`"
                                                title="تعديل"
                                                @click="openEditModal(user)"
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="ui-icon-button"
                                                :aria-label="`حذف المستخدم ${user.name}`"
                                                title="حذف"
                                                @click="deleteUser(user)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="ui-mobile-card-list">
                        <article v-for="user in filteredUsers" :key="user.id" class="ui-mobile-row-card text-right">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="ui-avatar shrink-0">{{ userInitial(user.name) }}</div>
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-black text-white">{{ user.name }}</p>
                                        <p class="truncate text-xs text-slate-400" dir="ltr">{{ user.email }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="ui-icon-button"
                                        :aria-label="`تعديل بيانات المستخدم ${user.name}`"
                                        @click="openEditModal(user)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="ui-icon-button"
                                        :aria-label="`حذف المستخدم ${user.name}`"
                                        @click="deleteUser(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="ui-mobile-row-label">رقم الجوال</p>
                                    <p class="mt-1 text-sm text-slate-300" dir="ltr">{{ user.mobile || 'غير مضاف' }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">الدور</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ roleNameForUser(user) || 'لا يوجد دور' }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="ui-mobile-row-label">الإدارة</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ departmentNameForUser(user) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </template>
            </section>

            <section class="ui-table-shell">
                <div class="ui-table-header">
                    <div class="ui-section-header !mb-0">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">المدارس</h2>
                            <p class="ui-section-subtitle">
                                عرض موجز لكل المدارس المضافة مع المدير والمشرف وحالة التشغيل، دون نقل إدارة المدارس أو تغيير مساراتها الحالية.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="ui-chip">{{ schools.length }} مدرسة</span>
                            <span class="ui-chip">{{ activeSchoolsCount }} مفعلة</span>
                        </div>
                    </div>
                </div>

                <div v-if="schools.length === 0" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="empty"
                        title="لا توجد مدارس مسجلة بعد"
                        description="ستظهر المدارس هنا بعد إضافتها من تهيئة مدير المدرسة أو من مسارات الإدارة المعتمدة."
                        compact
                    />
                </div>

                <template v-else>
                    <div class="hidden lg:block ui-table-container">
                        <table class="ui-data-table min-w-[1180px]">
                            <thead>
                                <tr>
                                    <th>المدرسة</th>
                                    <th>النطاق التعليمي</th>
                                    <th>مدير المدرسة</th>
                                    <th>المشرف التربوي</th>
                                    <th>الحالات</th>
                                    <th>الأعداد</th>
                                    <th>تاريخ التسجيل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="school in schools" :key="school.id">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="ui-avatar shrink-0">
                                                <School class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0 text-right">
                                                <p class="truncate font-black text-white">{{ school.name }}</p>
                                                <p class="text-xs text-slate-400">
                                                    الكود: <span dir="ltr">{{ school.school_id || '-' }}</span>
                                                </p>
                                                <p class="text-xs text-slate-500">النوع: {{ schoolTypeLabel(school.school_type) }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1 text-right text-sm">
                                            <p class="text-slate-200">{{ schoolLocationLabel(school) }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ school.directorate?.education_type || 'نوع التعليم غير محدد' }}
                                            </p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1 text-right">
                                            <p class="font-semibold text-slate-100">{{ school.manager?.name || 'غير مرتبط' }}</p>
                                            <p v-if="school.manager?.email" class="text-xs text-slate-500" dir="ltr">{{ school.manager.email }}</p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1 text-right">
                                            <p class="font-semibold text-slate-100">{{ school.supervisor?.name || 'لا يوجد مشرف' }}</p>
                                            <p v-if="school.supervisor?.email" class="text-xs text-slate-500" dir="ltr">{{ school.supervisor.email }}</p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col items-start gap-2">
                                            <span class="ui-chip" :class="schoolStatusMeta(school.status).className">
                                                {{ schoolStatusMeta(school.status).label }}
                                            </span>
                                            <span class="ui-chip">{{ supervisionStatusLabel(school.supervision_status) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-2 text-right text-sm text-slate-300">
                                            <p>المستخدمون: {{ school.users_count || 0 }}</p>
                                            <p>الطلاب: {{ school.students_count || 0 }}</p>
                                        </div>
                                    </td>
                                    <td class="text-slate-300">{{ formatDate(school.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="ui-mobile-card-list">
                        <article v-for="school in schools" :key="school.id" class="ui-mobile-row-card text-right">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="ui-avatar shrink-0">
                                        <School class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-black text-white">{{ school.name }}</p>
                                        <p class="truncate text-xs text-slate-400">
                                            {{ school.school_id || 'بدون كود' }} · {{ schoolTypeLabel(school.school_type) }}
                                        </p>
                                    </div>
                                </div>

                                <span class="ui-chip shrink-0" :class="schoolStatusMeta(school.status).className">
                                    {{ schoolStatusMeta(school.status).label }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <p class="ui-mobile-row-label">النطاق التعليمي</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ schoolLocationLabel(school) }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">نوع التعليم</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ school.directorate?.education_type || 'غير محدد' }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">مدير المدرسة</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ school.manager?.name || 'غير مرتبط' }}</p>
                                    <p v-if="school.manager?.email" class="mt-1 truncate text-xs text-slate-500" dir="ltr">{{ school.manager.email }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">المشرف التربوي</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ school.supervisor?.name || 'لا يوجد مشرف' }}</p>
                                    <p v-if="school.supervisor?.email" class="mt-1 truncate text-xs text-slate-500" dir="ltr">{{ school.supervisor.email }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">حالة الإشراف</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ supervisionStatusLabel(school.supervision_status) }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">الأعداد</p>
                                    <p class="mt-1 text-sm text-slate-300">
                                        {{ school.users_count || 0 }} مستخدم · {{ school.students_count || 0 }} طالب
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="ui-mobile-row-label">تاريخ التسجيل</p>
                                    <p class="mt-1 text-sm text-slate-300">{{ formatDate(school.created_at) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </template>
            </section>
        </div>

        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            dir="rtl"
            @click.self="closeModal"
        >
            <div class="ui-form-shell w-full max-w-3xl max-h-[92vh] overflow-y-auto">
                <div class="ui-section-header border-b border-white/10 pb-4">
                    <div class="ui-section-heading text-right">
                        <h3 class="ui-section-title">{{ isEditing ? 'تعديل بيانات المستخدم' : 'إضافة مستخدم جديد' }}</h3>
                        <p class="ui-section-subtitle">
                            {{ isEditing ? 'حدّث البيانات الأساسية والدور والقسم، مع إمكانية ترك كلمة المرور فارغة إذا لم ترغب في تعديلها.' : 'أنشئ حسابًا جديدًا وحدّد الدور والقسم المناسبين قبل الحفظ.' }}
                        </p>
                    </div>

                    <button type="button" class="ui-icon-button" aria-label="إغلاق النافذة" @click="closeModal">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <div class="ui-form-grid">
                        <div class="space-y-2">
                            <label for="admin-user-name" class="ui-field-label">الاسم الكامل</label>
                            <input id="admin-user-name" v-model="form.name" type="text" class="ui-input" placeholder="الاسم الثلاثي" />
                            <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="admin-user-email" class="ui-field-label">البريد الإلكتروني</label>
                            <input id="admin-user-email" v-model="form.email" type="email" dir="ltr" class="ui-input" placeholder="example@domain.com" />
                            <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div class="ui-form-grid">
                        <div class="space-y-2">
                            <label for="admin-user-mobile" class="ui-field-label">رقم الجوال</label>
                            <input id="admin-user-mobile" v-model="form.mobile" type="text" dir="ltr" inputmode="tel" class="ui-input" placeholder="05xxxxxxxx أو +9665xxxxxxxx" />
                            <p class="ui-helper-text">الرقم يقبل التنسيق المحلي أو الدولي.</p>
                            <p v-if="form.errors.mobile" class="ui-field-error">{{ form.errors.mobile }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="admin-user-role" class="ui-field-label">الدور الوظيفي</label>
                            <select id="admin-user-role" v-model="form.role_name" class="ui-select">
                                <option value="" disabled>اختر الدور</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                            </select>
                            <p v-if="form.errors.role_name" class="ui-field-error">{{ form.errors.role_name }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="admin-user-department" class="ui-field-label">الإدارة / القسم</label>
                        <select id="admin-user-department" v-model="form.department_id" class="ui-select">
                            <option value="" disabled>اختر الإدارة</option>
                            <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                                {{ department.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.department_id" class="ui-field-error">{{ form.errors.department_id }}</p>
                    </div>

                    <div class="ui-card-soft space-y-4 p-4">
                        <div class="text-right">
                            <p class="text-sm font-black text-white">بيانات الأمان</p>
                            <p class="ui-helper-text">في وضع التعديل يمكنك ترك الحقول فارغة للإبقاء على كلمة المرور الحالية.</p>
                        </div>

                        <div class="ui-form-grid">
                            <div class="space-y-2">
                                <label for="admin-user-password" class="ui-field-label">
                                    كلمة المرور <span v-if="!isEditing" class="text-rose-400">*</span>
                                </label>
                                <input id="admin-user-password" v-model="form.password" type="password" dir="ltr" class="ui-input" />
                            </div>

                            <div class="space-y-2">
                                <label for="admin-user-password-confirmation" class="ui-field-label">
                                    تأكيد كلمة المرور <span v-if="!isEditing" class="text-rose-400">*</span>
                                </label>
                                <input id="admin-user-password-confirmation" v-model="form.password_confirmation" type="password" dir="ltr" class="ui-input" />
                            </div>
                        </div>

                        <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" class="ui-ghost-button" @click="closeModal">إلغاء</button>
                        <button type="submit" :disabled="form.processing" class="ui-primary-button min-w-[12rem] self-end">
                            <span>{{ isEditing ? (form.processing ? 'جارٍ حفظ التعديلات...' : 'حفظ التعديلات') : (form.processing ? 'جارٍ إنشاء المستخدم...' : 'إنشاء المستخدم') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
