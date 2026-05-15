<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'can_manage_leave_types')) {
                    $table->boolean('can_manage_leave_types')->nullable();
                }

                if (!Schema::hasColumn('users', 'can_manage_school_calendar')) {
                    $table->boolean('can_manage_school_calendar')->nullable();
                }

                if (!Schema::hasColumn('users', 'can_manage_school_holidays')) {
                    $table->boolean('can_manage_school_holidays')->nullable();
                }
            });
        }

        if (Schema::hasTable('department_roles')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                if (!Schema::hasColumn('department_roles', 'can_manage_leave_types')) {
                    $table->boolean('can_manage_leave_types')->default(false);
                }

                if (!Schema::hasColumn('department_roles', 'can_manage_school_calendar')) {
                    $table->boolean('can_manage_school_calendar')->default(false);
                }

                if (!Schema::hasColumn('department_roles', 'can_manage_school_holidays')) {
                    $table->boolean('can_manage_school_holidays')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                $columns = collect([
                    'can_manage_leave_types',
                    'can_manage_school_calendar',
                    'can_manage_school_holidays',
                ])->filter(fn (string $column): bool => Schema::hasColumn('users', $column))->values()->all();

                if (count($columns) > 0) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('department_roles')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $columns = collect([
                    'can_manage_leave_types',
                    'can_manage_school_calendar',
                    'can_manage_school_holidays',
                ])->filter(fn (string $column): bool => Schema::hasColumn('department_roles', $column))->values()->all();

                if (count($columns) > 0) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};

