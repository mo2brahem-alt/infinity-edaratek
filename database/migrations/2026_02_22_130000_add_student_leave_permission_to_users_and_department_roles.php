<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'can_manage_student_leaves')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('can_manage_student_leaves')->nullable();
            });
        }

        if (Schema::hasTable('department_roles') && !Schema::hasColumn('department_roles', 'can_manage_student_leaves')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $table->boolean('can_manage_student_leaves')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'can_manage_student_leaves')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('can_manage_student_leaves');
            });
        }

        if (Schema::hasTable('department_roles') && Schema::hasColumn('department_roles', 'can_manage_student_leaves')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $table->dropColumn('can_manage_student_leaves');
            });
        }
    }
};

