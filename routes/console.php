<?php

use App\Models\User;
use App\Models\School;
use App\Services\School\DailyAttendanceInitializerService;
use App\Support\SchoolAssociationState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'attendance:initialize-daily {--date=} {--school_id=} {--classroom_id=} {--dry-run}',
    function (DailyAttendanceInitializerService $initializer): int {
        $dateInput = trim((string) $this->option('date'));
        $schoolId = (int) $this->option('school_id');
        $classroomId = (int) $this->option('classroom_id');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $date = $dateInput !== '' ? Carbon::parse($dateInput)->toDateString() : now()->toDateString();
        } catch (\Throwable) {
            $this->error('Invalid --date value. Expected YYYY-MM-DD.');
            return Command::FAILURE;
        }

        if ($classroomId > 0 && $schoolId <= 0) {
            $this->error('Option --classroom_id requires --school_id for tenant-safe execution.');
            return Command::FAILURE;
        }

        $schools = collect();
        if ($schoolId > 0) {
            $school = School::query()
                ->whereKey($schoolId)
                ->first(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id']);

            if (!$school) {
                $this->error("School {$schoolId} not found.");
                return Command::FAILURE;
            }

            $schools = collect([$school]);
        } else {
            $schools = School::query()
                ->orderBy('id')
                ->get(['id', 'status', 'supervision_status', 'manager_user_id', 'supervisor_id'])
                ->filter(fn (School $school): bool => SchoolAssociationState::isActiveAssociation($school))
                ->values();
        }

        if ($schools->isEmpty()) {
            $this->warn('No eligible schools found for initialization.');
            return Command::SUCCESS;
        }

        $summary = [
            'schools' => 0,
            'classrooms' => 0,
            'students' => 0,
            'existing' => 0,
            'inserted' => 0,
            'skipped' => 0,
        ];

        foreach ($schools as $school) {
            $result = $initializer->ensureDailyRecordsForSchool(
                (int) $school->id,
                $date,
                null,
                $classroomId > 0 ? $classroomId : null,
                $dryRun
            );

            $summary['schools']++;
            $summary['classrooms'] += (int) ($result['total_classrooms'] ?? 0);
            $summary['students'] += (int) ($result['total_students'] ?? 0);
            $summary['existing'] += (int) ($result['existing_records'] ?? 0);
            $summary['inserted'] += (int) ($result['inserted_records'] ?? 0);
            $summary['skipped'] += (bool) ($result['skipped'] ?? false) ? 1 : 0;
        }

        $mode = $dryRun ? 'DRY-RUN' : 'APPLY';
        $this->info("Daily attendance initialization [$mode] completed for {$date}.");
        $this->line("Schools: {$summary['schools']}");
        $this->line("Classrooms: {$summary['classrooms']}");
        $this->line("Students: {$summary['students']}");
        $this->line("Existing records: {$summary['existing']}");
        $this->line("Inserted records: {$summary['inserted']}");
        $this->line("Skipped schools: {$summary['skipped']}");

        return Command::SUCCESS;
    }
)->purpose('Initialize daily attendance records with default PRESENT status for active schools/classrooms.');

if (config('features.attendance.daily_initialization_enabled', true)) {
    Schedule::command('attendance:initialize-daily')
        ->dailyAt((string) config('features.attendance.daily_initialization_time', '06:00'))
        ->withoutOverlapping();
}

Artisan::command(
    'system:purge-school-and-accounts-data {--force : Execute destructive purge} {--dry-run : Preview purge scope without deleting data} {--keep-super-admin-email=* : Additional super admin emails to keep}',
    function (): int {
        $isDryRun = (bool) $this->option('dry-run');

        if (!$isDryRun && !$this->option('force')) {
            $this->error('This command is destructive. Re-run with --force.');
            return Command::FAILURE;
        }

        if (!Schema::hasTable('users')) {
            $this->error('Users table was not found.');
            return Command::FAILURE;
        }

        $roleTable = (string) config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissionsTable = (string) config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $modelMorphKey = (string) (config('permission.column_names.model_morph_key') ?: 'model_id');
        $rolePivotKey = (string) (config('permission.column_names.role_pivot_key') ?: 'role_id');

        $superAdminRoleIds = collect();
        if (Schema::hasTable($roleTable)) {
            $superAdminRoleIds = DB::table($roleTable)
                ->where('name', 'super_admin')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values();
        }

        $keepUserIds = collect();

        if (Schema::hasColumn('users', 'role')) {
            $keepUserIds = $keepUserIds->merge(
                DB::table('users')
                    ->where('role', 'super_admin')
                    ->pluck('id')
            );
        }

        if (
            $superAdminRoleIds->isNotEmpty()
            && Schema::hasTable($modelHasRolesTable)
            && Schema::hasColumn($modelHasRolesTable, 'model_type')
            && Schema::hasColumn($modelHasRolesTable, $modelMorphKey)
            && Schema::hasColumn($modelHasRolesTable, $rolePivotKey)
        ) {
            $keepUserIds = $keepUserIds->merge(
                DB::table($modelHasRolesTable)
                    ->where('model_type', User::class)
                    ->whereIn($rolePivotKey, $superAdminRoleIds->all())
                    ->pluck($modelMorphKey)
            );
        }

        $extraEmails = collect((array) $this->option('keep-super-admin-email'))
            ->map(fn ($email): string => trim((string) $email))
            ->filter(fn (string $email): bool => $email !== '')
            ->values();

        if ($extraEmails->isNotEmpty()) {
            $keepUserIds = $keepUserIds->merge(
                DB::table('users')
                    ->whereIn('email', $extraEmails->all())
                    ->pluck('id')
            );
        }

        $keepUserIds = $keepUserIds
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($keepUserIds->isEmpty()) {
            $this->error('No super admin user was detected. Aborting purge to avoid locking the system.');
            return Command::FAILURE;
        }

        $keptUsers = DB::table('users')
            ->whereIn('id', $keepUserIds->all())
            ->get(['id', 'name', 'email']);

        if ($keptUsers->isEmpty()) {
            $this->error('No valid super admin user records were found. Aborting purge.');
            return Command::FAILURE;
        }

        $databaseName = (string) (DB::getDatabaseName() ?? '');
        $allTables = collect(Schema::getTableListing())
            ->map(function ($table) use ($databaseName): ?string {
                $normalized = trim((string) $table);
                if ($normalized === '') {
                    return null;
                }

                if (str_contains($normalized, '.')) {
                    [$listedDatabase, $listedTable] = array_pad(explode('.', $normalized, 2), 2, null);
                    if ($databaseName !== '' && strcasecmp((string) $listedDatabase, $databaseName) !== 0) {
                        return null;
                    }

                    $normalized = trim((string) $listedTable);
                }

                return $normalized !== '' ? $normalized : null;
            })
            ->filter(fn (?string $table): bool => $table !== null)
            ->unique()
            ->values();

        $protectedTables = collect([
            'users',
            'migrations',
            'plans',
            'settings',
            'pages',
            'page_components',
            'header_menus',
            'header_items',
            'footer_columns',
            'footer_items',
            'org_structure_role_templates',
            'countries',
            'governorates',
            'education_types',
            'educational_directorates',
            'school_default_stage_templates',
            'school_default_stage_grade_templates',
            'school_default_classroom_templates',
            'school_default_academic_year_templates',
            'school_default_holiday_templates',
            'school_default_leave_type_templates',
            'school_default_subject_templates',
            $roleTable,
            (string) config('permission.table_names.permissions', 'permissions'),
            (string) config('permission.table_names.role_has_permissions', 'role_has_permissions'),
            $modelHasRolesTable,
            $modelHasPermissionsTable,
        ])->filter(fn (string $table): bool => $allTables->contains($table));

        $manualTenantTables = collect([
            'schools',
            'association_requests',
            'subscriptions',
            'tickets',
            'subtasks',
            'ticket_messages',
            'attachments',
            'status_history',
            'notifications',
            'audit_logs',
            'password_reset_tokens',
            'sessions',
            'personal_access_tokens',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
        ])->filter(fn (string $table): bool => $allTables->contains($table));

        $schoolScopedTables = $allTables
            ->filter(fn (string $table): bool => str_starts_with($table, 'school_'))
            ->values();

        $tablesToTruncate = $schoolScopedTables
            ->merge($manualTenantTables)
            ->unique()
            ->reject(fn (string $table): bool => $protectedTables->contains($table))
            ->values();

        $schoolScopedDepartmentIds = collect();
        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'school_id')) {
            $schoolScopedDepartmentIds = DB::table('departments')
                ->whereNotNull('school_id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values();
        }

        if ($isDryRun) {
            $this->info('Dry-run mode: no data has been deleted.');
            $this->line('Super admin users that will be kept:');
            foreach ($keptUsers as $user) {
                $this->line("- #{$user->id} {$user->name} <{$user->email}>");
            }

            $this->line('Tables targeted for purge:');
            foreach ($tablesToTruncate as $table) {
                $this->line("- {$table}");
            }

            if ($schoolScopedDepartmentIds->isNotEmpty()) {
                $this->line('Additional scoped cleanup:');
                $this->line('- departments: school-scoped rows only');
                if (Schema::hasTable('department_roles')) {
                    $this->line('- department_roles: rows linked to school-scoped departments only');
                }
            }

            return Command::SUCCESS;
        }

        $driver = DB::getDriverName();
        $deletedUsersCount = 0;
        $truncatedTablesCount = 0;
        $wipeTable = function (string $table) use ($driver): void {
            if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
                DB::table($table)->delete();
                return;
            }

            DB::table($table)->truncate();
        };

        $disableForeignKeys = function () use ($driver): void {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }
        };

        $enableForeignKeys = function () use ($driver): void {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        };

        try {
            DB::beginTransaction();
            $disableForeignKeys();

            foreach ($tablesToTruncate as $table) {
                $wipeTable($table);
                $truncatedTablesCount++;
            }

            if ($schoolScopedDepartmentIds->isNotEmpty()) {
                if (Schema::hasTable('department_roles') && Schema::hasColumn('department_roles', 'department_id')) {
                    DB::table('department_roles')
                        ->whereIn('department_id', $schoolScopedDepartmentIds->all())
                        ->delete();
                }

                DB::table('departments')
                    ->whereIn('id', $schoolScopedDepartmentIds->all())
                    ->delete();
            }

            if (
                Schema::hasTable($modelHasRolesTable)
                && Schema::hasColumn($modelHasRolesTable, 'model_type')
                && Schema::hasColumn($modelHasRolesTable, $modelMorphKey)
            ) {
                DB::table($modelHasRolesTable)
                    ->where('model_type', User::class)
                    ->whereNotIn($modelMorphKey, $keepUserIds->all())
                    ->delete();
            }

            if (
                Schema::hasTable($modelHasPermissionsTable)
                && Schema::hasColumn($modelHasPermissionsTable, 'model_type')
                && Schema::hasColumn($modelHasPermissionsTable, $modelMorphKey)
            ) {
                DB::table($modelHasPermissionsTable)
                    ->where('model_type', User::class)
                    ->whereNotIn($modelMorphKey, $keepUserIds->all())
                    ->delete();
            }

            $deletedUsersCount = DB::table('users')
                ->whereNotIn('id', $keepUserIds->all())
                ->delete();

            if (Schema::hasTable('org_structure_role_templates')) {
                foreach (['created_by', 'updated_by'] as $column) {
                    if (Schema::hasColumn('org_structure_role_templates', $column)) {
                        DB::table('org_structure_role_templates')
                            ->whereNotNull($column)
                            ->whereNotIn($column, $keepUserIds->all())
                            ->update([$column => null]);
                    }
                }
            }

            $keepUserUpdates = [];
            foreach ([
                'role' => 'super_admin',
                'is_active' => true,
                'school_id' => null,
                'department_id' => null,
                'department_role_id' => null,
                'onboarding_region_id' => null,
                'school_staff_type' => null,
            ] as $column => $value) {
                if (Schema::hasColumn('users', $column)) {
                    $keepUserUpdates[$column] = $value;
                }
            }

            if (!empty($keepUserUpdates)) {
                DB::table('users')
                    ->whereIn('id', $keepUserIds->all())
                    ->update($keepUserUpdates);
            }

            if (
                $superAdminRoleIds->isNotEmpty()
                && Schema::hasTable($modelHasRolesTable)
                && Schema::hasColumn($modelHasRolesTable, 'model_type')
                && Schema::hasColumn($modelHasRolesTable, $modelMorphKey)
                && Schema::hasColumn($modelHasRolesTable, $rolePivotKey)
            ) {
                $canonicalRoleId = (int) $superAdminRoleIds->first();
                foreach ($keepUserIds as $userId) {
                    $alreadyLinked = DB::table($modelHasRolesTable)
                        ->where('model_type', User::class)
                        ->where($modelMorphKey, (int) $userId)
                        ->whereIn($rolePivotKey, $superAdminRoleIds->all())
                        ->exists();

                    if (!$alreadyLinked) {
                        DB::table($modelHasRolesTable)->insert([
                            $rolePivotKey => $canonicalRoleId,
                            'model_type' => User::class,
                            $modelMorphKey => (int) $userId,
                        ]);
                    }
                }
            }

            $enableForeignKeys();
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            try {
                $enableForeignKeys();
            } catch (\Throwable) {
                // ignore secondary FK reset errors
            }

            $this->error('Purge failed: ' . $exception->getMessage());
            return Command::FAILURE;
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            // cache reset is best-effort
        }

        $this->info('Tenant/accounts data purge completed successfully.');
        $this->line("Truncated tables: {$truncatedTablesCount}");
        $this->line("Deleted non-super-admin users: {$deletedUsersCount}");
        $this->line('Kept super admin users:');
        foreach ($keptUsers as $user) {
            $this->line("- #{$user->id} {$user->name} <{$user->email}>");
        }

        return Command::SUCCESS;
    }
)->purpose('Delete all schools and school-related account data while keeping super admin users only.');
