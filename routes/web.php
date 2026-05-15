<?php

use App\Http\Controllers\AssociationRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Api\System\SystemPermissionController;
use App\Http\Controllers\Api\System\SystemRoleController;
use App\Http\Controllers\Api\School\SchoolAssignableRoleController;
use App\Http\Controllers\Api\School\SchoolOrgStructureRoleController;
use App\Http\Controllers\Api\School\QuickSetupStatusController as ApiSchoolQuickSetupStatusController;
use App\Http\Controllers\Api\School\SchoolCalendarManagementController as ApiSchoolCalendarManagementController;
use App\Http\Controllers\Api\School\SchoolPermissionGroupController;
use App\Http\Controllers\Api\School\StudentLeaveManagementController as ApiSchoolStudentLeaveManagementController;
use App\Http\Controllers\Api\School\SchoolUserManagementController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SchoolDefaultDataController as AdminSchoolDefaultDataController;
use App\Http\Controllers\Admin\SubscriptionManagementController;
use App\Http\Controllers\Admin\SupervisorAssignmentController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Manager\DepartmentController as ManagerDepartmentController;
use App\Http\Controllers\Manager\OnboardingController as ManagerOnboardingController;
use App\Http\Controllers\Manager\RequestController as ManagerRequestController;
use App\Http\Controllers\Manager\SchoolStructureController as ManagerSchoolStructureController;
use App\Http\Controllers\Manager\SchoolUserController as ManagerSchoolUserController;
use App\Http\Controllers\Manager\SubtaskController as ManagerSubtaskController;
use App\Http\Controllers\Manager\TicketController as ManagerTicketController;
use App\Http\Controllers\School\StudentAttendanceController as SchoolStudentAttendanceController;
use App\Http\Controllers\School\StudentLeaveController as SchoolStudentLeaveController;
use App\Http\Controllers\School\AcademicPlanningController as SchoolAcademicPlanningController;
use App\Http\Controllers\School\SchoolAttachmentController as SchoolAttachmentController;
use App\Http\Controllers\School\DefaultDataImportController as SchoolDefaultDataImportController;
use App\Http\Controllers\School\StudentStructureController as SchoolStudentStructureController;
use App\Http\Controllers\School\ReportsController as SchoolReportsController;
use App\Http\Controllers\School\SchoolExamController as SchoolExamController;
use App\Http\Controllers\School\StudentCertificateController as SchoolStudentCertificateController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\SubtaskController as StaffSubtaskController;
use App\Http\Controllers\Supervisor\DashboardController as SupervisorDashboardController;
use App\Http\Controllers\Supervisor\OnboardingController as SupervisorOnboardingController;
use App\Http\Controllers\Supervisor\RequestController as SupervisorRequestController;
use App\Http\Controllers\Supervisor\TicketController as SupervisorTicketController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');
Route::get('/pricing', [PricingController::class, 'index'])->name('pricing.index');
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/media-files/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public.media_file.show');
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public.storage.show');
Route::get('/certificates/verify/{token}', CertificateVerificationController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('certificates.verify');

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class)->except(['create', 'show', 'edit']);
    Route::post('/users/{user}/approve', [App\Http\Controllers\Admin\UserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [App\Http\Controllers\Admin\UserController::class, 'reject'])->name('users.reject');
    Route::resource('departments', App\Http\Controllers\Admin\DepartmentController::class)->except(['create', 'show', 'edit']);
    Route::resource('roles', App\Http\Controllers\Admin\RoleController::class)->except(['create', 'show', 'edit']);
    Route::get('/org-structure-roles', [App\Http\Controllers\Admin\RoleController::class, 'indexOrgStructureRoles'])->name('admin.org_structure_roles.index');
    Route::post('/org-structure-roles', [App\Http\Controllers\Admin\RoleController::class, 'storeOrgStructureRole'])->name('admin.org_structure_roles.store');
    Route::match(['put', 'patch'], '/org-structure-roles/{orgStructureRoleTemplate}', [App\Http\Controllers\Admin\RoleController::class, 'updateOrgStructureRole'])->name('admin.org_structure_roles.update');
    Route::post('/org-structure-roles/{orgStructureRoleTemplate}/disable', [App\Http\Controllers\Admin\RoleController::class, 'disableOrgStructureRole'])->name('admin.org_structure_roles.disable');
    Route::post('/roles/org-structure', [App\Http\Controllers\Admin\RoleController::class, 'storeOrgStructureRole'])->name('roles.org_structure.store');
    Route::put('/roles/org-structure/{orgStructureRoleTemplate}', [App\Http\Controllers\Admin\RoleController::class, 'updateOrgStructureRole'])->name('roles.org_structure.update');
    Route::post('/roles/org-structure/{orgStructureRoleTemplate}/disable', [App\Http\Controllers\Admin\RoleController::class, 'disableOrgStructureRole'])->name('roles.org_structure.disable');

    Route::post('/components', [App\Http\Controllers\Admin\PageComponentController::class, 'store'])->name('admin.components.store');
    Route::put('/components/{id}', [App\Http\Controllers\Admin\PageComponentController::class, 'update'])->name('admin.components.update');
    Route::delete('/components/{id}', [App\Http\Controllers\Admin\PageComponentController::class, 'destroy'])->name('admin.components.destroy');

    Route::post('/pages', [App\Http\Controllers\Admin\PageController::class, 'store'])->name('admin.pages.store');
    Route::put('/pages/{id}', [App\Http\Controllers\Admin\PageController::class, 'update'])->name('admin.pages.update');
    Route::delete('/pages/{id}', [App\Http\Controllers\Admin\PageController::class, 'destroy'])->name('admin.pages.destroy');

    Route::get('/media', [App\Http\Controllers\Admin\MediaController::class, 'index'])->name('admin.media.index');
    Route::post('/media', [App\Http\Controllers\Admin\MediaController::class, 'store'])->name('admin.media.store');
    Route::get('/media/{media}/preview', [App\Http\Controllers\Admin\MediaController::class, 'preview'])->name('admin.media.preview');
    Route::delete('/media/{id}', [App\Http\Controllers\Admin\MediaController::class, 'destroy'])->name('admin.media.destroy');

    Route::post('/header/menu', [App\Http\Controllers\Admin\HeaderController::class, 'storeMenu'])->name('admin.header.menu.store');
    Route::delete('/header/menu/{id}', [App\Http\Controllers\Admin\HeaderController::class, 'destroyMenu'])->name('admin.header.menu.delete');
    Route::post('/header/item', [App\Http\Controllers\Admin\HeaderController::class, 'storeItem'])->name('admin.header.item.store');
    Route::delete('/header/item/{id}', [App\Http\Controllers\Admin\HeaderController::class, 'destroyItem'])->name('admin.header.item.delete');

    Route::get('/footer', [App\Http\Controllers\Admin\FooterController::class, 'index'])->name('admin.footer.index');
    Route::post('/footer/column', [App\Http\Controllers\Admin\FooterController::class, 'storeColumn'])->name('admin.footer.column.store');
    Route::delete('/footer/column/{id}', [App\Http\Controllers\Admin\FooterController::class, 'destroyColumn'])->name('admin.footer.column.delete');
    Route::post('/footer/item', [App\Http\Controllers\Admin\FooterController::class, 'storeItem'])->name('admin.footer.item.store');
    Route::delete('/footer/item/{id}', [App\Http\Controllers\Admin\FooterController::class, 'destroyItem'])->name('admin.footer.item.delete');

    Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('admin.settings.update');

    Route::put('/schools/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateSchool'])->name('admin.schools.update');
    Route::get('/schools', [App\Http\Controllers\Admin\SchoolManagementController::class, 'index'])->name('admin.schools.index');
    Route::get('/school-defaults', [AdminSchoolDefaultDataController::class, 'index'])->name('admin.school_defaults.index');
    Route::get('/school-defaults/country-reference', [AdminSchoolDefaultDataController::class, 'countryReference'])->name('admin.school_defaults.country_reference');
    Route::get('/school-defaults/reference/holidays/preview', [AdminSchoolDefaultDataController::class, 'previewReferenceHolidays'])->name('admin.school_defaults.reference.holidays.preview');
    Route::post('/school-defaults/reference/holidays/import', [AdminSchoolDefaultDataController::class, 'importReferenceHolidays'])->name('admin.school_defaults.reference.holidays.import');
    Route::post('/school-defaults/scopes', [AdminSchoolDefaultDataController::class, 'storeScopeConfig'])->name('admin.school_defaults.scopes.store');
    Route::delete('/school-defaults/scopes/{country}/{educationType}', [AdminSchoolDefaultDataController::class, 'destroyScope'])->name('admin.school_defaults.scopes.destroy');
    Route::post('/school-defaults/stages', [AdminSchoolDefaultDataController::class, 'storeStage'])->name('admin.school_defaults.stages.store');
    Route::put('/school-defaults/stages/{schoolDefaultStageTemplate}', [AdminSchoolDefaultDataController::class, 'updateStage'])->name('admin.school_defaults.stages.update');
    Route::delete('/school-defaults/stages/{schoolDefaultStageTemplate}', [AdminSchoolDefaultDataController::class, 'destroyStage'])->name('admin.school_defaults.stages.destroy');
    Route::post('/school-defaults/stage-terms', [AdminSchoolDefaultDataController::class, 'storeStageTerm'])->name('admin.school_defaults.stage_terms.store');
    Route::put('/school-defaults/stage-terms/{schoolDefaultStageTermTemplate}', [AdminSchoolDefaultDataController::class, 'updateStageTerm'])->name('admin.school_defaults.stage_terms.update');
    Route::delete('/school-defaults/stage-terms/{schoolDefaultStageTermTemplate}', [AdminSchoolDefaultDataController::class, 'destroyStageTerm'])->name('admin.school_defaults.stage_terms.destroy');
    Route::post('/school-defaults/stage-grades', [AdminSchoolDefaultDataController::class, 'storeStageGrade'])->name('admin.school_defaults.stage_grades.store');
    Route::put('/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate}', [AdminSchoolDefaultDataController::class, 'updateStageGrade'])->name('admin.school_defaults.stage_grades.update');
    Route::delete('/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate}', [AdminSchoolDefaultDataController::class, 'destroyStageGrade'])->name('admin.school_defaults.stage_grades.destroy');
    Route::post('/school-defaults/stage-grade-terms', [AdminSchoolDefaultDataController::class, 'storeStageGradeTerm'])->name('admin.school_defaults.stage_grade_terms.store');
    Route::put('/school-defaults/stage-grade-terms/{gradeTermTemplate}', [AdminSchoolDefaultDataController::class, 'updateStageGradeTerm'])->name('admin.school_defaults.stage_grade_terms.update');
    Route::delete('/school-defaults/stage-grade-terms/{gradeTermTemplate}', [AdminSchoolDefaultDataController::class, 'destroyStageGradeTerm'])->name('admin.school_defaults.stage_grade_terms.destroy');
    Route::post('/school-defaults/classrooms', [AdminSchoolDefaultDataController::class, 'storeClassroom'])->name('admin.school_defaults.classrooms.store');
    Route::put('/school-defaults/classrooms/{schoolDefaultClassroomTemplate}', [AdminSchoolDefaultDataController::class, 'updateClassroom'])->name('admin.school_defaults.classrooms.update');
    Route::delete('/school-defaults/classrooms/{schoolDefaultClassroomTemplate}', [AdminSchoolDefaultDataController::class, 'destroyClassroom'])->name('admin.school_defaults.classrooms.destroy');
    Route::post('/school-defaults/academic-years', [AdminSchoolDefaultDataController::class, 'storeAcademicYear'])->name('admin.school_defaults.academic_years.store');
    Route::put('/school-defaults/academic-years/{yearTemplate}', [AdminSchoolDefaultDataController::class, 'updateAcademicYear'])->name('admin.school_defaults.academic_years.update');
    Route::delete('/school-defaults/academic-years/{yearTemplate}', [AdminSchoolDefaultDataController::class, 'destroyAcademicYear'])->name('admin.school_defaults.academic_years.destroy');
    Route::post('/school-defaults/holidays', [AdminSchoolDefaultDataController::class, 'storeHoliday'])->name('admin.school_defaults.holidays.store');
    Route::put('/school-defaults/holidays/{schoolDefaultHolidayTemplate}', [AdminSchoolDefaultDataController::class, 'updateHoliday'])->name('admin.school_defaults.holidays.update');
    Route::delete('/school-defaults/holidays/{schoolDefaultHolidayTemplate}', [AdminSchoolDefaultDataController::class, 'destroyHoliday'])->name('admin.school_defaults.holidays.destroy');
    Route::post('/school-defaults/leave-types', [AdminSchoolDefaultDataController::class, 'storeLeaveType'])->name('admin.school_defaults.leave_types.store');
    Route::put('/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate}', [AdminSchoolDefaultDataController::class, 'updateLeaveType'])->name('admin.school_defaults.leave_types.update');
    Route::delete('/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate}', [AdminSchoolDefaultDataController::class, 'destroyLeaveType'])->name('admin.school_defaults.leave_types.destroy');
    Route::post('/school-defaults/subjects', [AdminSchoolDefaultDataController::class, 'storeSubject'])->name('admin.school_defaults.subjects.store');
    Route::put('/school-defaults/subjects/{schoolDefaultSubjectTemplate}', [AdminSchoolDefaultDataController::class, 'updateSubject'])->name('admin.school_defaults.subjects.update');
    Route::delete('/school-defaults/subjects/{schoolDefaultSubjectTemplate}', [AdminSchoolDefaultDataController::class, 'destroySubject'])->name('admin.school_defaults.subjects.destroy');
    Route::post('/countries/sync-global', [App\Http\Controllers\Admin\SchoolManagementController::class, 'syncCountriesFromGlobalApi'])->name('admin.countries.sync_global');
    Route::post('/countries', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeCountry'])->name('admin.countries.store');
    Route::put('/countries/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateCountry'])->name('admin.countries.update');
    Route::delete('/countries/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroyCountry'])->name('admin.countries.delete');
    Route::post('/governorates/sync-global', [App\Http\Controllers\Admin\SchoolManagementController::class, 'syncGovernoratesFromGlobalApi'])->name('admin.governorates.sync_global');
    Route::post('/governorates', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeGovernorate'])->name('admin.governorates.store');
    Route::put('/governorates/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateGovernorate'])->name('admin.governorates.update');
    Route::delete('/governorates/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroyGovernorate'])->name('admin.governorates.delete');
    Route::post('/education-types', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeEducationType'])->name('admin.education_types.store');
    Route::put('/education-types/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateEducationType'])->name('admin.education_types.update');
    Route::delete('/education-types/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroyEducationType'])->name('admin.education_types.delete');
    Route::post('/education-stages', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeEducationStage'])->name('admin.education_stages.store');
    Route::put('/education-stages/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateEducationStage'])->name('admin.education_stages.update');
    Route::delete('/education-stages/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroyEducationStage'])->name('admin.education_stages.delete');
    Route::post('/directorates', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeDirectorate'])->name('admin.directorates.store');
    Route::put('/directorates/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'updateDirectorate'])->name('admin.directorates.update');
    Route::delete('/directorates/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroyDirectorate'])->name('admin.directorates.delete');
    Route::post('/schools', [App\Http\Controllers\Admin\SchoolManagementController::class, 'storeSchool'])->name('admin.schools.store');
    Route::delete('/schools/{id}', [App\Http\Controllers\Admin\SchoolManagementController::class, 'destroySchool'])->name('admin.schools.delete');

    Route::get('/supervisor-assignments', [SupervisorAssignmentController::class, 'index'])->name('admin.supervisor_assignments.index');
    Route::post('/supervisor-assignments', [SupervisorAssignmentController::class, 'store'])->name('admin.supervisor_assignments.store');
    Route::delete('/supervisor-assignments/{supervisorAssignment}', [SupervisorAssignmentController::class, 'destroy'])->name('admin.supervisor_assignments.destroy');

    Route::get('/plans', [AdminPlanController::class, 'index'])->name('admin.plans.index');
    Route::post('/plans', [AdminPlanController::class, 'store'])->name('admin.plans.store');
    Route::post('/plans/{plan}/freeze', [AdminPlanController::class, 'freeze'])->name('admin.plans.freeze');
    Route::post('/plans/{plan}/activate', [AdminPlanController::class, 'activate'])->name('admin.plans.activate');
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('admin.plans.update');
    Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('admin.plans.destroy');

    Route::post('/subscriptions/{subscription}/activate', [SubscriptionManagementController::class, 'activate'])->name('admin.subscriptions.activate');
    Route::post('/subscriptions/{subscription}/freeze', [SubscriptionManagementController::class, 'freeze'])->name('admin.subscriptions.freeze');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionManagementController::class, 'cancel'])->name('admin.subscriptions.cancel');
    Route::delete('/subscriptions/{subscription}', [SubscriptionManagementController::class, 'destroy'])->name('admin.subscriptions.destroy');

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
});

Route::middleware(['auth', 'role:super_admin', 'throttle:api.system'])->prefix('api/system')->group(function () {
    Route::get('/permissions', [SystemPermissionController::class, 'index'])->name('api.system.permissions.index');
    Route::post('/permissions', [SystemPermissionController::class, 'store'])->name('api.system.permissions.store');

    Route::get('/roles', [SystemRoleController::class, 'index'])->name('api.system.roles.index');
    Route::post('/roles', [SystemRoleController::class, 'store'])->name('api.system.roles.store');
    Route::get('/roles/{role}', [SystemRoleController::class, 'show'])->name('api.system.roles.show');
    Route::put('/roles/{role}', [SystemRoleController::class, 'update'])->name('api.system.roles.update');
    Route::delete('/roles/{role}', [SystemRoleController::class, 'destroy'])->name('api.system.roles.destroy');
    Route::put('/roles/{role}/permissions', [SystemRoleController::class, 'syncPermissions'])->name('api.system.roles.permissions.sync');
});

Route::middleware(['auth', 'role:school_manager', 'managed_school', 'active_school_association', 'throttle:api.school'])->prefix('api/school')->group(function () {
    Route::get('/roles/assignable', [SchoolAssignableRoleController::class, 'index'])->name('api.school.roles.assignable');
    Route::get('/org-structure-roles', [SchoolOrgStructureRoleController::class, 'index'])->name('api.school.org_structure_roles.index');
    Route::get('/permission-groups', [SchoolPermissionGroupController::class, 'index'])->name('api.school.permission_groups.index');
    Route::post('/permission-groups', [SchoolPermissionGroupController::class, 'store'])->name('api.school.permission_groups.store');
    Route::put('/permission-groups/{schoolPermissionGroup}', [SchoolPermissionGroupController::class, 'update'])->name('api.school.permission_groups.update');
    Route::delete('/permission-groups/{schoolPermissionGroup}', [SchoolPermissionGroupController::class, 'destroy'])->name('api.school.permission_groups.destroy');

    Route::get('/users', [SchoolUserManagementController::class, 'index'])->name('api.school.users.index');
    Route::post('/users', [SchoolUserManagementController::class, 'store'])->name('api.school.users.store');
    Route::put('/users/{user}', [SchoolUserManagementController::class, 'update'])->name('api.school.users.update');
    Route::put('/users/{user}/roles', [SchoolUserManagementController::class, 'syncRoles'])->name('api.school.users.roles.sync');
});

Route::middleware(['auth', 'role:supervisor'])->group(function () {
    Route::get('/supervisor/onboarding', [SupervisorOnboardingController::class, 'show'])->name('supervisor.onboarding.show');
    Route::get('/supervisor/onboarding/regions', [SupervisorOnboardingController::class, 'regions'])->name('supervisor.onboarding.regions');
    Route::get('/supervisor/onboarding/location-schools', [SupervisorOnboardingController::class, 'locationSchools'])->name('supervisor.onboarding.location_schools');
    Route::get('/supervisor/onboarding/regions/{region}/schools', [SupervisorOnboardingController::class, 'schools'])->name('supervisor.onboarding.schools');
    Route::post('/supervisor/onboarding/select', [SupervisorOnboardingController::class, 'select'])->name('supervisor.onboarding.select');

    Route::get('/supervisor/requests/inbox', [SupervisorRequestController::class, 'page'])->name('supervisor.requests.page');
    Route::get('/supervisor/requests', [SupervisorRequestController::class, 'index'])->name('supervisor.requests.index');
    Route::post('/supervisor/requests/{schoolSupervisionRequest}/confirm', [SupervisorRequestController::class, 'confirm'])->name('supervisor.requests.confirm');
    Route::post('/supervisor/requests/{schoolSupervisionRequest}/cancel', [SupervisorRequestController::class, 'cancel'])->name('supervisor.requests.cancel');

    Route::get('/supervisor/dashboard', [SupervisorDashboardController::class, 'index'])->name('supervisor.dashboard');

    Route::get('/supervisor/tickets', [SupervisorTicketController::class, 'index'])->name('supervisor.tickets.index');
    Route::post('/supervisor/tickets', [SupervisorTicketController::class, 'store'])->name('supervisor.tickets.store');
    Route::get('/supervisor/tickets/{ticket}', [SupervisorTicketController::class, 'show'])->name('supervisor.tickets.show');
    Route::put('/supervisor/tickets/{ticket}', [SupervisorTicketController::class, 'update'])->name('supervisor.tickets.update');
    Route::post('/supervisor/tickets/{ticket}/close', [SupervisorTicketController::class, 'close'])->name('supervisor.tickets.close');
});

Route::middleware(['auth', 'role:school_manager'])->group(function () {
    Route::get('/manager/onboarding', [ManagerOnboardingController::class, 'show'])->name('manager.onboarding.show');
    Route::get('/manager/onboarding/regions', [ManagerOnboardingController::class, 'regions'])->name('manager.onboarding.regions');
    Route::get('/manager/onboarding/templates', [ManagerOnboardingController::class, 'templates'])->name('manager.onboarding.templates');
    Route::get('/manager/onboarding/governorates', [ManagerOnboardingController::class, 'governorates'])->name('manager.onboarding.governorates');
    Route::get('/manager/onboarding/regions/{region}/schools', [ManagerOnboardingController::class, 'schools'])->name('manager.onboarding.schools');
    Route::post('/manager/onboarding/select', [ManagerOnboardingController::class, 'select'])->name('manager.onboarding.select');
    Route::post('/manager/onboarding/schools', [ManagerOnboardingController::class, 'storeSchool'])->name('manager.onboarding.schools.store');
    Route::put('/manager/onboarding/schools/{school}', [ManagerOnboardingController::class, 'updateSchool'])->name('manager.onboarding.schools.update');

    Route::middleware('managed_school')->group(function () {
        Route::get('/manager/requests/inbox', [ManagerRequestController::class, 'page'])->name('manager.requests.page');
        Route::get('/manager/requests', [ManagerRequestController::class, 'index'])->name('manager.requests.index');
        Route::post('/manager/requests/{schoolSupervisionRequest}/approve', [ManagerRequestController::class, 'approve'])->name('manager.requests.approve');
        Route::post('/manager/requests/{schoolSupervisionRequest}/reject', [ManagerRequestController::class, 'reject'])->name('manager.requests.reject');

        Route::get('/association-requests', [AssociationRequestController::class, 'index'])->name('association_requests.index');
        Route::post('/association-requests/{associationRequest}/approve', [AssociationRequestController::class, 'approve'])->name('association_requests.approve');
        Route::post('/association-requests/{associationRequest}/reject', [AssociationRequestController::class, 'reject'])->name('association_requests.reject');

        Route::middleware('active_school_association')->group(function () {
            Route::get('/manager/structure', [ManagerSchoolStructureController::class, 'index'])->name('manager.structure.index');
            Route::post('/manager/structure/departments', [ManagerDepartmentController::class, 'store'])->name('manager.structure.departments.store');
            Route::put('/manager/structure/departments/{department}', [ManagerDepartmentController::class, 'update'])->name('manager.structure.departments.update');
            Route::delete('/manager/structure/departments/{department}', [ManagerDepartmentController::class, 'destroy'])->name('manager.structure.departments.destroy');
            Route::post('/manager/structure/users', [ManagerSchoolUserController::class, 'store'])->name('manager.structure.users.store');
            Route::put('/manager/structure/users/{user}', [ManagerSchoolUserController::class, 'update'])->name('manager.structure.users.update');
            Route::delete('/manager/structure/users/{user}', [ManagerSchoolUserController::class, 'destroy'])->name('manager.structure.users.destroy');

            Route::get('/manager/dashboard', [ManagerDashboardController::class, 'index'])->name('manager.dashboard');

            Route::get('/manager/tickets', [ManagerTicketController::class, 'index'])->name('manager.tickets.index');
            Route::post('/manager/tickets', [ManagerTicketController::class, 'store'])->name('manager.tickets.store');
            Route::get('/manager/tickets/{ticket}', [ManagerTicketController::class, 'show'])->name('manager.tickets.show');
            Route::post('/manager/tickets/{ticket}/final-report', [ManagerTicketController::class, 'finalReport'])->name('manager.tickets.final_report');
            Route::post('/manager/tickets/{ticket}/close', [ManagerTicketController::class, 'close'])->name('manager.tickets.close');

            Route::post('/manager/subtasks', [ManagerSubtaskController::class, 'store'])->name('manager.subtasks.store');
            Route::put('/manager/subtasks/{subtask}', [ManagerSubtaskController::class, 'update'])->name('manager.subtasks.update');
            Route::post('/manager/subtasks/{subtask}/approve', [ManagerSubtaskController::class, 'approve'])->name('manager.subtasks.approve');
        });
    });
});

Route::middleware(['auth', 'active_school_association', 'student_structure_access'])->group(function () {
    Route::get('/school/student-structure', [SchoolStudentStructureController::class, 'index'])->name('school.student_structure.index');

    Route::post('/school/student-structure/stages', [SchoolStudentStructureController::class, 'storeStage'])->name('school.student_structure.stages.store');
    Route::put('/school/student-structure/stages/{schoolStage}', [SchoolStudentStructureController::class, 'updateStage'])->name('school.student_structure.stages.update');
    Route::delete('/school/student-structure/stages/{schoolStage}', [SchoolStudentStructureController::class, 'destroyStage'])->name('school.student_structure.stages.destroy');
    Route::post('/school/student-structure/stage-terms', [SchoolStudentStructureController::class, 'storeStageTerm'])->name('school.student_structure.stage_terms.store');
    Route::put('/school/student-structure/stage-terms/{schoolStageTerm}', [SchoolStudentStructureController::class, 'updateStageTerm'])->name('school.student_structure.stage_terms.update');
    Route::delete('/school/student-structure/stage-terms/{schoolStageTerm}', [SchoolStudentStructureController::class, 'destroyStageTerm'])->name('school.student_structure.stage_terms.destroy');
    Route::post('/school/student-structure/stage-grades', [SchoolStudentStructureController::class, 'storeStageGrade'])->name('school.student_structure.stage_grades.store');
    Route::put('/school/student-structure/stage-grades/{schoolStageGrade}', [SchoolStudentStructureController::class, 'updateStageGrade'])->name('school.student_structure.stage_grades.update');
    Route::delete('/school/student-structure/stage-grades/{schoolStageGrade}', [SchoolStudentStructureController::class, 'destroyStageGrade'])->name('school.student_structure.stage_grades.destroy');
    Route::post('/school/student-structure/stage-grade-terms', [SchoolStudentStructureController::class, 'storeStageGradeTerm'])->name('school.student_structure.stage_grade_terms.store');
    Route::put('/school/student-structure/stage-grade-terms/{schoolStageGradeTerm}', [SchoolStudentStructureController::class, 'updateStageGradeTerm'])->name('school.student_structure.stage_grade_terms.update');
    Route::delete('/school/student-structure/stage-grade-terms/{schoolStageGradeTerm}', [SchoolStudentStructureController::class, 'destroyStageGradeTerm'])->name('school.student_structure.stage_grade_terms.destroy');

    Route::post('/school/student-structure/classrooms', [SchoolStudentStructureController::class, 'storeClassroom'])->name('school.student_structure.classrooms.store');
    Route::put('/school/student-structure/classrooms/{schoolClassroom}', [SchoolStudentStructureController::class, 'updateClassroom'])->name('school.student_structure.classrooms.update');
    Route::delete('/school/student-structure/classrooms/{schoolClassroom}', [SchoolStudentStructureController::class, 'destroyClassroom'])->name('school.student_structure.classrooms.destroy');

    Route::post('/school/student-structure/students', [SchoolStudentStructureController::class, 'storeStudent'])->name('school.student_structure.students.store');
    Route::put('/school/student-structure/students/{schoolStudent}', [SchoolStudentStructureController::class, 'updateStudent'])->name('school.student_structure.students.update');
    Route::delete('/school/student-structure/students/{schoolStudent}', [SchoolStudentStructureController::class, 'destroyStudent'])->name('school.student_structure.students.destroy');
});

Route::middleware(['auth', 'active_school_association', 'throttle:api.school'])->group(function () {
    Route::post('/school/default-data/import', [SchoolDefaultDataImportController::class, 'store'])->name('school.default_data.import');
});

Route::middleware(['auth', 'active_school_association', 'student_attendance_access'])->group(function () {
    Route::get('/school/student-attendance', [SchoolStudentAttendanceController::class, 'index'])->name('school.student_attendance.index');
    Route::post('/school/student-attendance/records', [SchoolStudentAttendanceController::class, 'upsertRecords'])->name('school.student_attendance.records.upsert');
    Route::post('/school/student-attendance/attachments', [SchoolStudentAttendanceController::class, 'storeAttachments'])->name('school.student_attendance.attachments.store');
    Route::get(
        '/school/student-attendance/attachments/{schoolAttendanceAttachment}/download',
        [SchoolStudentAttendanceController::class, 'downloadAttachment']
    )->name('school.student_attendance.attachments.download');
    Route::delete(
        '/school/student-attendance/attachments/{schoolAttendanceAttachment}',
        [SchoolStudentAttendanceController::class, 'destroyAttachment']
    )->name('school.student_attendance.attachments.destroy');
    Route::get('/school/student-attendance/report/export', [SchoolStudentAttendanceController::class, 'exportReportCsv'])->name('school.student_attendance.report.export');
});

Route::middleware(['auth', 'active_school_association'])->group(function () {
    Route::get('/school/attachments/{attachment}/download', [SchoolAttachmentController::class, 'download'])
        ->name('school.attachments.download');
    Route::delete('/school/attachments/{attachment}', [SchoolAttachmentController::class, 'destroy'])
        ->name('school.attachments.destroy');
});

Route::middleware(['auth', 'active_school_association', 'school_reports_access'])->group(function () {
    Route::get('/school/reports', [SchoolReportsController::class, 'index'])->name('school.reports.index');
    Route::get('/school/reports/export', [SchoolReportsController::class, 'export'])->name('school.reports.export');
});

Route::middleware(['auth', 'active_school_association', 'school_certificates_access'])->group(function () {
    Route::get('/school/certificates', [SchoolStudentCertificateController::class, 'index'])->name('school.certificates.index');
    Route::post('/school/certificates/templates', [SchoolStudentCertificateController::class, 'storeTemplate'])->name('school.certificates.templates.store');
    Route::put('/school/certificates/templates/{certificateTemplate}', [SchoolStudentCertificateController::class, 'updateTemplate'])->name('school.certificates.templates.update');
    Route::delete('/school/certificates/templates/{certificateTemplate}', [SchoolStudentCertificateController::class, 'destroyTemplate'])->name('school.certificates.templates.destroy');
    Route::post('/school/certificates/signatures', [SchoolStudentCertificateController::class, 'storeSignature'])->name('school.certificates.signatures.store');
    Route::delete('/school/certificates/signatures/{schoolCertificateSignature}', [SchoolStudentCertificateController::class, 'destroySignature'])->name('school.certificates.signatures.destroy');
    Route::post('/school/certificates/issue', [SchoolStudentCertificateController::class, 'issue'])->name('school.certificates.issue');
    Route::get('/school/certificates/{studentCertificate}/print', [SchoolStudentCertificateController::class, 'print'])->name('school.certificates.print');
    Route::get('/school/certificates/{studentCertificate}/download', [SchoolStudentCertificateController::class, 'download'])->name('school.certificates.download');
    Route::post('/school/certificates/{studentCertificate}/cancel', [SchoolStudentCertificateController::class, 'cancel'])->name('school.certificates.cancel');
});

Route::middleware(['auth', 'active_school_association', 'school_exams_access'])->group(function () {
    Route::get('/school/exams', [SchoolExamController::class, 'index'])->name('school.exams.index');
    Route::put('/school/exams/settings', [SchoolExamController::class, 'updateSettings'])->name('school.exams.settings.update');

    Route::post('/school/exams/templates', [SchoolExamController::class, 'storeTemplate'])->name('school.exams.templates.store');
    Route::put('/school/exams/templates/{schoolExamTemplate}', [SchoolExamController::class, 'updateTemplate'])->name('school.exams.templates.update');
    Route::delete('/school/exams/templates/{schoolExamTemplate}', [SchoolExamController::class, 'destroyTemplate'])->name('school.exams.templates.destroy');

    Route::post('/school/exams', [SchoolExamController::class, 'storeExam'])->name('school.exams.store');
    Route::put('/school/exams/{schoolExam}', [SchoolExamController::class, 'updateExam'])->name('school.exams.update');
    Route::delete('/school/exams/{schoolExam}', [SchoolExamController::class, 'destroyExam'])->name('school.exams.destroy');
    Route::post('/school/exams/{schoolExam}/status', [SchoolExamController::class, 'updateExamStatus'])->name('school.exams.status.update');

    Route::post('/school/exams/question-bank', [SchoolExamController::class, 'storeQuestion'])->name('school.exams.question_bank.store');
    Route::put('/school/exams/question-bank/{schoolQuestionBankItem}', [SchoolExamController::class, 'updateQuestion'])->name('school.exams.question_bank.update');
    Route::delete('/school/exams/question-bank/{schoolQuestionBankItem}', [SchoolExamController::class, 'destroyQuestion'])->name('school.exams.question_bank.destroy');

    Route::post('/school/exams/{schoolExam}/questions/sync', [SchoolExamController::class, 'syncExamQuestions'])->name('school.exams.questions.sync');
    Route::post('/school/exams/{schoolExam}/scores/upsert', [SchoolExamController::class, 'upsertScores'])->name('school.exams.scores.upsert');
});

Route::middleware(['auth', 'active_school_association', 'student_leave_access'])->group(function () {
    Route::get('/school/student-leaves', [SchoolStudentLeaveController::class, 'index'])->name('school.student_leaves.index');

    Route::middleware('throttle:api.school')->prefix('api/school')->group(function () {
        Route::get('/leave-types', [ApiSchoolStudentLeaveManagementController::class, 'leaveTypes'])->name('api.school.leave_types.index');
        Route::post('/leave-types', [ApiSchoolStudentLeaveManagementController::class, 'storeLeaveType'])->name('api.school.leave_types.store');
        Route::patch('/leave-types/{schoolLeaveType}', [ApiSchoolStudentLeaveManagementController::class, 'updateLeaveType'])->name('api.school.leave_types.update');
        Route::get('/leave-types/{schoolLeaveType}/delete-impact', [ApiSchoolStudentLeaveManagementController::class, 'leaveTypeDeleteImpact'])->name('api.school.leave_types.delete_impact');
        Route::post('/leave-types/{schoolLeaveType}/disable', [ApiSchoolStudentLeaveManagementController::class, 'disableLeaveType'])->name('api.school.leave_types.disable');

        Route::get('/school-calendar-settings', [ApiSchoolCalendarManagementController::class, 'showSettings'])->name('api.school.calendar_settings.show');
        Route::put('/school-calendar-settings', [ApiSchoolCalendarManagementController::class, 'updateSettings'])->name('api.school.calendar_settings.update');

        Route::get('/holidays', [ApiSchoolCalendarManagementController::class, 'indexHolidays'])->name('api.school.holidays.index');
        Route::post('/holidays', [ApiSchoolCalendarManagementController::class, 'storeHoliday'])->name('api.school.holidays.store');
        Route::get('/holidays/{schoolHoliday}/delete-impact', [ApiSchoolCalendarManagementController::class, 'holidayDeleteImpact'])->name('api.school.holidays.delete_impact');
        Route::get('/holidays/{schoolHoliday}/update-impact', [ApiSchoolCalendarManagementController::class, 'holidayUpdateImpact'])->name('api.school.holidays.update_impact');
        Route::patch('/holidays/{schoolHoliday}', [ApiSchoolCalendarManagementController::class, 'updateHoliday'])->name('api.school.holidays.update');
        Route::post('/holidays/{schoolHoliday}/disable', [ApiSchoolCalendarManagementController::class, 'disableHoliday'])->name('api.school.holidays.disable');

        Route::get('/leaves', [ApiSchoolStudentLeaveManagementController::class, 'index'])->name('api.school.leaves.index');
        Route::post('/leaves', [ApiSchoolStudentLeaveManagementController::class, 'store'])->name('api.school.leaves.store');
        Route::patch('/leaves/{schoolStudentLeaveRequest}', [ApiSchoolStudentLeaveManagementController::class, 'update'])->name('api.school.leaves.update');
        Route::post('/leaves/{schoolStudentLeaveRequest}/approve', [ApiSchoolStudentLeaveManagementController::class, 'approve'])->name('api.school.leaves.approve');
        Route::post('/leaves/{schoolStudentLeaveRequest}/reject', [ApiSchoolStudentLeaveManagementController::class, 'reject'])->name('api.school.leaves.reject');
        Route::post('/leaves/{schoolStudentLeaveRequest}/cancel', [ApiSchoolStudentLeaveManagementController::class, 'cancel'])->name('api.school.leaves.cancel');
        Route::post('/leaves/{schoolStudentLeaveRequest}/attachments', [ApiSchoolStudentLeaveManagementController::class, 'storeAttachment'])->name('api.school.leaves.attachments.store');
        Route::get('/leaves/{schoolStudentLeaveRequest}/attachments/{schoolStudentLeaveAttachment}/download', [ApiSchoolStudentLeaveManagementController::class, 'downloadAttachment'])->name('api.school.leaves.attachments.download');
    });
});

Route::middleware(['auth', 'active_school_association', 'academic_planning_access'])->group(function () {
    Route::get('/school/academic-planning', [SchoolAcademicPlanningController::class, 'index'])->name('school.academic_planning.index');

    Route::middleware('throttle:api.school')->prefix('api/school')->group(function () {
        Route::get('/quick-setup/status', [ApiSchoolQuickSetupStatusController::class, 'show'])->name('api.school.quick_setup.status');
    });

    Route::post('/school/academic-planning/years', [SchoolAcademicPlanningController::class, 'storeYear'])->name('school.academic_planning.years.store');
    Route::put('/school/academic-planning/years/{schoolAcademicYear}', [SchoolAcademicPlanningController::class, 'updateYear'])->name('school.academic_planning.years.update');
    Route::delete('/school/academic-planning/years/{schoolAcademicYear}', [SchoolAcademicPlanningController::class, 'destroyYear'])->name('school.academic_planning.years.destroy');

    Route::post('/school/academic-planning/terms', [SchoolAcademicPlanningController::class, 'storeTerm'])->name('school.academic_planning.terms.store');
    Route::put('/school/academic-planning/terms/{schoolTerm}', [SchoolAcademicPlanningController::class, 'updateTerm'])->name('school.academic_planning.terms.update');
    Route::delete('/school/academic-planning/terms/{schoolTerm}', [SchoolAcademicPlanningController::class, 'destroyTerm'])->name('school.academic_planning.terms.destroy');

    Route::post('/school/academic-planning/versions', [SchoolAcademicPlanningController::class, 'storeTimetableVersion'])->name('school.academic_planning.versions.store');
    Route::put('/school/academic-planning/versions/{schoolTimetableVersion}', [SchoolAcademicPlanningController::class, 'updateTimetableVersion'])->name('school.academic_planning.versions.update');
    Route::post('/school/academic-planning/versions/{schoolTimetableVersion}/publish', [SchoolAcademicPlanningController::class, 'publishTimetableVersion'])->name('school.academic_planning.versions.publish');

    Route::post('/school/academic-planning/subjects', [SchoolAcademicPlanningController::class, 'storeSubject'])->name('school.academic_planning.subjects.store');
    Route::put('/school/academic-planning/subjects/{schoolSubject}', [SchoolAcademicPlanningController::class, 'updateSubject'])->name('school.academic_planning.subjects.update');
    Route::delete('/school/academic-planning/subjects/{schoolSubject}', [SchoolAcademicPlanningController::class, 'destroySubject'])->name('school.academic_planning.subjects.destroy');
    Route::post('/school/academic-planning/subjects/{schoolSubject}/teachers', [SchoolAcademicPlanningController::class, 'syncSubjectTeachers'])->name('school.academic_planning.subjects.teachers.sync');

    Route::post('/school/academic-planning/offerings', [SchoolAcademicPlanningController::class, 'storeCourseOffering'])->name('school.academic_planning.offerings.store');
    Route::put('/school/academic-planning/offerings/{schoolCourseOffering}', [SchoolAcademicPlanningController::class, 'updateCourseOffering'])->name('school.academic_planning.offerings.update');
    Route::delete('/school/academic-planning/offerings/{schoolCourseOffering}', [SchoolAcademicPlanningController::class, 'destroyCourseOffering'])->name('school.academic_planning.offerings.destroy');
    Route::post('/school/academic-planning/offerings/{schoolCourseOffering}/assignment/sync', [SchoolAcademicPlanningController::class, 'syncCourseOfferingAssignment'])->name('school.academic_planning.offerings.assignment.sync');

    Route::post('/school/academic-planning/teachers/{teacher}/availability/sync', [SchoolAcademicPlanningController::class, 'syncTeacherAvailability'])->name('school.academic_planning.teachers.availability.sync');

    Route::post('/school/academic-planning/schedules/grid/sync', [SchoolAcademicPlanningController::class, 'syncWeeklyGrid'])->name('school.academic_planning.schedules.grid.sync');
    Route::get('/school/academic-planning/schedules/grid/export/{format}', [SchoolAcademicPlanningController::class, 'exportWeeklyGrid'])
        ->where('format', 'pdf|word')
        ->name('school.academic_planning.schedules.grid.export');
    Route::post('/school/academic-planning/schedules', [SchoolAcademicPlanningController::class, 'storeSchedule'])->name('school.academic_planning.schedules.store');
    Route::put('/school/academic-planning/schedules/{schoolClassSchedule}', [SchoolAcademicPlanningController::class, 'updateSchedule'])->name('school.academic_planning.schedules.update');
    Route::delete('/school/academic-planning/schedules/{schoolClassSchedule}', [SchoolAcademicPlanningController::class, 'destroySchedule'])->name('school.academic_planning.schedules.destroy');
});

Route::middleware(['auth', 'role:staff', 'active_school_association'])->group(function () {
    Route::get('/staff/dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');

    Route::get('/staff/subtasks', [StaffSubtaskController::class, 'index'])->name('staff.subtasks.index');
    Route::get('/staff/subtasks/{subtask}', [StaffSubtaskController::class, 'show'])->name('staff.subtasks.show');
    Route::post('/staff/subtasks/{subtask}/reply', [StaffSubtaskController::class, 'reply'])->name('staff.subtasks.reply');
    Route::post('/staff/subtasks/{subtask}/submit', [StaffSubtaskController::class, 'submit'])->name('staff.subtasks.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
