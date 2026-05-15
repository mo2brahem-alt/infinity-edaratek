<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departments') && !Schema::hasColumn('departments', 'staff_type')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->string('staff_type')->nullable()->after('name')->index();
            });
        }

        if (!Schema::hasTable('department_roles')) {
            Schema::create('department_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['department_id', 'name']);
                $table->index(['department_id', 'is_active']);
            });
        }

        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'staff_type')) {
            DB::table('departments')
                ->whereNull('school_id')
                ->whereNull('staff_type')
                ->update(['staff_type' => 'ADMINISTRATIVE']);
        }

        if (Schema::hasTable('department_roles')) {
            $globalDepartmentIds = DB::table('departments')
                ->whereNull('school_id')
                ->pluck('id');

            foreach ($globalDepartmentIds as $departmentId) {
                $hasAnyRole = DB::table('department_roles')
                    ->where('department_id', $departmentId)
                    ->exists();

                if (!$hasAnyRole) {
                    DB::table('department_roles')->insert([
                        'department_id' => $departmentId,
                        'name' => 'General Staff',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'department_role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('department_role_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('department_roles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_role_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('department_role_id');
            });
        }

        Schema::dropIfExists('department_roles');

        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'staff_type')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropColumn('staff_type');
            });
        }
    }
};
