<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classrooms', function (Blueprint $table): void {
            $table->string('grade_name', 100)->default('غير محدد')->after('name');

            $table->dropUnique(['school_id', 'school_stage_id', 'name']);
            $table->unique(
                ['school_id', 'school_stage_id', 'grade_name', 'name'],
                'school_classrooms_scope_grade_name_unique'
            );

            $table->dropIndex(['school_id', 'school_stage_id', 'sort_order']);
            $table->index(
                ['school_id', 'school_stage_id', 'grade_name', 'sort_order'],
                'school_classrooms_scope_grade_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('school_classrooms', function (Blueprint $table): void {
            $table->dropIndex('school_classrooms_scope_grade_sort_index');
            $table->dropUnique('school_classrooms_scope_grade_name_unique');

            $table->unique(['school_id', 'school_stage_id', 'name']);
            $table->index(['school_id', 'school_stage_id', 'sort_order']);

            $table->dropColumn('grade_name');
        });
    }
};
