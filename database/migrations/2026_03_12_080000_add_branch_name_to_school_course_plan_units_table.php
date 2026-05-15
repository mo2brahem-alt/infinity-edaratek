<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_course_plan_units')) {
            return;
        }

        if (!Schema::hasColumn('school_course_plan_units', 'branch_name')) {
            Schema::table('school_course_plan_units', function (Blueprint $table): void {
                $table->string('branch_name', 150)->nullable()->after('school_course_offering_id');
                $table->index(
                    ['school_id', 'school_course_offering_id', 'branch_name'],
                    'school_course_plan_units_branch_scope_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_course_plan_units')) {
            return;
        }

        if (Schema::hasColumn('school_course_plan_units', 'branch_name')) {
            Schema::table('school_course_plan_units', function (Blueprint $table): void {
                $table->dropColumn('branch_name');
            });
        }
    }
};
