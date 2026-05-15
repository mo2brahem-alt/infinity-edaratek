<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, LayoutGrid, Pencil, Plus, ShieldCheck, Sparkles, Trash2, UserCog, Users, X } from 'lucide-vue-next';
import AttachmentPanel from '@/Components/AttachmentPanel.vue';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import AppSearchField from '@/Components/AppSearchField.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';
import { useThemeMode } from '@/composables/useThemeMode';

const props = defineProps({
    school: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    staffTypes: { type: Array, default: () => ['ADMINISTRATIVE', 'EDUCATIONAL'] },
    users: { type: Array, default: () => [] },
    roleAssignmentEnabled: { type: Boolean, default: true },
    orgStructureRoleTemplates: { type: Array, default: () => [] },
    managerAssignsStructurePermissions: { type: Boolean, default: true },
    assignablePermissionGroups: { type: Array, default: () => [] },
    permissionGroupTypeOptions: { type: Array, default: () => [] },
    schoolPermissionGroups: { type: Array, default: () => [] },
    delegationTemplates: { type: Array, default: () => [] },
    delegationAuditEntries: { type: Array, default: () => [] },
});

const mandatoryRoleName = 'staff';
const legacyPermissionColumnMap = {
    can_manage_student_structure: 'school.student_structure.manage',
    can_manage_student_attendance: 'school.student_attendance.manage',
    can_manage_academic_planning: 'school.academic_planning.manage',
    can_manage_student_leaves: 'school.student_leaves.manage',
    can_manage_leave_types: 'school.leave_types.manage',
    can_manage_school_calendar: 'school.calendar.manage',
    can_manage_school_holidays: 'school.holidays.manage',
};
const fallbackPermissionDependencyMap = {
    'school.reports.export': ['school.reports.view'],
};
const fallbackPermissionGroupCatalog = [
    {
        key: 'administrative',
        type: 'administrative',
        label: 'صلاحيات الهيكل الإداري',
        description: 'خصصها للمهام التشغيلية والإدارية القابلة للتفويض داخل المدرسة.',
        tone: 'cyan',
        permissions: [
            {
                name: 'school.student_structure.manage',
                label: 'إدارة الهيكل الطلابي',
                description: 'إدارة المراحل والفصول والطلاب داخل المدرسة.',
                tone: 'cyan',
            },
            {
                name: 'school.student_attendance.manage',
                label: 'إدارة الحضور اليومي',
                description: 'تسجيل الحضور والغياب ومتابعة السجلات اليومية.',
                tone: 'blue',
            },
            {
                name: 'school.student_leaves.manage',
                label: 'إدارة إجازات الطلاب',
                description: 'إنشاء طلبات الإجازات ومتابعتها واعتمادها داخل المدرسة.',
                tone: 'violet',
            },
            {
                name: 'school.leave_types.manage',
                label: 'إدارة أنواع الإجازات',
                description: 'تنظيم أنواع الإجازات وضبط قواعدها التشغيلية.',
                tone: 'amber',
            },
            {
                name: 'school.calendar.manage',
                label: 'إدارة التقويم المدرسي',
                description: 'إدارة إعدادات التقويم والمدد الزمنية المرتبطة بالمدرسة.',
                tone: 'indigo',
            },
            {
                name: 'school.holidays.manage',
                label: 'إدارة العطلات المدرسية',
                description: 'إضافة العطلات المدرسية وربطها بالتقويم السنوي المعتمد.',
                tone: 'emerald',
            },
        ],
    },
    {
        key: 'educational',
        type: 'educational',
        label: 'صلاحيات الهيكل التعليمي',
        description: 'خصصها للمهام الأكاديمية والتعليمية القابلة للتفويض داخل المدرسة.',
        tone: 'emerald',
        permissions: [
            {
                name: 'school.academic_planning.manage',
                label: 'إدارة الهيكل الأكاديمي والتخطيط',
                description: 'إدارة العام الدراسي والمواد والجداول وما يرتبط بها من تشغيل أكاديمي واختبارات.',
                tone: 'emerald',
            },
        ],
    },
];
const fallbackPermissionGroupTypeOptions = fallbackPermissionGroupCatalog.map((group) => ({
    value: group.type,
    label: group.label,
    description: group.description,
}));
const roleLabels = { staff: 'فريق المدرسة', teacher: 'معلم' };
const toneClasses = {
    blue: 'manager-structure-chip manager-structure-chip--blue',
    emerald: 'manager-structure-chip manager-structure-chip--emerald',
    amber: 'manager-structure-chip manager-structure-chip--amber',
    indigo: 'manager-structure-chip manager-structure-chip--indigo',
    fuchsia: 'manager-structure-chip manager-structure-chip--fuchsia',
    cyan: 'manager-structure-chip manager-structure-chip--cyan',
    violet: 'manager-structure-chip manager-structure-chip--violet',
    slate: 'manager-structure-chip manager-structure-chip--slate',
    orange: 'manager-structure-chip manager-structure-chip--orange',
    pink: 'manager-structure-chip manager-structure-chip--pink',
    purple: 'manager-structure-chip manager-structure-chip--purple',
    red: 'manager-structure-chip manager-structure-chip--red',
    zinc: 'manager-structure-chip manager-structure-chip--zinc',
};

const userEditId = ref(null);
const editingRoleContext = ref(null);
const apiProcessing = ref(false);
const assignableRoles = ref([]);
const hasLoadedAssignableRoles = ref(false);
const isUserModalOpen = ref(false);
const isDepartmentModalOpen = ref(false);
const departmentEditId = ref(null);
const isPermissionGroupModalOpen = ref(false);
const permissionGroupEditId = ref(null);
const departmentProcessing = ref(false);
const permissionGroupProcessing = ref(false);
const userFilters = ref({
    search: '',
    departmentId: 'all',
    staffType: 'all',
});
const actionDialog = useActionDialog();
const { isLightMode } = useThemeMode();

const createEmptyDepartmentRole = () => ({
    id: null,
    name: '',
    org_structure_role_template_id: null,
    can_manage_student_structure: false,
    can_manage_student_attendance: false,
    can_manage_academic_planning: false,
    can_manage_student_leaves: false,
});

const hasRenderablePermissionGroups = (groups) => Array.isArray(groups)
    && groups.some((group) => String(group?.type || group?.key || '').trim() !== ''
        && Array.isArray(group?.permissions)
        && group.permissions.some((permission) => String(permission?.name || '').trim() !== ''));

const canUseRoleAssignment = computed(() => Boolean(props.roleAssignmentEnabled));
const templateOptions = computed(() => Array.isArray(props.orgStructureRoleTemplates) ? props.orgStructureRoleTemplates : []);
const showDepartmentRolePermissionToggles = computed(() => !props.managerAssignsStructurePermissions);
const schoolOwnedDepartments = computed(() => props.departments.filter((department) => !Boolean(department?.is_legacy_global)));
const defaultDepartmentId = computed(() => schoolOwnedDepartments.value[0]?.id || props.departments[0]?.id || '');
const templateLabelById = computed(() => {
    const labels = new Map();
    templateOptions.value.forEach((template) => {
        labels.set(Number(template?.id || 0), String(template?.name || '').trim());
    });
    return labels;
});
const normalizedPermissionGroups = computed(() => hasRenderablePermissionGroups(props.assignablePermissionGroups)
    ? props.assignablePermissionGroups
    : fallbackPermissionGroupCatalog);
const normalizedPermissionGroupTypeOptions = computed(() => Array.isArray(props.permissionGroupTypeOptions) && props.permissionGroupTypeOptions.length > 0
    ? props.permissionGroupTypeOptions
    : [
        { value: 'administrative', label: 'صلاحيات الهيكل الإداري', description: 'حزم تشغيلية للإدارة اليومية داخل المدرسة.' },
        { value: 'educational', label: 'صلاحيات الهيكل التعليمي', description: 'حزم تشغيلية للمهام الأكاديمية والتعليمية.' },
    ]);
const normalizedDelegationTemplates = computed(() => Array.isArray(props.delegationTemplates) ? props.delegationTemplates : []);
const delegationAuditEntries = computed(() => Array.isArray(props.delegationAuditEntries) ? props.delegationAuditEntries : []);
const normalizedSchoolPermissionGroups = computed(() => Array.isArray(props.schoolPermissionGroups) ? props.schoolPermissionGroups : []);
const permissionMetadataByName = computed(() => Object.fromEntries(
    normalizedPermissionGroups.value.flatMap((group) => (Array.isArray(group.permissions) ? group.permissions : []).map((permission) => [
        String(permission?.name || ''),
        {
            name: String(permission?.name || ''),
            label: String(permission?.label || ''),
            description: String(permission?.description || ''),
            tone: String(permission?.tone || group?.tone || 'slate'),
            module: String(permission?.module || ''),
            group_label: String(group?.label || ''),
            sensitive: Boolean(permission?.sensitive),
            manager_assignable: permission?.manager_assignable !== false,
            dependencies: Array.isArray(permission?.dependencies) ? permission.dependencies.map((dependency) => String(dependency || '').trim()).filter(Boolean) : [],
        },
    ])).filter(([name]) => name !== '')
));
const permissionDependencyMap = computed(() => ({
    ...fallbackPermissionDependencyMap,
    ...Object.fromEntries(Object.entries(permissionMetadataByName.value).map(([permissionName, permission]) => [
        permissionName,
        Array.isArray(permission?.dependencies) ? permission.dependencies : [],
    ])),
}));
const permissionDependentsMap = computed(() => {
    const entries = {};
    Object.entries(permissionDependencyMap.value).forEach(([permissionName, dependencies]) => {
        dependencies.forEach((dependencyName) => {
            if (!Array.isArray(entries[dependencyName])) entries[dependencyName] = [];
            entries[dependencyName].push(permissionName);
        });
    });
    return entries;
});
const assignablePermissionNames = computed(() => Object.keys(permissionMetadataByName.value));
const roleMetadataByName = computed(() => Object.fromEntries(assignableRoles.value.map((role) => [String(role?.name || ''), role]).filter(([name]) => name !== '')));
const optionalAssignableRoles = computed(() => assignableRoles.value.filter((role) => String(role?.name || '') !== mandatoryRoleName));
const defaultPermissionGroupType = computed(() => normalizedPermissionGroupTypeOptions.value[0]?.value || 'administrative');
const selectedDelegationTemplateKey = ref('custom');

const roleOptionsForDepartment = (departmentId) => {
    const department = props.departments.find((item) => Number(item.id) === Number(departmentId));
    return department && Array.isArray(department.roles) ? department.roles.filter((role) => role.is_active) : [];
};

const buildDepartmentRoleOptions = (departmentId) => {
    const options = [...roleOptionsForDepartment(departmentId)];
    const context = editingRoleContext.value;
    if (!context || Number(context.department_id) !== Number(departmentId)) return options;
    if (!options.some((role) => Number(role.id) === Number(context.id)) && Number(context.id) > 0) {
        options.push({ id: Number(context.id), name: context.name || '-', is_active: true, is_legacy_inactive: true });
    }
    return options;
};

const firstRoleForDepartment = (departmentId) => roleOptionsForDepartment(departmentId)[0]?.id || '';
const defaultRoleNames = () => (canUseRoleAssignment.value ? [mandatoryRoleName] : []);
const normalizeRoleNames = (roleNames) => {
    let normalized = Array.isArray(roleNames) ? roleNames.map((item) => String(item || '').trim()).filter(Boolean) : [];
    if (hasLoadedAssignableRoles.value) {
        const allowed = assignableRoles.value.map((role) => String(role?.name || '')).filter(Boolean);
        normalized = normalized.filter((name) => allowed.includes(name));
    }
    if (canUseRoleAssignment.value && !normalized.includes(mandatoryRoleName)) normalized.unshift(mandatoryRoleName);
    return [...new Set(normalized)];
};

const normalizePermissionNames = (permissionNames) => {
    let normalized = Array.isArray(permissionNames) ? permissionNames.map((item) => String(item || '').trim()).filter(Boolean) : [];
    if (assignablePermissionNames.value.length > 0) normalized = normalized.filter((name) => assignablePermissionNames.value.includes(name));
    const selected = new Set(normalized);
    let changed = true;
    while (changed) {
        changed = false;
        Object.entries(permissionDependencyMap.value).forEach(([permissionName, dependencies]) => {
            if (!selected.has(permissionName)) return;
            dependencies.forEach((dependencyName) => {
                if (!selected.has(dependencyName)) {
                    selected.add(dependencyName);
                    changed = true;
                }
            });
        });
    }
    return [...selected];
};
const normalizePermissionGroupIds = (groupIds) => {
    const allowedIds = normalizedSchoolPermissionGroups.value.map((group) => Number(group?.id || 0)).filter((groupId) => groupId > 0);

    return [...new Set(
        (Array.isArray(groupIds) ? groupIds : [])
            .map((groupId) => Number(groupId))
            .filter((groupId) => groupId > 0 && allowedIds.includes(groupId))
    )];
};
const permissionNamesForGroupType = (groupType) => normalizedPermissionGroups.value
    .filter((group) => String(group?.type || group?.key || '') === String(groupType || ''))
    .flatMap((group) => Array.isArray(group?.permissions) ? group.permissions.map((permission) => String(permission?.name || '').trim()).filter(Boolean) : []);
const normalizePermissionGroupPermissionNames = (permissionNames, groupType) => {
    const allowedNames = permissionNamesForGroupType(groupType);
    return normalizePermissionNames(permissionNames).filter((permissionName) => allowedNames.includes(permissionName));
};

const pendingUserAttachments = ref([]);
const userForm = useForm({
    name: '',
    email: '',
    mobile: '',
    department_id: defaultDepartmentId.value,
    department_role_id: firstRoleForDepartment(defaultDepartmentId.value),
    role_names: [],
    permission_names: [],
    school_permission_group_ids: [],
    can_manage_student_structure: false,
    can_manage_student_attendance: false,
    can_manage_academic_planning: false,
    can_manage_student_leaves: false,
    can_manage_leave_types: false,
    can_manage_school_calendar: false,
    can_manage_school_holidays: false,
    password: '',
    password_confirmation: '',
    attachments: [],
});
const permissionGroupForm = useForm({
    name: '',
    group_type: defaultPermissionGroupType.value,
    permission_names: [],
});
const departmentForm = useForm({
    name: '',
    staff_type: props.staffTypes?.[0] || 'ADMINISTRATIVE',
    org_structure_roles: [createEmptyDepartmentRole()],
});
const assignableDepartmentsForUserForm = computed(() => {
    const selectedDepartment = props.departments.find((department) => Number(department?.id || 0) === Number(userForm.department_id || 0));
    const availableDepartments = [...schoolOwnedDepartments.value];

    if (selectedDepartment?.is_legacy_global && !availableDepartments.some((department) => Number(department?.id || 0) === Number(selectedDepartment.id))) {
        availableDepartments.unshift(selectedDepartment);
    }

    return availableDepartments.length > 0 ? availableDepartments : props.departments;
});

const syncLegacyPermissionFlags = (permissionNames) => {
    const selected = new Set(permissionNames);
    Object.entries(legacyPermissionColumnMap).forEach(([column, permissionName]) => {
        userForm[column] = selected.has(permissionName);
    });
};
const setSelectedRoleNames = (roleNames) => { userForm.role_names = normalizeRoleNames(roleNames); };
const setSelectedSchoolPermissionGroupIds = (groupIds) => { userForm.school_permission_group_ids = normalizePermissionGroupIds(groupIds); };
const setSelectedPermissionNames = (permissionNames) => {
    const normalized = normalizePermissionNames(permissionNames);
    userForm.permission_names = normalized;
    syncLegacyPermissionFlags(normalized);
};
const setPermissionGroupFormPermissionNames = (permissionNames) => {
    permissionGroupForm.permission_names = normalizePermissionGroupPermissionNames(permissionNames, permissionGroupForm.group_type);
};

const resolveUserRoleNames = (user) => {
    const fromRelations = Array.isArray(user?.roles) ? user.roles.map((role) => role?.name) : [];
    const source = Array.isArray(user?.role_names) && user.role_names.length > 0 ? user.role_names : fromRelations;
    return normalizeRoleNames(source.filter(Boolean));
};
const resolveUserSchoolPermissionGroups = (user) => {
    const groups = Array.isArray(user?.school_permission_groups)
        ? user.school_permission_groups
        : Array.isArray(user?.schoolPermissionGroups)
            ? user.schoolPermissionGroups
            : [];

    return groups
        .map((group) => ({
            id: Number(group?.id || 0),
            name: String(group?.name || '').trim(),
            group_type: String(group?.group_type || ''),
            group_type_label: String(group?.group_type_label || '').trim(),
            permission_names: Array.isArray(group?.permission_names) ? group.permission_names.map((permissionName) => String(permissionName || '').trim()).filter(Boolean) : [],
        }))
        .filter((group) => group.id > 0 && group.name !== '');
};
const resolveUserSchoolPermissionGroupIds = (user) => {
    const fromPayload = Array.isArray(user?.school_permission_group_ids)
        ? user.school_permission_group_ids
        : resolveUserSchoolPermissionGroups(user).map((group) => group.id);

    return normalizePermissionGroupIds(fromPayload);
};
const resolveUserDirectPermissionNames = (user) => {
    const fromPayload = Array.isArray(user?.direct_permission_names)
        ? user.direct_permission_names
        : Array.isArray(user?.permission_names)
            ? user.permission_names
            : Array.isArray(user?.permissions)
                ? user.permissions.map((permission) => permission?.name)
                : [];

    if (fromPayload.filter(Boolean).length > 0) return normalizePermissionNames(fromPayload);
    return normalizePermissionNames(Object.entries(legacyPermissionColumnMap).filter(([column]) => Boolean(user?.[column])).map(([, permissionName]) => permissionName));
};
const resolveUserEffectivePermissionNames = (user) => {
    const fromPayload = Array.isArray(user?.effective_permission_names) ? user.effective_permission_names : [];
    if (fromPayload.filter(Boolean).length > 0) return normalizePermissionNames(fromPayload);

    return normalizePermissionNames([
        ...resolveUserDirectPermissionNames(user),
        ...resolveUserSchoolPermissionGroups(user).flatMap((group) => group.permission_names),
    ]);
};

const removePermissionWithDependents = (selected, permissionName) => {
    const stack = [permissionName];
    while (stack.length > 0) {
        const current = stack.pop();
        if (!current || !selected.has(current)) continue;
        selected.delete(current);
        (permissionDependentsMap.value[current] || []).forEach((dependent) => {
            if (selected.has(dependent)) stack.push(dependent);
        });
    }
};

const selectedDepartment = computed(() => props.departments.find((item) => Number(item.id) === Number(userForm.department_id)) || null);
const departmentRoleOptions = computed(() => buildDepartmentRoleOptions(userForm.department_id));
const selectedDepartmentStaffTypeLabel = computed(() => staffTypeLabel(selectedDepartment.value?.staff_type));
const selectedPermissionCount = computed(() => normalizePermissionNames(userForm.permission_names).length);
const selectedOptionalRoleCount = computed(() => normalizeRoleNames(userForm.role_names).filter((roleName) => roleName !== mandatoryRoleName).length);
const selectedPermissionGroupCount = computed(() => normalizePermissionGroupIds(userForm.school_permission_group_ids).length);
const selectedPermissionGroupNames = computed(() => normalizedSchoolPermissionGroups.value
    .filter((group) => normalizePermissionGroupIds(userForm.school_permission_group_ids).includes(Number(group?.id || 0)))
    .flatMap((group) => Array.isArray(group?.permission_names) ? group.permission_names : [])
    .map((permissionName) => String(permissionName || '').trim())
    .filter(Boolean));
const selectedEffectivePermissionNames = computed(() => normalizePermissionNames([
    ...userForm.permission_names,
    ...selectedPermissionGroupNames.value,
]));
const selectedSensitivePermissions = computed(() => selectedEffectivePermissionNames.value
    .map((permissionName) => permissionMetadataByName.value[permissionName])
    .filter((permission) => Boolean(permission?.sensitive)));
const selectedDelegationModuleCount = computed(() => new Set(selectedEffectivePermissionNames.value
    .map((permissionName) => String(permissionMetadataByName.value[permissionName]?.module || '').trim())
    .filter(Boolean)).size);
const schoolPermissionGroupCount = computed(() => normalizedSchoolPermissionGroups.value.length);
const selectedPermissionGroupFormCount = computed(() => normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type).length);
const permissionGroupCatalogForSelectedType = computed(() => normalizedPermissionGroups.value.filter((group) => String(group?.type || group?.key || '') === String(permissionGroupForm.group_type || '')));
const departmentCount = computed(() => props.departments.length);
const activeDepartmentRoles = (department) => Array.isArray(department?.roles) ? department.roles.filter((role) => Boolean(role?.is_active)) : [];
const activeRoleCount = computed(() => props.departments.reduce((total, department) => total + activeDepartmentRoles(department).length, 0));
const schoolUserCount = computed(() => props.users.length);
const administrativeUserCount = computed(() => props.users.filter((user) => user?.school_staff_type === 'ADMINISTRATIVE').length);
const educationalUserCount = computed(() => props.users.filter((user) => user?.school_staff_type === 'EDUCATIONAL').length);
const normalizedUserSearch = computed(() => String(userFilters.value.search || '').trim().toLocaleLowerCase('ar'));
const hasActiveUserFilters = computed(() =>
    normalizedUserSearch.value !== ''
    || String(userFilters.value.departmentId) !== 'all'
    || String(userFilters.value.staffType) !== 'all'
);
const activeUserFilterCount = computed(() =>
    [
        normalizedUserSearch.value !== '',
        String(userFilters.value.departmentId) !== 'all',
        String(userFilters.value.staffType) !== 'all',
    ].filter(Boolean).length
);
const filteredUsers = computed(() => {
    const search = normalizedUserSearch.value;
    const departmentId = String(userFilters.value.departmentId);
    const staffType = String(userFilters.value.staffType);

    return props.users.filter((user) => {
        const matchesDepartment = departmentId === 'all' || String(user?.department_id || '') === departmentId;
        const matchesStaffType = staffType === 'all' || String(user?.school_staff_type || '') === staffType;

        if (!matchesDepartment || !matchesStaffType) {
            return false;
        }

        if (search === '') {
            return true;
        }

        const haystack = [
            user?.name,
            user?.email,
            user?.mobile,
            user?.department?.name,
            user?.department_role?.name,
            ...userRoleBadges(user).map((badge) => badge.label),
            ...userSchoolPermissionGroupBadges(user).map((badge) => badge.label),
            ...userPermissionBadges(user).map((badge) => badge.label),
        ]
            .map((value) => String(value || '').toLocaleLowerCase('ar'))
            .join(' ');

        return haystack.includes(search);
    });
});

const normalizeExistingAttachments = (attachments) =>
    (Array.isArray(attachments) ? attachments : [])
        .map((attachment) => ({
            id: Number(attachment?.id || 0),
            file_name: String(attachment?.file_name || attachment?.original_name || '').trim(),
            file_size: Number(attachment?.file_size || attachment?.size || 0),
            mime_type: String(attachment?.mime_type || '').trim(),
            uploaded_by: String(attachment?.uploaded_by || attachment?.uploader?.name || '').trim() || null,
            uploaded_at: attachment?.uploaded_at || attachment?.created_at || null,
            download_url: attachment?.download_url || attachment?.url || null,
        }))
        .filter((attachment) => attachment.id > 0 && attachment.file_name !== '' && attachment.download_url);

const selectedManagedUser = computed(() =>
    (props.users || []).find((user) => Number(user?.id || 0) === Number(userEditId.value || 0)) || null
);

const selectedManagedUserAttachments = computed(() =>
    normalizeExistingAttachments(selectedManagedUser.value?.attachments || [])
);

watch(() => userForm.department_id, (departmentId) => {
    const options = buildDepartmentRoleOptions(departmentId);
    if (!options.some((role) => Number(role.id) === Number(userForm.department_role_id))) userForm.department_role_id = options[0]?.id || '';
});
watch(defaultPermissionGroupType, (groupType) => {
    if (!normalizedPermissionGroupTypeOptions.value.some((option) => option.value === permissionGroupForm.group_type)) {
        permissionGroupForm.group_type = groupType;
    }
});
watch(() => permissionGroupForm.group_type, (groupType) => {
    setPermissionGroupFormPermissionNames(permissionGroupForm.permission_names.filter((permissionName) => permissionNamesForGroupType(groupType).includes(permissionName)));
});
watch(() => departmentForm.staff_type, (staffType) => {
    if (staffType === 'ADMINISTRATIVE') return;

    departmentForm.org_structure_roles = (departmentForm.org_structure_roles || []).map((role) => ({
        ...role,
        can_manage_student_structure: false,
        can_manage_student_attendance: false,
        can_manage_academic_planning: false,
        can_manage_student_leaves: false,
    }));
});

const loadAssignableRoles = async () => {
    if (!canUseRoleAssignment.value) return;
    try {
        const response = await axios.get(route('api.school.roles.assignable'));
        assignableRoles.value = Array.isArray(response?.data?.data)
            ? response.data.data.map((role) => ({
                id: role?.id ?? null,
                name: String(role?.name || '').trim(),
                display_name: String(role?.display_name || '').trim(),
                description: String(role?.description || '').trim(),
            })).filter((role) => role.name !== '')
            : [];
    } catch {
        assignableRoles.value = [];
    } finally {
        if (!assignableRoles.value.some((role) => role.name === mandatoryRoleName)) {
            assignableRoles.value.unshift({ id: null, name: mandatoryRoleName, display_name: roleLabels.staff, description: '' });
        }
        hasLoadedAssignableRoles.value = true;
        setSelectedRoleNames(userForm.role_names.length > 0 ? userForm.role_names : defaultRoleNames());
    }
};

const resolveTemplateLabel = (templateId, fallbackName = '-') => {
    if (!templateId) return fallbackName || '-';
    return templateLabelById.value.get(Number(templateId)) || fallbackName || '-';
};
const departmentRoleNameError = (index) => departmentForm.errors[`org_structure_roles.${index}.name`] || '';
const isLegacyLinkedDepartmentRole = (roleRow) => Number(roleRow?.org_structure_role_template_id || 0) > 0;
const buildDepartmentPayload = () => ({
    name: String(departmentForm.name || '').trim(),
    staff_type: departmentForm.staff_type,
    org_structure_roles: (departmentForm.org_structure_roles || [])
        .map((role) => ({
            id: role.id || null,
            name: String(role.name || '').trim(),
            org_structure_role_template_id: role.org_structure_role_template_id || null,
            can_manage_student_structure: showDepartmentRolePermissionToggles.value && departmentForm.staff_type === 'ADMINISTRATIVE' ? Boolean(role.can_manage_student_structure) : false,
            can_manage_student_attendance: showDepartmentRolePermissionToggles.value && departmentForm.staff_type === 'ADMINISTRATIVE' ? Boolean(role.can_manage_student_attendance) : false,
            can_manage_academic_planning: showDepartmentRolePermissionToggles.value && departmentForm.staff_type === 'ADMINISTRATIVE' ? Boolean(role.can_manage_academic_planning) : false,
            can_manage_student_leaves: showDepartmentRolePermissionToggles.value && departmentForm.staff_type === 'ADMINISTRATIVE' ? Boolean(role.can_manage_student_leaves) : false,
        }))
        .filter((role) => role.name !== ''),
});
const resetDepartmentForm = () => {
    departmentEditId.value = null;
    departmentForm.reset();
    departmentForm.name = '';
    departmentForm.staff_type = props.staffTypes?.[0] || 'ADMINISTRATIVE';
    departmentForm.org_structure_roles = [createEmptyDepartmentRole()];
    departmentForm.clearErrors();
};
const openCreateDepartmentModal = () => {
    resetDepartmentForm();
    isDepartmentModalOpen.value = true;
};
const closeDepartmentModal = () => {
    isDepartmentModalOpen.value = false;
    resetDepartmentForm();
};
const startDepartmentEdit = (department) => {
    departmentEditId.value = Number(department?.id || 0) || null;
    departmentForm.name = department?.name || '';
    departmentForm.staff_type = department?.staff_type || props.staffTypes?.[0] || 'ADMINISTRATIVE';
    departmentForm.org_structure_roles = Array.isArray(department?.roles) && department.roles.length > 0
        ? department.roles.map((role) => ({
            id: role?.id || null,
            name: role?.name || '',
            org_structure_role_template_id: role?.org_structure_role_template_id || null,
            can_manage_student_structure: Boolean(role?.can_manage_student_structure) && departmentForm.staff_type === 'ADMINISTRATIVE',
            can_manage_student_attendance: Boolean(role?.can_manage_student_attendance) && departmentForm.staff_type === 'ADMINISTRATIVE',
            can_manage_academic_planning: Boolean(role?.can_manage_academic_planning) && departmentForm.staff_type === 'ADMINISTRATIVE',
            can_manage_student_leaves: Boolean(role?.can_manage_student_leaves) && departmentForm.staff_type === 'ADMINISTRATIVE',
        }))
        : [createEmptyDepartmentRole()];
    departmentForm.clearErrors();
    isDepartmentModalOpen.value = true;
};
const addDepartmentRoleRow = () => {
    departmentForm.org_structure_roles = [...(departmentForm.org_structure_roles || []), createEmptyDepartmentRole()];
};
const removeDepartmentRoleRow = (index) => {
    if ((departmentForm.org_structure_roles || []).length <= 1) return;
    departmentForm.org_structure_roles = departmentForm.org_structure_roles.filter((_, currentIndex) => currentIndex !== index);
};
const submitDepartment = () => {
    departmentForm.clearErrors();
    departmentProcessing.value = true;

    const requestOptions = {
        preserveScroll: true,
        onSuccess: () => closeDepartmentModal(),
        onError: (errors) => {
            if (Object.keys(errors || {}).length === 0) {
                departmentForm.setError('general', 'تعذر حفظ الإدارة حاليًا. حاول مرة أخرى.');
            }
        },
        onFinish: () => {
            departmentProcessing.value = false;
        },
    };

    if (departmentEditId.value) {
        departmentForm.transform(() => buildDepartmentPayload()).put(route('manager.structure.departments.update', departmentEditId.value), requestOptions);
        return;
    }

    departmentForm.transform(() => buildDepartmentPayload()).post(route('manager.structure.departments.store'), requestOptions);
};
const removeDepartment = async (department) => {
    if (!department?.can_manage) return;

    const confirmed = await actionDialog.confirm({
        title: 'حذف الإدارة',
        message: department?.users_count > 0
            ? `هذه الإدارة مرتبطة حاليًا بـ ${department.users_count} مستخدم داخل المدرسة. لن يتم حذفها حتى يتم نقل المستخدمين عنها أولًا. هل تريد المتابعة؟`
            : 'سيتم حذف الإدارة من الهيكل المدرسي الحالي. هل تريد المتابعة؟',
        confirmText: 'حذف الإدارة',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    departmentProcessing.value = true;
    router.delete(route('manager.structure.departments.destroy', department.id), {
        preserveScroll: true,
        onError: async (errors) => {
            const message = errors?.department
                || errors?.general
                || 'تعذر حذف الإدارة حاليًا. تحقق من الارتباطات ثم حاول مرة أخرى.';

            await actionDialog.alert({
                title: 'تعذر حذف الإدارة',
                message,
                confirmText: 'حسنًا',
            });
        },
        onFinish: () => {
            departmentProcessing.value = false;
        },
    });
};

const resetUserForm = () => {
    userEditId.value = null;
    editingRoleContext.value = null;
    selectedDelegationTemplateKey.value = 'custom';
    userForm.reset();
    userForm.department_id = defaultDepartmentId.value;
    userForm.department_role_id = firstRoleForDepartment(defaultDepartmentId.value);
    setSelectedRoleNames(defaultRoleNames());
    setSelectedPermissionNames([]);
    setSelectedSchoolPermissionGroupIds([]);
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.attachments = [];
    pendingUserAttachments.value = [];
    userForm.clearErrors();
};
const openCreateUserModal = () => {
    resetUserForm();
    isUserModalOpen.value = true;
};
const closeUserModal = () => {
    isUserModalOpen.value = false;
    resetUserForm();
};
const startUserEdit = (user) => {
    userEditId.value = user.id;
    editingRoleContext.value = user?.department_role_id ? { id: Number(user.department_role_id), name: user?.department_role?.name || '-', department_id: Number(user.department_id || 0) } : null;
    selectedDelegationTemplateKey.value = 'custom';
    userForm.name = user.name || '';
    userForm.email = user.email || '';
    userForm.mobile = user.mobile || '';
    userForm.department_id = user.department_id || defaultDepartmentId.value;
    userForm.department_role_id = user.department_role_id || firstRoleForDepartment(user.department_id || defaultDepartmentId.value);
    setSelectedRoleNames(resolveUserRoleNames(user));
    setSelectedPermissionNames(resolveUserDirectPermissionNames(user));
    setSelectedSchoolPermissionGroupIds(resolveUserSchoolPermissionGroupIds(user));
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.attachments = [];
    pendingUserAttachments.value = [];
    userForm.clearErrors();
    isUserModalOpen.value = true;
};
const clearUserFilters = () => {
    userFilters.value = {
        search: '',
        departmentId: 'all',
        staffType: 'all',
    };
};

const applyDelegationTemplate = (templateKey) => {
    const normalizedKey = String(templateKey || '').trim();
    const template = normalizedDelegationTemplates.value.find((item) => String(item?.key || '') === normalizedKey);

    selectedDelegationTemplateKey.value = normalizedKey || 'custom';

    if (!template) {
        return;
    }

    setSelectedRoleNames([
        ...defaultRoleNames(),
        ...(Array.isArray(template.role_names) ? template.role_names : []),
    ]);
    setSelectedPermissionNames(Array.isArray(template.permission_names) ? template.permission_names : []);
};

const mapApiValidationErrors = (error) => {
    userForm.clearErrors();
    const status = error?.response?.status;
    const errors = error?.response?.data?.errors || {};
    if (status === 422 && typeof errors === 'object') {
        Object.keys(errors).forEach((field) => {
            const fieldMessages = errors[field];
            const message = Array.isArray(fieldMessages) ? fieldMessages[0] : String(fieldMessages || '');
            if (!message) return;
            if (field.startsWith('role_names')) return userForm.setError('role_names', message);
            if (field.startsWith('permission_names')) return userForm.setError('permission_names', message);
            if (field.startsWith('school_permission_group_ids')) return userForm.setError('school_permission_group_ids', message);
            userForm.setError(field, message);
        });
        return;
    }
    userForm.setError('general', status === 403 ? 'لا تملك صلاحية تنفيذ هذا الإجراء.' : 'تعذر حفظ البيانات حاليًا. حاول مرة أخرى.');
};

const appendUserAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) return;

    pendingUserAttachments.value = [...pendingUserAttachments.value, ...incoming].slice(0, 10);
};

const removePendingUserAttachment = (index) => {
    pendingUserAttachments.value = pendingUserAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingUserAttachments = () => {
    pendingUserAttachments.value = [];
    userForm.attachments = [];
};

const userAttachmentErrors = computed(() => [
    userForm.errors.attachments,
    userForm.errors['attachments.0'],
].filter((value) => typeof value === 'string' && value.trim() !== ''));

const deleteUserAttachment = async (attachment) => {
    if (!attachment?.id) return;

    const confirmed = await actionDialog.confirm({
        title: 'حذف المرفق',
        message: 'سيتم حذف هذا المرفق من ملف المستخدم. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    router.delete(route('school.attachments.destroy', { attachment: attachment.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};

const buildApiPayload = (isUpdate = false) => {
    const permissionNames = normalizePermissionNames(userForm.permission_names);
    const payload = {
        name: userForm.name,
        email: userForm.email,
        mobile: userForm.mobile,
        department_id: userForm.department_id,
        department_role_id: userForm.department_role_id,
        role_names: normalizeRoleNames(userForm.role_names),
        permission_names: permissionNames,
        school_permission_group_ids: normalizePermissionGroupIds(userForm.school_permission_group_ids),
        attachments: [...pendingUserAttachments.value],
        ...Object.fromEntries(Object.entries(legacyPermissionColumnMap).map(([column, permissionName]) => [column, permissionNames.includes(permissionName)])),
    };
    if (!isUpdate || userForm.password) {
        payload.password = userForm.password;
        payload.password_confirmation = userForm.password_confirmation;
    }
    return payload;
};

const appendFormDataValue = (formData, key, value) => {
    if (Array.isArray(value)) {
        value.forEach((item) => appendFormDataValue(formData, `${key}[]`, item));
        return;
    }

    if (value instanceof File) {
        formData.append(key, value);
        return;
    }

    if (value === null || value === undefined) {
        return;
    }

    if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0');
        return;
    }

    formData.append(key, String(value));
};

const buildApiFormData = (isUpdate = false) => {
    const formData = new FormData();
    const payload = buildApiPayload(isUpdate);

    Object.entries(payload).forEach(([key, value]) => {
        appendFormDataValue(formData, key, value);
    });

    if (isUpdate) {
        formData.append('_method', 'PUT');
    }

    return formData;
};

const submitUser = async () => {
    if (!canUseRoleAssignment.value) {
        userForm.attachments = [...pendingUserAttachments.value];
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                clearPendingUserAttachments();
                closeUserModal();
            },
        };

        if (userEditId.value) {
            userForm.put(route('manager.structure.users.update', userEditId.value), options);
            return;
        }

        userForm.post(route('manager.structure.users.store'), options);
        return;
    }

    userForm.clearErrors();
    apiProcessing.value = true;
    try {
        const isUpdate = Boolean(userEditId.value);
        const endpoint = isUpdate
            ? route('api.school.users.update', userEditId.value)
            : route('api.school.users.store');

        await axios.post(endpoint, buildApiFormData(isUpdate), {
            headers: {
                Accept: 'application/json',
            },
        });

        clearPendingUserAttachments();
        closeUserModal();
        await router.reload({ only: ['users'], preserveScroll: true });
    } catch (error) {
        mapApiValidationErrors(error);
    } finally {
        apiProcessing.value = false;
    }
};

const toggleRoleName = (roleName) => {
    const normalizedRoleName = String(roleName || '').trim();
    if (normalizedRoleName === '' || normalizedRoleName === mandatoryRoleName) return;
    const selected = new Set(normalizeRoleNames(userForm.role_names));
    selected.has(normalizedRoleName) ? selected.delete(normalizedRoleName) : selected.add(normalizedRoleName);
    setSelectedRoleNames([...selected]);
};
const toggleSchoolPermissionGroupId = (groupId) => {
    const normalizedGroupId = Number(groupId || 0);
    if (normalizedGroupId <= 0) return;
    const selected = new Set(normalizePermissionGroupIds(userForm.school_permission_group_ids));
    selected.has(normalizedGroupId) ? selected.delete(normalizedGroupId) : selected.add(normalizedGroupId);
    setSelectedSchoolPermissionGroupIds([...selected]);
};
const togglePermissionName = (permissionName) => {
    const normalizedPermissionName = String(permissionName || '').trim();
    if (normalizedPermissionName === '') return;
    const selected = new Set(normalizePermissionNames(userForm.permission_names));
    if (selected.has(normalizedPermissionName)) removePermissionWithDependents(selected, normalizedPermissionName);
    else selected.add(normalizedPermissionName);
    setSelectedPermissionNames([...selected]);
};
const togglePermissionGroupFormPermission = (permissionName) => {
    const normalizedPermissionName = String(permissionName || '').trim();
    if (normalizedPermissionName === '') return;
    const selected = new Set(normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type));
    if (selected.has(normalizedPermissionName)) removePermissionWithDependents(selected, normalizedPermissionName);
    else selected.add(normalizedPermissionName);
    setPermissionGroupFormPermissionNames([...selected]);
};
const areAllPermissionsSelected = (group) => {
    const permissionNames = Array.isArray(group?.permissions) ? group.permissions.map((permission) => permission.name) : [];
    return permissionNames.length > 0 && permissionNames.every((permissionName) => normalizePermissionNames(userForm.permission_names).includes(permissionName));
};
const setGroupPermissions = (group, enabled) => {
    const selected = new Set(normalizePermissionNames(userForm.permission_names));
    const permissionNames = Array.isArray(group?.permissions) ? group.permissions.map((permission) => permission.name) : [];
    if (enabled) permissionNames.forEach((permissionName) => selected.add(permissionName));
    else permissionNames.forEach((permissionName) => removePermissionWithDependents(selected, permissionName));
    setSelectedPermissionNames([...selected]);
};
const areAllPermissionGroupFormPermissionsSelected = (group) => {
    const permissionNames = Array.isArray(group?.permissions) ? group.permissions.map((permission) => permission.name) : [];
    return permissionNames.length > 0 && permissionNames.every((permissionName) => normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type).includes(permissionName));
};
const setPermissionGroupFormPermissions = (group, enabled) => {
    const selected = new Set(normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type));
    const permissionNames = Array.isArray(group?.permissions) ? group.permissions.map((permission) => permission.name) : [];
    if (enabled) permissionNames.forEach((permissionName) => selected.add(permissionName));
    else permissionNames.forEach((permissionName) => removePermissionWithDependents(selected, permissionName));
    setPermissionGroupFormPermissionNames([...selected]);
};
const removeUser = async (userId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف المستخدم',
        message: 'هل أنت متأكد من حذف هذا المستخدم من هيكل المدرسة؟',
        confirmText: 'حذف المستخدم',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    useForm({}).delete(route('manager.structure.users.destroy', userId));
};
const resetPermissionGroupForm = () => {
    permissionGroupEditId.value = null;
    permissionGroupForm.reset();
    permissionGroupForm.name = '';
    permissionGroupForm.group_type = defaultPermissionGroupType.value;
    permissionGroupForm.permission_names = [];
    permissionGroupForm.clearErrors();
};
const openCreatePermissionGroupModal = () => {
    resetPermissionGroupForm();
    isPermissionGroupModalOpen.value = true;
};
const closePermissionGroupModal = () => {
    isPermissionGroupModalOpen.value = false;
    resetPermissionGroupForm();
};
const startPermissionGroupEdit = (group) => {
    permissionGroupEditId.value = Number(group?.id || 0) || null;
    permissionGroupForm.name = group?.name || '';
    permissionGroupForm.group_type = group?.group_type || defaultPermissionGroupType.value;
    setPermissionGroupFormPermissionNames(Array.isArray(group?.permission_names) ? group.permission_names : []);
    permissionGroupForm.clearErrors();
    isPermissionGroupModalOpen.value = true;
};
const mapPermissionGroupValidationErrors = (error) => {
    permissionGroupForm.clearErrors();
    const status = error?.response?.status;
    const errors = error?.response?.data?.errors || {};
    if (status === 422 && typeof errors === 'object') {
        Object.keys(errors).forEach((field) => {
            const fieldMessages = errors[field];
            const message = Array.isArray(fieldMessages) ? fieldMessages[0] : String(fieldMessages || '');
            if (!message) return;
            if (field.startsWith('permission_names')) return permissionGroupForm.setError('permission_names', message);
            permissionGroupForm.setError(field, message);
        });
        return;
    }
    permissionGroupForm.setError('general', status === 403 ? 'لا تملك صلاحية تنفيذ هذا الإجراء.' : 'تعذر حفظ مجموعة الصلاحيات حاليًا. حاول مرة أخرى.');
};
const buildPermissionGroupPayload = () => ({
    name: String(permissionGroupForm.name || '').trim(),
    group_type: permissionGroupForm.group_type,
    permission_names: normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type),
});
const submitPermissionGroup = async () => {
    permissionGroupForm.clearErrors();
    permissionGroupProcessing.value = true;
    try {
        if (permissionGroupEditId.value) {
            await axios.put(route('api.school.permission_groups.update', permissionGroupEditId.value), buildPermissionGroupPayload());
        } else {
            await axios.post(route('api.school.permission_groups.store'), buildPermissionGroupPayload());
        }
        closePermissionGroupModal();
        await router.reload({ only: ['schoolPermissionGroups', 'users'], preserveScroll: true });
    } catch (error) {
        mapPermissionGroupValidationErrors(error);
    } finally {
        permissionGroupProcessing.value = false;
    }
};
const removePermissionGroup = async (group) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف مجموعة الصلاحيات',
        message: group?.users_count > 0
            ? `هذه المجموعة مرتبطة حاليًا بـ ${group.users_count} مستخدم. سيؤدي حذفها إلى فك الإسناد عنهم. هل تريد المتابعة؟`
            : 'هل أنت متأكد من حذف مجموعة الصلاحيات هذه؟',
        confirmText: 'حذف المجموعة',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    try {
        await axios.delete(route('api.school.permission_groups.destroy', group.id));
        await router.reload({ only: ['schoolPermissionGroups', 'users'], preserveScroll: true });
    } catch (error) {
        mapPermissionGroupValidationErrors(error);
    }
};

const departmentRoleLabel = (role) => role?.name || '-';
const staffTypeLabel = (type) => type === 'ADMINISTRATIVE' ? 'إداري' : type === 'EDUCATIONAL' ? 'تعليمي' : (type || '-');
const staffTypeBadgeClass = (type) => type === 'ADMINISTRATIVE' ? toneClasses.cyan : type === 'EDUCATIONAL' ? toneClasses.emerald : toneClasses.slate;
const roleBadgeClass = (roleName) => toneClasses[roleName === 'teacher' ? 'emerald' : roleName === mandatoryRoleName ? 'slate' : 'blue'];
const permissionBadgeClass = (tone) => toneClasses[tone] || toneClasses.slate;
const roleLabel = (roleName) => String(roleMetadataByName.value[roleName]?.display_name || '').trim() || roleLabels[roleName] || roleName;
const permissionLabel = (permissionName) => permissionMetadataByName.value[permissionName]?.label || permissionName;
const permissionTone = (permissionName) => permissionMetadataByName.value[permissionName]?.tone || 'slate';
const isSensitivePermission = (permissionName) => Boolean(permissionMetadataByName.value[permissionName]?.sensitive);
const formatDelegationAuditDate = (value) => {
    if (!value) return '-';

    try {
        return new Intl.DateTimeFormat('ar-EG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
};
const isRoleSelected = (roleName) => normalizeRoleNames(userForm.role_names).includes(String(roleName || '').trim());
const isSchoolPermissionGroupSelected = (groupId) => normalizePermissionGroupIds(userForm.school_permission_group_ids).includes(Number(groupId || 0));
const isPermissionSelected = (permissionName) => normalizePermissionNames(userForm.permission_names).includes(String(permissionName || '').trim());
const isPermissionGroupFormPermissionSelected = (permissionName) => normalizePermissionGroupPermissionNames(permissionGroupForm.permission_names, permissionGroupForm.group_type).includes(String(permissionName || '').trim());
const userRoleBadges = (user) => resolveUserRoleNames(user).map((roleName) => ({ name: roleName, label: roleLabel(roleName) }));
const userSchoolPermissionGroupBadges = (user) => resolveUserSchoolPermissionGroups(user).map((group) => ({
    id: group.id,
    label: group.name,
    tone: group.group_type === 'educational' ? 'emerald' : 'cyan',
}));
const userPermissionBadges = (user) => resolveUserEffectivePermissionNames(user).map((permissionName) => ({ name: permissionName, label: permissionLabel(permissionName), tone: permissionTone(permissionName) }));
const userCardInitial = (user) => String(user?.name || '').trim().charAt(0).toUpperCase() || 'U';

onMounted(loadAssignableRoles);
resetUserForm();
</script>

<template>
    <Head title="هيكل المدرسة" />

    <RoleLayout title="هيكل المدرسة" role="SCHOOL_MANAGER">
        <div class="ui-page-shell manager-structure-shell" :class="{ 'manager-structure-shell--light': isLightMode }">
        <AppInlineAlert
            v-if="!school"
            variant="warning"
            class="manager-structure-card"
            message="حسابك غير مرتبط بمدرسة بعد. أكمل التهيئة أولًا."
        />

        <div v-else class="space-y-6">
            <section class="ui-page-hero manager-structure-hero">
                <div class="ui-page-header flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="ui-page-heading space-y-3">
                        <div class="manager-structure-section-title">
                            <div class="manager-structure-icon-box manager-structure-icon-box--cyan"><Building2 class="h-5 w-5" /></div>
                            <div>
                                <p class="ui-page-kicker manager-structure-eyebrow">مساحة تنظيم المدرسة</p>
                                <h2 class="ui-page-title text-2xl font-black text-white">{{ school.name }}</h2>
                            </div>
                        </div>
                        <p class="ui-page-copy max-w-2xl text-sm leading-7 text-slate-300">
                            اضبط الإدارات والأدوار والمستخدمين والصلاحيات التشغيلية الدقيقة لكل مهمة، حتى تصبح المهام اليومية واضحة وآمنة داخل نفس نطاق المدرسة.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="manager-structure-chip manager-structure-chip--blue">الرمز المدرسي: {{ school.school_id || '-' }}</span>
                            <span class="manager-structure-chip manager-structure-chip--slate">المستخدمون الحاليون: {{ schoolUserCount }}</span>
                        </div>
                    </div>

                    <div class="flex w-full max-w-sm flex-col gap-3">
                        <button type="button" class="ui-primary-button manager-structure-primary-button w-full" @click="router.get(route('school.academic_planning.index'), { quick_setup: 1, quick_setup_step: 'school_users' })">
                            <Sparkles class="h-4 w-4" />
                            <span>الإعدادات السريعة</span>
                        </button>
                        <p class="text-xs leading-6 text-slate-400">يفتح هذا المسار خطوات الإعداد المختصرة لإكمال التهيئة بسرعة.</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="ui-card-soft manager-structure-card-soft"><p class="manager-structure-stat-label">الإدارات المعتمدة</p><p class="manager-structure-stat-value">{{ departmentCount }}</p></div>
                    <div class="ui-card-soft manager-structure-card-soft"><p class="manager-structure-stat-label">الأدوار النشطة</p><p class="manager-structure-stat-value">{{ activeRoleCount }}</p></div>
                    <div class="ui-card-soft manager-structure-card-soft"><p class="manager-structure-stat-label">الكادر الإداري</p><p class="manager-structure-stat-value">{{ administrativeUserCount }}</p></div>
                    <div class="ui-card-soft manager-structure-card-soft"><p class="manager-structure-stat-label">الكادر التعليمي</p><p class="manager-structure-stat-value">{{ educationalUserCount }}</p></div>
                </div>
            </section>

            <section class="ui-section manager-structure-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="manager-structure-section-title">
                        <div class="manager-structure-icon-box manager-structure-icon-box--emerald"><LayoutGrid class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-lg font-bold text-white">الإدارات والأدوار المعتمدة</h2>
                            <p class="text-sm text-slate-400">أنشئ الإدارات الخاصة بمدرستك، ثم عرّف داخل كل إدارة الأدوار الوظيفية المناسبة للكادر الإداري أو التعليمي مع إبقاء الإدارات العامة القديمة للقراءة فقط.</p>
                        </div>
                    </div>

                    <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="openCreateDepartmentModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة إدارة جديدة</span>
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="manager-structure-chip manager-structure-chip--blue">الإدارات داخل هذا النطاق: {{ departmentCount }}</span>
                    <span class="manager-structure-chip manager-structure-chip--slate">الإدارات الخاصة بالمدرسة: {{ schoolOwnedDepartments.length }}</span>
                </div>

                <div
                    v-if="isDepartmentModalOpen"
                    class="manager-structure-modal-shell"
                    @click.self="closeDepartmentModal"
                >
                    <div class="manager-structure-modal-panel">
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-4">
                            <div class="space-y-1">
                                <p class="manager-structure-eyebrow">إدارة الهيكل التنظيمي</p>
                                <h3 class="text-xl font-black text-white">{{ departmentEditId ? 'تعديل الإدارة' : 'إنشاء إدارة جديدة' }}</h3>
                                <p class="text-sm leading-7 text-slate-400">أنشئ إدارة داخل مدرستك وحدد نوع الكادر والأدوار الوظيفية التي سيجري استخدامها لاحقًا في ربط المستخدمين داخل نفس المدرسة.</p>
                            </div>

                            <button type="button" class="manager-structure-modal-close" aria-label="إغلاق نافذة الإدارة" @click="closeDepartmentModal">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form class="ui-form-shell manager-structure-form mt-5" @submit.prevent="submitDepartment">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="manager-structure-label">اسم الإدارة</label>
                                    <input v-model="departmentForm.name" class="ui-input manager-structure-input" placeholder="مثل: شؤون الطلاب أو شؤون الاختبارات" />
                                    <p v-if="departmentForm.errors.name" class="manager-structure-error">{{ departmentForm.errors.name }}</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="manager-structure-label">نوع الكادر</label>
                                    <select v-model="departmentForm.staff_type" class="ui-select manager-structure-input">
                                        <option v-for="staffType in staffTypes" :key="staffType" :value="staffType">{{ staffTypeLabel(staffType) }}</option>
                                    </select>
                                    <p v-if="departmentForm.errors.staff_type" class="manager-structure-error">{{ departmentForm.errors.staff_type }}</p>
                                </div>
                            </div>

                            <div class="ui-card-soft manager-structure-card-soft mt-5">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="manager-structure-section-title">
                                        <div class="manager-structure-icon-box manager-structure-icon-box--cyan"><UserCog class="h-4 w-4" /></div>
                                        <div>
                                            <p class="text-sm font-bold text-white">الأدوار الوظيفية داخل الإدارة</p>
                                            <p class="text-xs text-slate-400">أنشئ الأدوار التشغيلية الخاصة بهذه الإدارة داخل مدرستك، ثم اربط المستخدمين بها حسب طبيعة العمل اليومية.</p>
                                        </div>
                                    </div>

                                    <button type="button" class="manager-structure-pill-button" @click="addDepartmentRoleRow">
                                        <Plus class="h-4 w-4" />
                                        <span>إضافة دور وظيفي</span>
                                    </button>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div
                                        v-for="(roleRow, index) in departmentForm.org_structure_roles"
                                        :key="roleRow.id ? `department-role-row-${roleRow.id}` : `department-role-row-${index}`"
                                        class="manager-structure-option-card"
                                    >
                                        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.4fr)_auto]">
                                            <div class="space-y-2">
                                                <label class="manager-structure-label">اسم الدور الوظيفي</label>
                                                <input v-model="roleRow.name" class="ui-input manager-structure-input" placeholder="مثل: مسؤول شؤون الطلاب أو مسؤول الحضور" />
                                                <div v-if="isLegacyLinkedDepartmentRole(roleRow)" class="flex flex-wrap gap-2">
                                                    <span class="manager-structure-chip manager-structure-chip--slate">
                                                        {{ resolveTemplateLabel(roleRow.org_structure_role_template_id, 'مرتبط بقالب عام') }}
                                                    </span>
                                                    <span class="manager-structure-chip manager-structure-chip--blue">مرتبط بقالب عام سابق</span>
                                                </div>
                                                <p v-if="departmentRoleNameError(index)" class="manager-structure-error">{{ departmentRoleNameError(index) }}</p>
                                            </div>

                                            <div class="flex items-end justify-end">
                                                <button
                                                    type="button"
                                                    class="manager-structure-inline-danger"
                                                    :disabled="departmentForm.org_structure_roles.length <= 1"
                                                    :aria-label="`إزالة الدور الوظيفي رقم ${index + 1}`"
                                                    @click="removeDepartmentRoleRow(index)"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                    <span>إزالة</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div v-if="showDepartmentRolePermissionToggles && departmentForm.staff_type === 'ADMINISTRATIVE'" class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                                            <label class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': roleRow.can_manage_student_structure }">
                                                <input v-model="roleRow.can_manage_student_structure" type="checkbox" class="manager-structure-checkbox" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="manager-structure-option-title">إدارة الهيكل الطلابي</span>
                                                    <span class="manager-structure-option-description">السماح بإدارة الطلاب والمراحل والفصول داخل المدرسة.</span>
                                                </span>
                                            </label>
                                            <label class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': roleRow.can_manage_student_attendance }">
                                                <input v-model="roleRow.can_manage_student_attendance" type="checkbox" class="manager-structure-checkbox" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="manager-structure-option-title">إدارة الحضور اليومي</span>
                                                    <span class="manager-structure-option-description">السماح بمتابعة سجلات الحضور والغياب اليومية.</span>
                                                </span>
                                            </label>
                                            <label class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': roleRow.can_manage_academic_planning }">
                                                <input v-model="roleRow.can_manage_academic_planning" type="checkbox" class="manager-structure-checkbox" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="manager-structure-option-title">إدارة التخطيط الأكاديمي</span>
                                                    <span class="manager-structure-option-description">السماح بمتابعة التخطيط الأكاديمي والمواد والجداول.</span>
                                                </span>
                                            </label>
                                            <label class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': roleRow.can_manage_student_leaves }">
                                                <input v-model="roleRow.can_manage_student_leaves" type="checkbox" class="manager-structure-checkbox" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="manager-structure-option-title">إدارة إجازات الطلاب</span>
                                                    <span class="manager-structure-option-description">السماح بمتابعة طلبات الإجازات وإجراءاتها التشغيلية.</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="departmentForm.errors.org_structure_roles" class="manager-structure-error mt-4">{{ departmentForm.errors.org_structure_roles }}</p>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="closeDepartmentModal">إلغاء</button>
                                <button class="ui-primary-button manager-structure-primary-button" :disabled="departmentProcessing || departmentForm.processing">
                                    {{ departmentEditId ? 'تحديث الإدارة' : 'إنشاء الإدارة' }}
                                </button>
                            </div>

                            <p v-if="departmentForm.errors.general" class="manager-structure-error mt-3">{{ departmentForm.errors.general }}</p>
                        </form>
                    </div>
                </div>

                <div v-if="departments.length === 0" class="manager-structure-empty mt-5">لا توجد إدارات معرّفة حاليًا داخل هذا النطاق. ابدأ بإنشاء أول إدارة خاصة بمدرستك.</div>
                <div v-else class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <article v-for="department in departments" :key="department.id" class="manager-structure-subcard">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-bold text-white">{{ department.name }}</p>
                                    <span :class="department.is_school_owned ? toneClasses.blue : toneClasses.slate">{{ department.scope_label }}</span>
                                </div>
                                <p class="text-xs text-slate-400">عدد الأدوار المفعلة: {{ activeDepartmentRoles(department).length }} | المستخدمون المرتبطون: {{ department.users_count }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="staffTypeBadgeClass(department.staff_type)">{{ staffTypeLabel(department.staff_type) }}</span>
                                <button v-if="department.can_manage" type="button" class="manager-structure-secondary-button !px-4 !py-2 !text-sm" @click="startDepartmentEdit(department)">تعديل</button>
                                <button v-if="department.can_manage" type="button" class="manager-structure-inline-danger" @click="removeDepartment(department)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>حذف</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="activeDepartmentRoles(department).length > 0" class="mt-4 flex flex-wrap gap-2">
                            <span v-for="role in activeDepartmentRoles(department)" :key="role.id" class="manager-structure-chip manager-structure-chip--slate">
                                {{ role.name }}
                            </span>
                        </div>
                        <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد أدوار مفعلة داخل هذه الإدارة حاليًا.</div>

                        <p v-if="department.is_legacy_global" class="mt-4 text-xs leading-6 text-slate-400">
                            هذه إدارة عامة قديمة تظهر للقراءة فقط لأنها ما تزال مرتبطة بمستخدمين داخل مدرستك. أنشئ إدارة مدرسية جديدة إذا احتجت إلى إدارة محلية قابلة للتعديل.
                        </p>
                    </article>
                </div>
            </section>

            <section class="ui-section manager-structure-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="manager-structure-section-title">
                        <div class="manager-structure-icon-box manager-structure-icon-box--blue"><ShieldCheck class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-lg font-bold text-white">مجموعات الصلاحيات المدرسية</h2>
                            <p class="text-sm text-slate-400">أنشئ حزمًا تشغيلية جاهزة داخل مدرستك ثم أسندها للمستخدمين بدل توزيع الصلاحيات واحدة تلو الأخرى.</p>
                        </div>
                    </div>

                    <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="openCreatePermissionGroupModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة مجموعة صلاحيات</span>
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="manager-structure-chip manager-structure-chip--blue">إجمالي المجموعات: {{ schoolPermissionGroupCount }}</span>
                    <span class="manager-structure-chip manager-structure-chip--slate">ضمن نفس نطاق المدرسة فقط</span>
                </div>

                <div
                    v-if="isPermissionGroupModalOpen"
                    class="manager-structure-modal-shell"
                    @click.self="closePermissionGroupModal"
                >
                    <div class="manager-structure-modal-panel">
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-4">
                            <div class="space-y-1">
                                <p class="manager-structure-eyebrow">مجموعات الصلاحيات</p>
                                <h3 class="text-xl font-black text-white">{{ permissionGroupEditId ? 'تعديل مجموعة الصلاحيات' : 'إنشاء مجموعة صلاحيات' }}</h3>
                                <p class="text-sm leading-7 text-slate-400">أنشئ مجموعة واضحة الاسم ثم اجمع بداخلها الصلاحيات الإدارية أو التعليمية القابلة للتفويض داخل نفس المدرسة.</p>
                            </div>

                            <button type="button" class="manager-structure-modal-close" aria-label="إغلاق نافذة مجموعة الصلاحيات" @click="closePermissionGroupModal">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form class="ui-form-shell manager-structure-form mt-5" @submit.prevent="submitPermissionGroup">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="manager-structure-label">اسم المجموعة</label>
                                    <input v-model="permissionGroupForm.name" class="ui-input manager-structure-input" placeholder="مثل: شؤون الطلاب أو شؤون الاختبارات" />
                                    <p v-if="permissionGroupForm.errors.name" class="manager-structure-error">{{ permissionGroupForm.errors.name }}</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="manager-structure-label">نوع المجموعة</label>
                                    <select v-model="permissionGroupForm.group_type" class="ui-select manager-structure-input">
                                        <option v-for="option in normalizedPermissionGroupTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                    <p v-if="permissionGroupForm.errors.group_type" class="manager-structure-error">{{ permissionGroupForm.errors.group_type }}</p>
                                </div>
                            </div>

                            <div class="ui-card-soft manager-structure-card-soft mt-5">
                                <div class="manager-structure-section-title">
                                    <div class="manager-structure-icon-box manager-structure-icon-box--violet"><ShieldCheck class="h-4 w-4" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-white">صلاحيات المجموعة</p>
                                        <p class="text-xs text-slate-400">اختر الصلاحيات المطابقة لنوع المجموعة فقط حتى تبقى الحزمة واضحة وقابلة لإعادة الاستخدام.</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="manager-structure-chip manager-structure-chip--blue">الصلاحيات المحددة: {{ selectedPermissionGroupFormCount }}</span>
                                    <span class="manager-structure-chip manager-structure-chip--slate">{{ normalizedPermissionGroupTypeOptions.find((option) => option.value === permissionGroupForm.group_type)?.label || '-' }}</span>
                                </div>
                                <div v-if="permissionGroupCatalogForSelectedType.length > 0" class="mt-4 grid grid-cols-1 gap-4">
                                    <article v-for="group in permissionGroupCatalogForSelectedType" :key="`permission-group-form-${group.key}`" class="manager-structure-permission-group">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <p class="manager-structure-option-title text-white">{{ group.label }}</p>
                                                <p class="manager-structure-option-description">{{ group.description }}</p>
                                            </div>
                                            <button type="button" class="manager-structure-pill-button" @click="setPermissionGroupFormPermissions(group, !areAllPermissionGroupFormPermissionsSelected(group))">
                                                {{ areAllPermissionGroupFormPermissionsSelected(group) ? 'إلغاء المجموعة' : 'تفعيل المجموعة' }}
                                            </button>
                                        </div>
                                        <div class="mt-4 grid grid-cols-1 gap-2">
                                            <label v-for="permission in group.permissions" :key="`permission-group-form-item-${permission.name}`" class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': isPermissionGroupFormPermissionSelected(permission.name) }">
                                                <input type="checkbox" class="manager-structure-checkbox" :checked="isPermissionGroupFormPermissionSelected(permission.name)" @change="togglePermissionGroupFormPermission(permission.name)" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex flex-wrap items-center gap-2">
                                                        <span class="manager-structure-option-title">{{ permission.label }}</span>
                                                        <span v-if="isSensitivePermission(permission.name)" class="manager-structure-chip manager-structure-chip--red">حساسة</span>
                                                    </span>
                                                    <span class="manager-structure-option-description">{{ permission.description }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    </article>
                                </div>
                                <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد صلاحيات قابلة للتنظيم ضمن هذا التصنيف حاليًا.</div>
                                <p v-if="permissionGroupForm.errors.permission_names" class="manager-structure-error mt-3">{{ permissionGroupForm.errors.permission_names }}</p>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="closePermissionGroupModal">إلغاء</button>
                                <button class="ui-primary-button manager-structure-primary-button" :disabled="permissionGroupProcessing">
                                    {{ permissionGroupEditId ? 'تحديث المجموعة' : 'إنشاء المجموعة' }}
                                </button>
                            </div>

                            <p v-if="permissionGroupForm.errors.general" class="manager-structure-error mt-3">{{ permissionGroupForm.errors.general }}</p>
                        </form>
                    </div>
                </div>

                <div v-if="normalizedSchoolPermissionGroups.length === 0" class="manager-structure-empty mt-5">لا توجد مجموعات صلاحيات داخل هذه المدرسة بعد. أنشئ أول مجموعة لتسهيل التفويض على فريق المدرسة.</div>
                <div v-else class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <article v-for="group in normalizedSchoolPermissionGroups" :key="group.id" class="manager-structure-subcard">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-base font-bold text-white">{{ group.name }}</p>
                                <p class="text-xs text-slate-400">{{ group.group_type_label }} | مستخدمون مرتبطون: {{ group.users_count }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span :class="group.group_type === 'educational' ? toneClasses.emerald : toneClasses.cyan">{{ group.group_type_label }}</span>
                                <button type="button" class="manager-structure-secondary-button !px-4 !py-2 !text-sm" @click="startPermissionGroupEdit(group)">تعديل</button>
                                <button type="button" class="manager-structure-inline-danger" @click="removePermissionGroup(group)"><Trash2 class="h-3.5 w-3.5" /><span>حذف</span></button>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="permission in group.permissions" :key="`${group.id}-${permission.name}`" :class="permissionBadgeClass(permission.tone)">{{ permission.label }}</span>
                        </div>
                    </article>
                </div>
            </section>

            <section class="ui-section manager-structure-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="manager-structure-section-title">
                        <div class="manager-structure-icon-box manager-structure-icon-box--violet"><Users class="h-5 w-5" /></div>
                        <div>
                            <h2 class="text-lg font-bold text-white">مستخدمو المدرسة</h2>
                            <p class="text-sm text-slate-400">أضف أو حدّث المستخدمين مع ربطهم بالإدارة والدور والصلاحيات المناسبة.</p>
                        </div>
                    </div>

                    <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="openCreateUserModal">
                        <Plus class="h-4 w-4" />
                        <span>إضافة مستخدم جديد</span>
                    </button>
                </div>

                <div class="ui-filter-bar manager-structure-form mt-5">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <div class="space-y-2">
                            <label class="manager-structure-label">بحث سريع</label>
                            <AppSearchField
                                v-model="userFilters.search"
                                input-class="manager-structure-input"
                                placeholder="ابحث بالاسم أو البريد أو الجوال أو الإدارة"
                                aria-label="بحث سريع في مستخدمي المدرسة"
                            />
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">الإدارة</label>
                            <select v-model="userFilters.departmentId" class="ui-select manager-structure-input">
                                <option value="all">كل الإدارات</option>
                                <option v-for="department in departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">نوع الكادر</label>
                            <select v-model="userFilters.staffType" class="ui-select manager-structure-input">
                                <option value="all">كل الأنواع</option>
                                <option value="ADMINISTRATIVE">إداري</option>
                                <option value="EDUCATIONAL">تعليمي</option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="ui-secondary-button manager-structure-secondary-button self-end"
                            :disabled="!hasActiveUserFilters"
                            @click="clearUserFilters"
                        >
                            <span>مسح الفلاتر</span>
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="manager-structure-chip manager-structure-chip--blue">إجمالي المستخدمين: {{ users.length }}</span>
                        <span class="manager-structure-chip manager-structure-chip--slate">نتائج الفلترة: {{ filteredUsers.length }}</span>
                        <span v-if="hasActiveUserFilters" class="manager-structure-chip manager-structure-chip--cyan">فلاتر مفعلة: {{ activeUserFilterCount }}</span>
                    </div>

                    <AppInlineAlert
                        v-if="assignableDepartmentsForUserForm.length === 0"
                        variant="warning"
                        class="mt-4"
                        message="لا يمكن إنشاء مستخدمين قبل تعريف الإدارات والأدوار المعتمدة."
                    />
                </div>

                <div
                    v-if="isUserModalOpen"
                    class="manager-structure-modal-shell"
                    @click.self="closeUserModal"
                >
                    <div class="manager-structure-modal-panel">
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-4">
                            <div class="space-y-1">
                                <p class="manager-structure-eyebrow">إدارة المستخدمين</p>
                                <h3 class="text-xl font-black text-white">{{ userEditId ? 'تعديل المستخدم' : 'إضافة مستخدم جديد' }}</h3>
                                <p class="text-sm leading-7 text-slate-400">
                                    اربط المستخدم بالإدارة والدور والصلاحيات التشغيلية المناسبة داخل نفس المدرسة فقط.
                                </p>
                            </div>

                            <button type="button" class="manager-structure-modal-close" aria-label="إغلاق نافذة المستخدم" @click="closeUserModal">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <form class="ui-form-shell manager-structure-form mt-5" @submit.prevent="submitUser">
                    <div class="mb-4 space-y-3 md:col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-white">مرفقات المستخدم</p>
                                <p class="text-xs text-slate-400">
                                    يمكنك رفع صورة الهوية الوطنية أو الإقامة أو أي وثائق تعريفية خاصة بالمعلم أو المستخدم الإداري.
                                </p>
                            </div>
                            <button
                                v-if="pendingUserAttachments.length > 0"
                                type="button"
                                class="manager-structure-secondary-button !px-3 !py-1.5 !text-xs"
                                @click="clearPendingUserAttachments"
                            >
                                مسح الملفات المختارة
                            </button>
                        </div>

                        <AttachmentPanel
                            title="وثائق المستخدم"
                            helper-text="هذه المرفقات تبقى داخل نطاق المدرسة الحالية، ويمكن استخدامها لحفظ الهوية الوطنية أو المستندات الوظيفية."
                            :existing-attachments="selectedManagedUserAttachments"
                            :pending-files="pendingUserAttachments"
                            :errors="userAttachmentErrors"
                            pending-title="مرفقات ستُحفظ مع بيانات المستخدم"
                            existing-title="المرفقات الحالية للمستخدم"
                            :busy="apiProcessing || userForm.processing"
                            @select-files="appendUserAttachmentFiles"
                            @remove-pending="removePendingUserAttachment"
                            @delete-existing="deleteUserAttachment"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="manager-structure-label">الاسم</label>
                            <input v-model="userForm.name" class="ui-input manager-structure-input" />
                            <p v-if="userForm.errors.name" class="manager-structure-error">{{ userForm.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">البريد الإلكتروني</label>
                            <input v-model="userForm.email" type="email" class="ui-input manager-structure-input" />
                            <p v-if="userForm.errors.email" class="manager-structure-error">{{ userForm.errors.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">الجوال</label>
                            <input v-model="userForm.mobile" class="ui-input manager-structure-input" inputmode="tel" placeholder="05xxxxxxxx أو +9665xxxxxxxx" />
                            <p v-if="userForm.errors.mobile" class="manager-structure-error">{{ userForm.errors.mobile }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">الإدارة</label>
                            <select v-model="userForm.department_id" class="ui-select manager-structure-input">
                                <option value="" disabled>اختر الإدارة</option>
                                <option v-for="department in assignableDepartmentsForUserForm" :key="department.id" :value="department.id">{{ department.name }}</option>
                            </select>
                            <p v-if="userForm.errors.department_id" class="manager-structure-error">{{ userForm.errors.department_id }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">نوع الكادر</label>
                            <input :value="selectedDepartmentStaffTypeLabel" disabled class="ui-input manager-structure-input opacity-70" />
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">الدور الإداري</label>
                            <select v-model="userForm.department_role_id" class="ui-select manager-structure-input" :disabled="departmentRoleOptions.length === 0">
                                <option value="" disabled>اختر الدور</option>
                                <option v-for="roleItem in departmentRoleOptions" :key="roleItem.id" :value="roleItem.id">
                                    {{ departmentRoleLabel(roleItem) }}{{ roleItem.is_legacy_inactive ? ' (معطل)' : '' }}
                                </option>
                            </select>
                            <p v-if="userForm.errors.department_role_id" class="manager-structure-error">{{ userForm.errors.department_role_id }}</p>
                        </div>

                        <div v-if="canUseRoleAssignment" class="space-y-4 md:col-span-2">
                            <div class="ui-card-soft manager-structure-card-soft">
                                <div class="manager-structure-section-title">
                                    <div class="manager-structure-icon-box manager-structure-icon-box--violet"><Sparkles class="h-4 w-4" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-white">قوالب التفويض الجاهزة</p>
                                        <p class="text-xs text-slate-400">ابدأ بقالب قريب من دور الموظف ثم عدّل الصلاحيات يدويًا عند الحاجة.</p>
                                    </div>
                                </div>
                                <div v-if="normalizedDelegationTemplates.length > 0" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <button
                                        v-for="template in normalizedDelegationTemplates"
                                        :key="template.key"
                                        type="button"
                                        class="manager-structure-option-card text-start"
                                        :class="{ 'manager-structure-option-card--active': selectedDelegationTemplateKey === template.key }"
                                        @click="applyDelegationTemplate(template.key)"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="manager-structure-option-title">{{ template.label }}</span>
                                                <span :class="permissionBadgeClass(template.tone || 'slate')">{{ template.permission_count || 0 }} صلاحية</span>
                                                <span class="manager-structure-chip manager-structure-chip--slate">{{ template.module_count || 0 }} وحدة</span>
                                            </span>
                                            <span class="mt-1 block text-xs leading-6 text-slate-400">{{ template.description }}</span>
                                        </span>
                                    </button>
                                </div>
                                <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد قوالب تفويض جاهزة متاحة حاليًا.</div>
                            </div>

                            <div class="ui-card-soft manager-structure-card-soft">
                                <div class="manager-structure-section-title">
                                    <div class="manager-structure-icon-box manager-structure-icon-box--emerald"><UserCog class="h-4 w-4" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-white">الأدوار العامة للمستخدم</p>
                                        <p class="text-xs text-slate-400">يحصل كل مستخدم على دور فريق المدرسة تلقائيًا، ويمكن إضافة أدوار إضافية عند الحاجة.</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span :class="roleBadgeClass(mandatoryRoleName)">{{ roleLabel(mandatoryRoleName) }}</span>
                                    <span class="manager-structure-chip manager-structure-chip--slate">أدوار إضافية مفعلة: {{ selectedOptionalRoleCount }}</span>
                                </div>
                                <div v-if="optionalAssignableRoles.length > 0" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <label v-for="role in optionalAssignableRoles" :key="role.name" class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': isRoleSelected(role.name) }">
                                        <input type="checkbox" class="manager-structure-checkbox" :checked="isRoleSelected(role.name)" @change="toggleRoleName(role.name)" />
                                        <span class="min-w-0 flex-1">
                                            <span class="manager-structure-option-title">{{ roleLabel(role.name) }}</span>
                                            <span class="manager-structure-option-description">{{ role.description || 'دور إضافي يكمّل مسؤوليات المستخدم داخل المدرسة.' }}</span>
                                        </span>
                                    </label>
                                </div>
                                <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد أدوار إضافية متاحة للإسناد حاليًا.</div>
                                <p v-if="userForm.errors.role_names" class="manager-structure-error mt-3">{{ userForm.errors.role_names }}</p>
                            </div>

                            <div class="ui-card-soft manager-structure-card-soft">
                                <div class="manager-structure-section-title">
                                    <div class="manager-structure-icon-box manager-structure-icon-box--cyan"><Users class="h-4 w-4" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-white">مجموعات الصلاحيات المدرسية</p>
                                        <p class="text-xs text-slate-400">اختر مجموعة واحدة أو أكثر لإسناد حزمة تشغيلية جاهزة للمستخدم داخل نفس المدرسة.</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="manager-structure-chip manager-structure-chip--blue">المجموعات المحددة: {{ selectedPermissionGroupCount }}</span>
                                    <span class="manager-structure-chip manager-structure-chip--slate">المتاح داخل المدرسة: {{ schoolPermissionGroupCount }}</span>
                                </div>
                                <div v-if="normalizedSchoolPermissionGroups.length > 0" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <label v-for="group in normalizedSchoolPermissionGroups" :key="`user-group-${group.id}`" class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': isSchoolPermissionGroupSelected(group.id) }">
                                        <input type="checkbox" class="manager-structure-checkbox" :checked="isSchoolPermissionGroupSelected(group.id)" @change="toggleSchoolPermissionGroupId(group.id)" />
                                        <span class="min-w-0 flex-1">
                                            <span class="manager-structure-option-title">{{ group.name }}</span>
                                            <span class="manager-structure-option-description">{{ group.group_type_label }} • {{ group.permissions.length }} صلاحية • {{ group.users_count }} مستخدم</span>
                                        </span>
                                    </label>
                                </div>
                                <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد مجموعات صلاحيات جاهزة للإسناد بعد. أنشئ مجموعة أولًا ثم أعد فتح هذه النافذة.</div>
                                <p v-if="userForm.errors.school_permission_group_ids" class="manager-structure-error mt-3">{{ userForm.errors.school_permission_group_ids }}</p>
                            </div>

                            <div class="ui-card-soft manager-structure-card-soft">
                                <div class="manager-structure-section-title">
                                    <div class="manager-structure-icon-box manager-structure-icon-box--blue"><ShieldCheck class="h-4 w-4" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-white">الصلاحيات التشغيلية المباشرة</p>
                                        <p class="text-xs text-slate-400">استخدمها فقط عند الحاجة لتخصيص إضافي خارج مجموعات الصلاحيات الجاهزة.</p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="manager-structure-chip manager-structure-chip--blue">الصلاحيات المحددة: {{ selectedPermissionCount }}</span>
                                    <span class="manager-structure-chip manager-structure-chip--slate">ضمن نفس نطاق المدرسة فقط</span>
                                    <span class="manager-structure-chip manager-structure-chip--emerald">الوحدات المغطاة: {{ selectedDelegationModuleCount }}</span>
                                </div>
                                <AppInlineAlert
                                    v-if="selectedSensitivePermissions.length > 0"
                                    variant="warning"
                                    class="mt-4"
                                    :message="`يتضمن التفويض الحالي ${selectedSensitivePermissions.length} صلاحية حساسة. راجع الاختيار قبل الحفظ.`"
                                />
                                <div v-if="normalizedPermissionGroups.length > 0" class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                                    <article v-for="group in normalizedPermissionGroups" :key="group.key" class="manager-structure-permission-group">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <p class="manager-structure-option-title text-white">{{ group.label }}</p>
                                                <p class="manager-structure-option-description">{{ group.description }}</p>
                                            </div>
                                            <button type="button" class="manager-structure-pill-button" @click="setGroupPermissions(group, !areAllPermissionsSelected(group))">
                                                {{ areAllPermissionsSelected(group) ? 'إلغاء المجموعة' : 'تفعيل المجموعة' }}
                                            </button>
                                        </div>
                                        <div class="mt-4 grid grid-cols-1 gap-2">
                                            <label v-for="permission in group.permissions" :key="permission.name" class="manager-structure-option-card" :class="{ 'manager-structure-option-card--active': isPermissionSelected(permission.name) }">
                                                <input type="checkbox" class="manager-structure-checkbox" :checked="isPermissionSelected(permission.name)" @change="togglePermissionName(permission.name)" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex flex-wrap items-center gap-2">
                                                        <span class="manager-structure-option-title">{{ permission.label }}</span>
                                                        <span v-if="isSensitivePermission(permission.name)" class="manager-structure-chip manager-structure-chip--red">حساسة</span>
                                                    </span>
                                                    <span class="manager-structure-option-description">{{ permission.description }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    </article>
                                </div>
                                <div v-else class="manager-structure-empty manager-structure-empty--inline mt-4">لا توجد صلاحيات تشغيلية جاهزة للإسناد حاليًا.</div>
                                <p v-if="userForm.errors.permission_names" class="manager-structure-error mt-3">{{ userForm.errors.permission_names }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">كلمة المرور {{ userEditId ? '(اختياري)' : '' }}</label>
                            <input v-model="userForm.password" type="password" class="ui-input manager-structure-input" />
                            <p v-if="userForm.errors.password" class="manager-structure-error">{{ userForm.errors.password }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="manager-structure-label">تأكيد كلمة المرور</label>
                            <input v-model="userForm.password_confirmation" type="password" class="ui-input manager-structure-input" />
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="button" class="ui-secondary-button manager-structure-secondary-button" @click="closeUserModal">إلغاء</button>
                        <button class="ui-primary-button manager-structure-primary-button" :disabled="apiProcessing || userForm.processing || assignableDepartmentsForUserForm.length === 0 || departmentRoleOptions.length === 0">
                            {{ userEditId ? 'تحديث المستخدم' : 'إضافة مستخدم' }}
                        </button>
                    </div>

                    <p v-if="userForm.errors.general" class="manager-structure-error mt-3">{{ userForm.errors.general }}</p>
                    <p v-if="assignableDepartmentsForUserForm.length === 0" class="mt-3 text-sm text-amber-200">لا يمكن إنشاء مستخدمين قبل تعريف الإدارات والأدوار المعتمدة.</p>
                    <p v-else-if="departmentRoleOptions.length === 0" class="mt-3 text-sm text-amber-200">الإدارة المختارة لا تحتوي أدوارًا مفعلة.</p>
                </form>
                    </div>
                </div>

                <div v-if="delegationAuditEntries.length > 0" class="ui-card-soft manager-structure-card-soft mt-6">
                    <div class="manager-structure-section-title">
                        <div class="manager-structure-icon-box manager-structure-icon-box--amber"><ShieldCheck class="h-4 w-4" /></div>
                        <div>
                            <p class="text-sm font-bold text-white">سجل تغييرات التفويضات</p>
                            <p class="text-xs text-slate-400">آخر التحديثات التي تمت على تفويضات المستخدمين ومجموعات الصلاحيات داخل المدرسة.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <article v-for="entry in delegationAuditEntries" :key="`delegation-audit-${entry.id}`" class="manager-structure-subcard !p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-white">{{ entry.title }}</p>
                                        <span class="manager-structure-chip manager-structure-chip--slate">{{ entry.changed_count || 0 }} تغيير</span>
                                    </div>
                                    <p class="text-xs leading-6 text-slate-300">{{ entry.description }}</p>
                                    <p class="text-[11px] text-slate-500">{{ entry.actor_name }} • {{ formatDelegationAuditDate(entry.created_at) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    <article v-for="user in filteredUsers" :key="user.id" class="manager-structure-user-card">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="manager-structure-avatar">{{ userCardInitial(user) }}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-base font-bold text-white">{{ user.name }}</p>
                                        <span :class="staffTypeBadgeClass(user.school_staff_type)">{{ staffTypeLabel(user.school_staff_type) }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-300">{{ user.email || 'لا يوجد بريد إلكتروني' }}</p>
                                    <p class="text-xs text-slate-400">{{ user.mobile || 'لا يوجد رقم جوال' }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ user.department?.name || '-' }} | {{ user.department_role?.name || '-' }}</p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span v-for="badge in userRoleBadges(user)" :key="`${user.id}-${badge.name}`" :class="roleBadgeClass(badge.name)">{{ badge.label }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="manager-structure-secondary-button !px-4 !py-2 !text-sm" @click="startUserEdit(user)">تعديل</button>
                                <button type="button" class="manager-structure-inline-danger" @click="removeUser(user.id)"><Trash2 class="h-3.5 w-3.5" /><span>حذف</span></button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="badge in userSchoolPermissionGroupBadges(user)" :key="`${user.id}-group-${badge.id}`" :class="permissionBadgeClass(badge.tone)">{{ badge.label }}</span>
                            <span v-if="userSchoolPermissionGroupBadges(user).length === 0" class="manager-structure-chip manager-structure-chip--slate">بدون مجموعات صلاحيات</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="badge in userPermissionBadges(user)" :key="`${user.id}-${badge.name}`" :class="permissionBadgeClass(badge.tone)">{{ badge.label }}</span>
                            <span v-if="userPermissionBadges(user).length === 0" class="manager-structure-chip manager-structure-chip--slate">بدون صلاحيات تشغيلية إضافية</span>
                        </div>
                    </article>

                    <AppStatePanel
                        v-if="users.length === 0"
                        class="manager-structure-empty"
                        title="لا يوجد مستخدمون حالياً"
                        description="ابدأ بإضافة أول مستخدم ضمن الهيكل المدرسي."
                    />
                    <AppStatePanel
                        v-else-if="filteredUsers.length === 0"
                        class="manager-structure-empty"
                        variant="no-results"
                        title="لا توجد نتائج مطابقة للفلاتر الحالية"
                        description="جرّب توسيع البحث أو مسح الفلاتر."
                    />
                </div>
            </section>
        </div>
        </div>
    </RoleLayout>
</template>

<style scoped>
.manager-structure-hero,
.manager-structure-card,
.manager-structure-subcard,
.manager-structure-form,
.manager-structure-user-card,
.manager-structure-empty,
.manager-structure-permission-group,
.manager-structure-card-soft {
    border: 1px solid var(--ui-border-soft);
    border-radius: 1.4rem;
    background:
        linear-gradient(180deg, var(--ui-surface-1), var(--ui-surface-2)),
        radial-gradient(120% 80% at 100% 0%, var(--ui-accent-soft), transparent 70%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), var(--ui-shadow-soft);
}

.manager-structure-hero,
.manager-structure-card,
.manager-structure-form {
    padding: 1.35rem;
}

.manager-structure-subcard,
.manager-structure-user-card,
.manager-structure-empty,
.manager-structure-permission-group,
.manager-structure-card-soft {
    padding: 1rem;
}

.manager-structure-section-title {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
}

.manager-structure-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 1rem;
    border: 1px solid rgba(125, 211, 252, 0.22);
}

.manager-structure-icon-box--cyan {
    color: rgb(186 230 253);
    background: linear-gradient(180deg, rgba(14, 116, 144, 0.42), rgba(8, 47, 73, 0.58));
}

.manager-structure-icon-box--emerald {
    color: rgb(209 250 229);
    background: linear-gradient(180deg, rgba(5, 150, 105, 0.42), rgba(6, 78, 59, 0.58));
}

.manager-structure-icon-box--violet {
    color: rgb(237 233 254);
    background: linear-gradient(180deg, rgba(124, 58, 237, 0.42), rgba(76, 29, 149, 0.58));
}

.manager-structure-icon-box--blue {
    color: rgb(219 234 254);
    background: linear-gradient(180deg, rgba(37, 99, 235, 0.42), rgba(30, 64, 175, 0.58));
}

.manager-structure-eyebrow {
    margin-bottom: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    color: color-mix(in srgb, var(--ui-accent) 82%, white);
}

.manager-structure-label,
.manager-structure-stat-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ui-text-muted);
}

.manager-structure-stat-value {
    margin-top: 0.55rem;
    font-size: 2rem;
    line-height: 1;
    font-weight: 900;
    color: var(--ui-text-primary);
}

.manager-structure-input {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid var(--ui-border-soft);
    background: color-mix(in srgb, var(--ui-surface-3) 92%, transparent);
    padding: 0.8rem 0.95rem;
    color: var(--ui-text-primary);
}

.manager-structure-input:focus {
    outline: none;
    border-color: color-mix(in srgb, var(--ui-accent) 55%, var(--ui-border-strong));
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--ui-accent) 16%, transparent);
}

.manager-structure-primary-button,
.manager-structure-secondary-button,
.manager-structure-inline-danger,
.manager-structure-pill-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: 1rem;
    padding: 0.8rem 1rem;
    font-weight: 800;
    transition: transform 180ms ease, opacity 180ms ease;
}

.manager-structure-primary-button {
    background: linear-gradient(135deg, var(--ui-accent-strong), var(--ui-accent));
    color: #fff;
}

.manager-structure-secondary-button,
.manager-structure-pill-button {
    border: 1px solid color-mix(in srgb, var(--ui-accent) 32%, var(--ui-border-soft));
    background: color-mix(in srgb, var(--ui-accent) 9%, transparent);
    color: var(--ui-accent-strong);
}

.manager-structure-inline-danger {
    border: 1px solid color-mix(in srgb, var(--ui-danger) 30%, var(--ui-border-soft));
    background: var(--ui-danger-soft);
    color: var(--ui-danger);
}

.manager-structure-pill-button {
    padding: 0.55rem 0.8rem;
    font-size: 0.75rem;
}

.manager-structure-primary-button:hover:not(:disabled),
.manager-structure-secondary-button:hover:not(:disabled),
.manager-structure-inline-danger:hover:not(:disabled),
.manager-structure-pill-button:hover:not(:disabled) {
    transform: translateY(-1px);
}

.manager-structure-primary-button:disabled,
.manager-structure-secondary-button:disabled,
.manager-structure-inline-danger:disabled,
.manager-structure-pill-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.manager-structure-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border-radius: 9999px;
    border: 1px solid transparent;
    padding: 0.4rem 0.8rem;
    font-size: 0.73rem;
    font-weight: 700;
}

.manager-structure-chip--blue { border-color: rgba(96, 165, 250, 0.28); background: rgba(59, 130, 246, 0.16); color: rgb(191 219 254); }
.manager-structure-chip--emerald { border-color: rgba(52, 211, 153, 0.24); background: rgba(16, 185, 129, 0.16); color: rgb(209 250 229); }
.manager-structure-chip--amber { border-color: rgba(251, 191, 36, 0.25); background: rgba(245, 158, 11, 0.16); color: rgb(254 243 199); }
.manager-structure-chip--indigo { border-color: rgba(129, 140, 248, 0.24); background: rgba(99, 102, 241, 0.16); color: rgb(224 231 255); }
.manager-structure-chip--fuchsia { border-color: rgba(232, 121, 249, 0.24); background: rgba(192, 38, 211, 0.14); color: rgb(250 232 255); }
.manager-structure-chip--cyan { border-color: rgba(103, 232, 249, 0.24); background: rgba(8, 145, 178, 0.18); color: rgb(207 250 254); }
.manager-structure-chip--violet { border-color: rgba(167, 139, 250, 0.24); background: rgba(139, 92, 246, 0.16); color: rgb(237 233 254); }
.manager-structure-chip--orange { border-color: rgba(251, 146, 60, 0.24); background: rgba(234, 88, 12, 0.16); color: rgb(255 237 213); }
.manager-structure-chip--pink { border-color: rgba(244, 114, 182, 0.24); background: rgba(219, 39, 119, 0.14); color: rgb(252 231 243); }
.manager-structure-chip--purple { border-color: rgba(192, 132, 252, 0.24); background: rgba(147, 51, 234, 0.14); color: rgb(243 232 255); }
.manager-structure-chip--red { border-color: rgba(248, 113, 113, 0.24); background: rgba(127, 29, 29, 0.34); color: rgb(254 226 226); }
.manager-structure-chip--zinc { border-color: rgba(161, 161, 170, 0.24); background: rgba(63, 63, 70, 0.34); color: rgb(244 244 245); }
.manager-structure-chip--slate { border-color: rgba(148, 163, 184, 0.24); background: rgba(51, 65, 85, 0.36); color: rgb(226 232 240); }

.manager-structure-option-card {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border-soft);
    background: linear-gradient(180deg, color-mix(in srgb, var(--ui-surface-2) 94%, transparent), color-mix(in srgb, var(--ui-surface-3) 96%, transparent));
    padding: 0.95rem;
}

.manager-structure-option-card--active {
    border-color: color-mix(in srgb, var(--ui-accent) 32%, var(--ui-border-soft));
    background: color-mix(in srgb, var(--ui-accent) 10%, transparent);
}

.manager-structure-option-title {
    display: block;
    font-size: 0.86rem;
    font-weight: 800;
    line-height: 1.6;
    color: var(--ui-text-primary);
}

.manager-structure-option-description {
    display: block;
    margin-top: 0.2rem;
    font-size: 0.76rem;
    line-height: 1.7;
    color: var(--ui-text-muted);
}

.manager-structure-checkbox {
    position: relative;
    appearance: none;
    -webkit-appearance: none;
    margin-top: 0.2rem;
    width: 1.15rem;
    height: 1.15rem;
    flex-shrink: 0;
    border-radius: 0.45rem;
    border: 1px solid rgba(100, 116, 139, 0.8);
    background: rgba(15, 23, 42, 0.75);
    transition: border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
}

.manager-structure-checkbox::after {
    content: '✓';
    position: absolute;
    inset: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.78rem;
    font-weight: 900;
    opacity: 0;
    transform: scale(0.75);
    transition: opacity 160ms ease, transform 160ms ease;
}

.manager-structure-checkbox:hover {
    border-color: color-mix(in srgb, var(--ui-accent) 42%, rgba(100, 116, 139, 0.8));
}

.manager-structure-checkbox:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-accent) 24%, transparent);
}

.manager-structure-checkbox:checked {
    border-color: color-mix(in srgb, var(--ui-accent) 70%, rgba(59, 130, 246, 0.92));
    background: linear-gradient(180deg, color-mix(in srgb, var(--ui-accent) 82%, rgba(59, 130, 246, 1)), color-mix(in srgb, var(--ui-accent) 64%, rgba(37, 99, 235, 1)));
}

.manager-structure-checkbox:checked::after {
    opacity: 1;
    transform: scale(1);
}

.manager-structure-error {
    font-size: 0.74rem;
    color: var(--ui-danger);
}

.manager-structure-empty {
    color: var(--ui-text-muted);
}

.manager-structure-empty--inline {
    border: 1px dashed var(--ui-border-soft);
    background: color-mix(in srgb, var(--ui-surface-2) 88%, transparent);
}

.manager-structure-avatar {
    display: inline-flex;
    width: 3rem;
    height: 3rem;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid rgba(125, 211, 252, 0.26);
    background: linear-gradient(180deg, rgba(14, 116, 144, 0.42), rgba(8, 47, 73, 0.58));
    color: white;
    font-weight: 900;
}

.manager-structure-search-icon {
    position: absolute;
    top: 50%;
    right: 0.95rem;
    transform: translateY(-50%);
    color: var(--ui-text-muted);
}

.manager-structure-modal-shell {
    position: fixed;
    inset: 0;
    z-index: 70;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(2, 6, 23, 0.82);
    backdrop-filter: blur(12px);
}

.manager-structure-modal-panel {
    width: min(100%, 1100px);
    max-height: 92vh;
    overflow-y: auto;
    border: 1px solid var(--ui-border-soft);
    border-radius: 1.6rem;
    background:
        linear-gradient(180deg, var(--ui-surface-1), var(--ui-surface-2)),
        radial-gradient(120% 80% at 100% 0%, color-mix(in srgb, var(--ui-accent) 16%, transparent), transparent 70%);
    box-shadow: 0 30px 80px rgba(2, 6, 23, 0.32);
    padding: 1.25rem;
}

.manager-structure-modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.95rem;
    border: 1px solid var(--ui-border-soft);
    background: color-mix(in srgb, var(--ui-surface-2) 92%, transparent);
    color: var(--ui-text-secondary);
    transition: transform 180ms ease, background-color 180ms ease, border-color 180ms ease;
}

.manager-structure-modal-close:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ui-accent) 32%, var(--ui-border-soft));
    background: color-mix(in srgb, var(--ui-accent) 10%, transparent);
}

.manager-structure-shell--light .manager-structure-hero,
.manager-structure-shell--light .manager-structure-card,
.manager-structure-shell--light .manager-structure-subcard,
.manager-structure-shell--light .manager-structure-form,
.manager-structure-shell--light .manager-structure-user-card,
.manager-structure-shell--light .manager-structure-empty,
.manager-structure-shell--light .manager-structure-permission-group,
.manager-structure-shell--light .manager-structure-card-soft,
:global(.role-layout--light) .manager-structure-hero,
:global(.role-layout--light) .manager-structure-card,
:global(.role-layout--light) .manager-structure-subcard,
:global(.role-layout--light) .manager-structure-form,
:global(.role-layout--light) .manager-structure-user-card,
:global(.role-layout--light) .manager-structure-empty,
:global(.role-layout--light) .manager-structure-permission-group,
:global(.role-layout--light) .manager-structure-card-soft,
:global(html.theme-light) .manager-structure-hero,
:global(html.theme-light) .manager-structure-card,
:global(html.theme-light) .manager-structure-subcard,
:global(html.theme-light) .manager-structure-form,
:global(html.theme-light) .manager-structure-user-card,
:global(html.theme-light) .manager-structure-empty,
:global(html.theme-light) .manager-structure-permission-group,
:global(html.theme-light) .manager-structure-card-soft,
:global(html[data-theme='light']) .manager-structure-hero,
:global(html[data-theme='light']) .manager-structure-card,
:global(html[data-theme='light']) .manager-structure-subcard,
:global(html[data-theme='light']) .manager-structure-form,
:global(html[data-theme='light']) .manager-structure-user-card,
:global(html[data-theme='light']) .manager-structure-empty,
:global(html[data-theme='light']) .manager-structure-permission-group,
:global(html[data-theme='light']) .manager-structure-card-soft {
    border-color: rgba(148, 163, 184, 0.35) !important;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.95)) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), 0 18px 36px rgba(15, 23, 42, 0.08);
}

.manager-structure-shell--light .manager-structure-option-card,
.manager-structure-shell--light .manager-structure-input,
.manager-structure-shell--light .manager-structure-pill-button,
.manager-structure-shell--light .manager-structure-secondary-button,
.manager-structure-shell--light .manager-structure-modal-panel,
.manager-structure-shell--light .manager-structure-modal-close,
:global(.role-layout--light) .manager-structure-option-card,
:global(.role-layout--light) .manager-structure-input,
:global(.role-layout--light) .manager-structure-pill-button,
:global(.role-layout--light) .manager-structure-secondary-button,
:global(.role-layout--light) .manager-structure-modal-panel,
:global(.role-layout--light) .manager-structure-modal-close,
:global(html.theme-light) .manager-structure-option-card,
:global(html.theme-light) .manager-structure-input,
:global(html.theme-light) .manager-structure-pill-button,
:global(html.theme-light) .manager-structure-secondary-button,
:global(html.theme-light) .manager-structure-modal-panel,
:global(html.theme-light) .manager-structure-modal-close,
:global(html[data-theme='light']) .manager-structure-option-card,
:global(html[data-theme='light']) .manager-structure-input,
:global(html[data-theme='light']) .manager-structure-pill-button,
:global(html[data-theme='light']) .manager-structure-secondary-button,
:global(html[data-theme='light']) .manager-structure-modal-panel,
:global(html[data-theme='light']) .manager-structure-modal-close {
    border-color: rgba(148, 163, 184, 0.38) !important;
    background: rgba(248, 250, 252, 0.94) !important;
    color: rgb(15 23 42) !important;
}

.manager-structure-shell--light .manager-structure-option-title,
.manager-structure-shell--light .manager-structure-stat-value,
.manager-structure-shell--light .manager-structure-card h2,
.manager-structure-shell--light .manager-structure-hero h2,
:global(.role-layout--light) .manager-structure-option-title,
:global(.role-layout--light) .manager-structure-stat-value,
:global(.role-layout--light) .manager-structure-card h2,
:global(.role-layout--light) .manager-structure-hero h2,
:global(html.theme-light) .manager-structure-option-title,
:global(html.theme-light) .manager-structure-stat-value,
:global(html.theme-light) .manager-structure-card h2,
:global(html.theme-light) .manager-structure-hero h2,
:global(html[data-theme='light']) .manager-structure-option-title,
:global(html[data-theme='light']) .manager-structure-stat-value,
:global(html[data-theme='light']) .manager-structure-card h2,
:global(html[data-theme='light']) .manager-structure-hero h2 {
    color: rgb(15 23 42) !important;
}

.manager-structure-shell--light .manager-structure-option-description,
.manager-structure-shell--light .manager-structure-label,
.manager-structure-shell--light .manager-structure-stat-label,
.manager-structure-shell--light .manager-structure-empty,
.manager-structure-shell--light .manager-structure-card p,
.manager-structure-shell--light .manager-structure-hero p,
:global(.role-layout--light) .manager-structure-option-description,
:global(.role-layout--light) .manager-structure-label,
:global(.role-layout--light) .manager-structure-stat-label,
:global(.role-layout--light) .manager-structure-empty,
:global(.role-layout--light) .manager-structure-card p,
:global(.role-layout--light) .manager-structure-hero p,
:global(html.theme-light) .manager-structure-option-description,
:global(html.theme-light) .manager-structure-label,
:global(html.theme-light) .manager-structure-stat-label,
:global(html.theme-light) .manager-structure-empty,
:global(html.theme-light) .manager-structure-card p,
:global(html.theme-light) .manager-structure-hero p,
:global(html[data-theme='light']) .manager-structure-option-description,
:global(html[data-theme='light']) .manager-structure-label,
:global(html[data-theme='light']) .manager-structure-stat-label,
:global(html[data-theme='light']) .manager-structure-empty,
:global(html[data-theme='light']) .manager-structure-card p,
:global(html[data-theme='light']) .manager-structure-hero p {
    color: rgb(71 85 105) !important;
}

.manager-structure-shell--light .text-white,
:global(.role-layout--light) .manager-structure-shell .text-white,
:global(html.theme-light) .manager-structure-shell .text-white,
:global(html[data-theme='light']) .manager-structure-shell .text-white {
    color: rgb(15 23 42) !important;
}

.manager-structure-shell--light .text-slate-300,
.manager-structure-shell--light .text-slate-400,
.manager-structure-shell--light .text-slate-500,
:global(.role-layout--light) .manager-structure-shell .text-slate-300,
:global(.role-layout--light) .manager-structure-shell .text-slate-400,
:global(.role-layout--light) .manager-structure-shell .text-slate-500,
:global(html.theme-light) .manager-structure-shell .text-slate-300,
:global(html.theme-light) .manager-structure-shell .text-slate-400,
:global(html.theme-light) .manager-structure-shell .text-slate-500,
:global(html[data-theme='light']) .manager-structure-shell .text-slate-300,
:global(html[data-theme='light']) .manager-structure-shell .text-slate-400,
:global(html[data-theme='light']) .manager-structure-shell .text-slate-500 {
    color: rgb(100 116 139) !important;
}

.manager-structure-shell--light .manager-structure-chip--blue,
:global(.role-layout--light) .manager-structure-shell .manager-structure-chip--blue,
:global(html.theme-light) .manager-structure-shell .manager-structure-chip--blue,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-chip--blue {
    border-color: rgba(96, 165, 250, 0.28) !important;
    background: rgba(219, 234, 254, 0.9) !important;
    color: rgb(29 78 216) !important;
}

.manager-structure-shell--light .manager-structure-chip--emerald,
:global(.role-layout--light) .manager-structure-shell .manager-structure-chip--emerald,
:global(html.theme-light) .manager-structure-shell .manager-structure-chip--emerald,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-chip--emerald {
    border-color: rgba(16, 185, 129, 0.24) !important;
    background: rgba(236, 253, 245, 0.92) !important;
    color: rgb(5 150 105) !important;
}

.manager-structure-shell--light .manager-structure-chip--cyan,
:global(.role-layout--light) .manager-structure-shell .manager-structure-chip--cyan,
:global(html.theme-light) .manager-structure-shell .manager-structure-chip--cyan,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-chip--cyan {
    border-color: rgba(34, 211, 238, 0.3) !important;
    background: rgba(236, 254, 255, 0.92) !important;
    color: rgb(14 116 144) !important;
}

.manager-structure-shell--light .manager-structure-chip--slate,
:global(.role-layout--light) .manager-structure-shell .manager-structure-chip--slate,
:global(html.theme-light) .manager-structure-shell .manager-structure-chip--slate,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-chip--slate {
    border-color: rgba(148, 163, 184, 0.28) !important;
    background: rgba(241, 245, 249, 0.94) !important;
    color: rgb(51 65 85) !important;
}

.manager-structure-shell--light .manager-structure-avatar,
:global(.role-layout--light) .manager-structure-shell .manager-structure-avatar,
:global(html.theme-light) .manager-structure-shell .manager-structure-avatar,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-avatar {
    border-color: rgba(59, 130, 246, 0.28) !important;
    background: linear-gradient(180deg, rgba(219, 234, 254, 0.96), rgba(191, 219, 254, 0.88)) !important;
    color: rgb(30 64 175) !important;
}

.manager-structure-shell--light .manager-structure-icon-box,
:global(.role-layout--light) .manager-structure-shell .manager-structure-icon-box,
:global(html.theme-light) .manager-structure-shell .manager-structure-icon-box,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-icon-box {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72), 0 12px 24px rgba(15, 23, 42, 0.06) !important;
}

.manager-structure-shell--light .manager-structure-option-card--active,
:global(.role-layout--light) .manager-structure-shell .manager-structure-option-card--active,
:global(html.theme-light) .manager-structure-shell .manager-structure-option-card--active,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-option-card--active {
    border-color: rgba(59, 130, 246, 0.4) !important;
    background: rgba(219, 234, 254, 0.62) !important;
}

.manager-structure-shell--light .manager-structure-checkbox,
:global(.role-layout--light) .manager-structure-shell .manager-structure-checkbox,
:global(html.theme-light) .manager-structure-shell .manager-structure-checkbox,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-checkbox {
    border-color: rgba(148, 163, 184, 0.52) !important;
    background: rgba(255, 255, 255, 0.98) !important;
}

.manager-structure-shell--light .manager-structure-checkbox:checked,
:global(.role-layout--light) .manager-structure-shell .manager-structure-checkbox:checked,
:global(html.theme-light) .manager-structure-shell .manager-structure-checkbox:checked,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-checkbox:checked {
    border-color: rgba(37, 99, 235, 0.64) !important;
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.92), rgba(37, 99, 235, 0.96)) !important;
}

.manager-structure-shell--light .manager-structure-modal-shell,
:global(.role-layout--light) .manager-structure-shell .manager-structure-modal-shell,
:global(html.theme-light) .manager-structure-shell .manager-structure-modal-shell,
:global(html[data-theme='light']) .manager-structure-shell .manager-structure-modal-shell {
    background: rgba(226, 232, 240, 0.72) !important;
}
</style>
