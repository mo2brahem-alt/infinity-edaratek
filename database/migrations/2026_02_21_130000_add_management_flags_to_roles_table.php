<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDisplayName = Schema::hasColumn('roles', 'display_name');
        $hasDescription = Schema::hasColumn('roles', 'description');
        $hasAssignable = Schema::hasColumn('roles', 'assignable_by_school_admin');
        $hasSystem = Schema::hasColumn('roles', 'is_system');

        if (!$hasDisplayName || !$hasDescription || !$hasAssignable || !$hasSystem) {
            Schema::table('roles', function (Blueprint $table) use ($hasDisplayName, $hasDescription, $hasAssignable, $hasSystem): void {
                if (!$hasDisplayName) {
                    $table->string('display_name')->nullable();
                }

                if (!$hasDescription) {
                    $table->text('description')->nullable();
                }

                if (!$hasAssignable) {
                    $table->boolean('assignable_by_school_admin')->default(false);
                }

                if (!$hasSystem) {
                    $table->boolean('is_system')->default(false);
                }
            });
        }

        if (Schema::hasColumn('roles', 'is_system')) {
            DB::table('roles')
                ->whereIn('name', ['super_admin', 'supervisor', 'school_manager', 'student', 'parent'])
                ->update(['is_system' => true]);

            DB::table('roles')
                ->whereIn('name', ['staff', 'teacher'])
                ->update(['is_system' => false]);
        }

        if (Schema::hasColumn('roles', 'assignable_by_school_admin')) {
            DB::table('roles')
                ->whereIn('name', ['staff', 'teacher'])
                ->update(['assignable_by_school_admin' => true]);

            DB::table('roles')
                ->whereIn('name', ['super_admin', 'supervisor', 'school_manager', 'student', 'parent'])
                ->update(['assignable_by_school_admin' => false]);
        }
    }

    public function down(): void
    {
        $dropColumns = [];

        foreach (['display_name', 'description', 'assignable_by_school_admin', 'is_system'] as $column) {
            if (Schema::hasColumn('roles', $column)) {
                $dropColumns[] = $column;
            }
        }

        if (count($dropColumns) > 0) {
            Schema::table('roles', function (Blueprint $table) use ($dropColumns): void {
                $table->dropColumn($dropColumns);
            });
        }
    }
};

