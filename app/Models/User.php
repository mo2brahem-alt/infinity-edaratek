<?php

namespace App\Models;

use App\Support\SchoolPermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    public const APPROVAL_PENDING = 'pending_approval';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';
    public const APPROVAL_REQUIRED_ROLES = ['school_manager', 'supervisor'];

    public const SCHOOL_STAFF_ADMINISTRATIVE = 'ADMINISTRATIVE';
    public const SCHOOL_STAFF_EDUCATIONAL = 'EDUCATIONAL';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'phone',
        'profile_photo_path',
        'role',
        'is_active',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'approval_notes',
        'department_id',
        'department_role_id',
        'school_id',
        'school_staff_type',
        'can_manage_student_structure',
        'can_manage_student_attendance',
        'can_manage_academic_planning',
        'can_manage_student_leaves',
        'can_manage_leave_types',
        'can_manage_school_calendar',
        'can_manage_school_holidays',
        'onboarding_region_id',
        'onboarding_completed_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'approval_status' => self::APPROVAL_APPROVED,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'can_manage_student_structure' => 'boolean',
            'can_manage_student_attendance' => 'boolean',
            'can_manage_academic_planning' => 'boolean',
            'can_manage_student_leaves' => 'boolean',
            'can_manage_leave_types' => 'boolean',
            'can_manage_school_calendar' => 'boolean',
            'can_manage_school_holidays' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function departmentRole()
    {
        return $this->belongsTo(DepartmentRole::class, 'department_role_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function schoolPermissionGroups()
    {
        return $this->belongsToMany(SchoolPermissionGroup::class, 'school_permission_group_user')
            ->withTimestamps();
    }

    public function onboardingRegion()
    {
        return $this->belongsTo(EducationalDirectorate::class, 'onboarding_region_id');
    }

    public function managedSchool()
    {
        return $this->hasOne(School::class, 'manager_user_id');
    }

    public function supervisedSchools()
    {
        return $this->hasMany(School::class, 'supervisor_id');
    }

    public function associationRequestsAsManager()
    {
        return $this->hasMany(AssociationRequest::class, 'manager_user_id');
    }

    public function associationRequestsAsSupervisor()
    {
        return $this->hasMany(AssociationRequest::class, 'supervisor_user_id');
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function createdSubtasks()
    {
        return $this->hasMany(Subtask::class, 'created_by');
    }

    public function assignedSubtasks()
    {
        return $this->hasMany(Subtask::class, 'assigned_to');
    }

    public function systemNotifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function teachingAssignments()
    {
        return $this->hasMany(SchoolSubjectTeacherAssignment::class, 'teacher_user_id');
    }

    public function taughtSubjects()
    {
        return $this->belongsToMany(SchoolSubject::class, 'school_subject_teacher_assignments', 'teacher_user_id', 'school_subject_id')
            ->withTimestamps();
    }

    public function classSchedulesAsTeacher()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'teacher_user_id');
    }

    public function weeklyAvailabilities()
    {
        return $this->hasMany(SchoolTeacherAvailability::class, 'teacher_user_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function courseTeachingAssignments()
    {
        return $this->hasMany(SchoolTeachingAssignment::class, 'teacher_user_id');
    }

    public function examsAsTeacher()
    {
        return $this->hasMany(SchoolExam::class, 'teacher_user_id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'user_id')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->latestOfMany();
    }

    public function supervisionRequestsAsSupervisor()
    {
        return $this->hasMany(SchoolSupervisionRequest::class, 'supervisor_id');
    }

    public function supervisionRequestsAsManager()
    {
        return $this->hasMany(SchoolSupervisionRequest::class, 'manager_id');
    }

    public function hasLegacyRole(string $role): bool
    {
        return ($this->role ?? null) === $role;
    }

    public function hasSystemRole(string $role): bool
    {
        return $this->hasRole($role) || $this->hasLegacyRole($role);
    }

    public function primaryRole(): ?string
    {
        $roleName = $this->roles()->orderBy('id')->value('name');

        return $roleName ?: ($this->role ?? null);
    }

    public function requiresSuperAdminApproval(): bool
    {
        return in_array((string) $this->primaryRole(), self::APPROVAL_REQUIRED_ROLES, true);
    }

    public function isPendingApproval(): bool
    {
        return (string) ($this->approval_status ?? self::APPROVAL_APPROVED) === self::APPROVAL_PENDING;
    }

    public function isRejectedApproval(): bool
    {
        return (string) ($this->approval_status ?? self::APPROVAL_APPROVED) === self::APPROVAL_REJECTED;
    }

    public function hasApprovedAccountAccess(): bool
    {
        if (! $this->requiresSuperAdminApproval()) {
            return (bool) $this->is_active;
        }

        return (bool) $this->is_active
            && (string) ($this->approval_status ?? self::APPROVAL_APPROVED) === self::APPROVAL_APPROVED;
    }

    public function approvalBlockedMessage(): string
    {
        if ($this->isPendingApproval()) {
            return 'حسابك قيد المراجعة حاليًا، وسيتم تفعيله بعد موافقة السوبر أدمن.';
        }

        if ($this->isRejectedApproval()) {
            return 'تم رفض طلب الانضمام لهذا الحساب. يرجى التواصل مع الإدارة العامة إذا كنت بحاجة إلى مراجعة الطلب.';
        }

        return 'هذا الحساب غير مفعّل حاليًا ولا يمكنه استخدام المنصة.';
    }

    public function canManageStudentStructure(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.student_structure.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_student_structure !== null) {
            return (bool) $this->can_manage_student_structure;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if ($this->relationLoaded('departmentRole')) {
            return (bool) optional($this->departmentRole)->can_manage_student_structure;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where('can_manage_student_structure', true)
            ->exists();
    }

    public function canManageStudentAttendance(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.student_attendance.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_student_attendance !== null) {
            return (bool) $this->can_manage_student_attendance;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if ($this->relationLoaded('departmentRole')) {
            return (bool) optional($this->departmentRole)->can_manage_student_attendance;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where('can_manage_student_attendance', true)
            ->exists();
    }

    public function canManageAcademicPlanning(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.academic_planning.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_academic_planning !== null) {
            return (bool) $this->can_manage_academic_planning;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if ($this->relationLoaded('departmentRole')) {
            return (bool) optional($this->departmentRole)->can_manage_academic_planning;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where('can_manage_academic_planning', true)
            ->exists();
    }

    public function canManageSchoolExams(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.exams.manage')) {
            return true;
        }

        if ($this->canManageAcademicPlanning()) {
            return true;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_EDUCATIONAL) {
            return false;
        }

        $schoolId = (int) ($this->school_id ?? 0);
        if ($schoolId <= 0 || !(bool) $this->is_active) {
            return false;
        }

        return SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', (int) $this->id)
            ->exists();
    }

    public function canManageSchoolReports(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if (
            $this->hasDelegatedSchoolPermission('school.reports.view')
            || $this->hasDelegatedSchoolPermission('school.reports.export')
        ) {
            return true;
        }

        return $this->legacySchoolReportsAccess();
    }

    public function canExportSchoolReports(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.reports.export')) {
            return true;
        }

        if ($this->hasDelegatedSchoolPermission('school.reports.view')) {
            return false;
        }

        return $this->legacySchoolReportsAccess();
    }

    public function canAccessCertificates(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        return $this->hasAnyDelegatedSchoolPermission([
            'certificates.view',
            'certificates.templates.view',
            'certificates.templates.create',
            'certificates.templates.update',
            'certificates.issue',
            'certificates.print',
            'certificates.cancel',
            'certificates.signatures.manage',
            'certificates.bulk_issue',
        ]);
    }

    public function canViewCertificateTemplates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasAnyDelegatedSchoolPermission(['certificates.view', 'certificates.templates.view']);
    }

    public function canCreateCertificateTemplates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.templates.create');
    }

    public function canUpdateCertificateTemplates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.templates.update');
    }

    public function canDeleteCertificateTemplates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.templates.delete');
    }

    public function canIssueCertificates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.issue');
    }

    public function canBulkIssueCertificates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.bulk_issue');
    }

    public function canPrintCertificates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasAnyDelegatedSchoolPermission(['certificates.print', 'certificates.issue']);
    }

    public function canCancelCertificates(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.cancel');
    }

    public function canManageCertificateSignatures(): bool
    {
        return $this->hasSystemRole('super_admin')
            || $this->hasSystemRole('school_manager')
            || $this->hasDelegatedSchoolPermission('certificates.signatures.manage');
    }

    public function canManageStudentLeaves(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.student_leaves.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_student_leaves !== null) {
            return (bool) $this->can_manage_student_leaves;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if ($this->relationLoaded('departmentRole')) {
            return (bool) optional($this->departmentRole)->can_manage_student_leaves;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where('can_manage_student_leaves', true)
            ->exists();
    }

    public function canManageLeaveTypes(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.leave_types.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_leave_types !== null) {
            return (bool) $this->can_manage_leave_types;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return $this->canManageStudentLeaves();
        }

        if ($this->relationLoaded('departmentRole')) {
            $canManageLeaveTypes = optional($this->departmentRole)->can_manage_leave_types;
            if ($canManageLeaveTypes !== null) {
                return (bool) $canManageLeaveTypes;
            }

            return (bool) optional($this->departmentRole)->can_manage_student_leaves;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('can_manage_leave_types', true)
                    ->orWhere('can_manage_student_leaves', true);
            })
            ->exists();
    }

    public function canManageSchoolCalendar(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.calendar.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_school_calendar !== null) {
            return (bool) $this->can_manage_school_calendar;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return false;
        }

        if ($this->relationLoaded('departmentRole')) {
            return (bool) optional($this->departmentRole)->can_manage_school_calendar;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where('can_manage_school_calendar', true)
            ->exists();
    }

    public function canManageSchoolHolidays(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        if ($this->hasDelegatedSchoolPermission('school.holidays.manage')) {
            return true;
        }

        if ($this->usesManagerAssignedStructurePermissions() && $this->can_manage_school_holidays !== null) {
            return (bool) $this->can_manage_school_holidays;
        }

        if (($this->school_staff_type ?? null) !== self::SCHOOL_STAFF_ADMINISTRATIVE) {
            return false;
        }

        $roleId = (int) ($this->department_role_id ?? 0);
        if ($roleId <= 0) {
            return $this->canManageStudentLeaves();
        }

        if ($this->relationLoaded('departmentRole')) {
            $canManageSchoolHolidays = optional($this->departmentRole)->can_manage_school_holidays;
            if ($canManageSchoolHolidays !== null) {
                return (bool) $canManageSchoolHolidays;
            }

            return (bool) optional($this->departmentRole)->can_manage_student_leaves;
        }

        return DepartmentRole::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('can_manage_school_holidays', true)
                    ->orWhere('can_manage_student_leaves', true);
            })
            ->exists();
    }

    public function canImportSchoolDefaultData(): bool
    {
        if ($this->hasSystemRole('super_admin') || $this->hasSystemRole('school_manager')) {
            return true;
        }

        if (!$this->hasSystemRole('staff')) {
            return false;
        }

        return $this->canManageStudentStructure()
            && $this->canManageAcademicPlanning()
            && $this->canManageLeaveTypes()
            && $this->canManageSchoolCalendar()
            && $this->canManageSchoolHolidays();
    }

    private function usesManagerAssignedStructurePermissions(): bool
    {
        return (bool) config('features.rbac.manager_assigns_structure_permissions', true);
    }

    /**
     * @return array<int, string>
     */
    public function directSchoolPermissionNames(): array
    {
        $columnBackedPermissionNames = collect(SchoolPermissionCatalog::permissionColumnMap())
            ->filter(fn (string $column): bool => (bool) ($this->{$column} ?? false));

        $catalogPermissionNames = SchoolPermissionCatalog::permissionNames();
        $spatiePermissionNames = ($this->relationLoaded('permissions')
            ? $this->permissions
            : $this->getDirectPermissions())
            ->pluck('name')
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter(fn (string $permissionName): bool => in_array($permissionName, $catalogPermissionNames, true));

        return $columnBackedPermissionNames
            ->keys()
            ->merge($spatiePermissionNames)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function groupedSchoolPermissionNames(): array
    {
        $loadedGroups = $this->relationLoaded('schoolPermissionGroups')
            ? $this->schoolPermissionGroups
            : $this->schoolPermissionGroups()->get(['school_permission_groups.id', 'school_permission_groups.permission_names']);

        return $loadedGroups
            ->flatMap(fn (SchoolPermissionGroup $group) => (array) ($group->permission_names ?? []))
            ->map(fn ($permissionName): string => trim((string) $permissionName))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function effectiveSchoolPermissionNames(): array
    {
        return collect($this->directSchoolPermissionNames())
            ->merge($this->groupedSchoolPermissionNames())
            ->unique()
            ->values()
            ->all();
    }

    private function hasDelegatedSchoolPermission(string $permissionName): bool
    {
        if ((int) ($this->school_id ?? 0) <= 0) {
            return false;
        }

        return in_array($permissionName, $this->effectiveSchoolPermissionNames(), true);
    }

    /**
     * @param array<int, string> $permissionNames
     */
    private function hasAnyDelegatedSchoolPermission(array $permissionNames): bool
    {
        $effective = $this->effectiveSchoolPermissionNames();

        foreach ($permissionNames as $permissionName) {
            if (in_array($permissionName, $effective, true)) {
                return true;
            }
        }

        return false;
    }

    private function legacySchoolReportsAccess(): bool
    {
        return $this->canManageStudentStructure()
            || $this->canManageStudentAttendance()
            || $this->canManageAcademicPlanning()
            || $this->canManageStudentLeaves()
            || $this->canManageLeaveTypes()
            || $this->canManageSchoolCalendar()
            || $this->canManageSchoolHolidays();
    }
}
