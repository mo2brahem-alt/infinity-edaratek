<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_teaching_assignments', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_teaching_assignments', 'can_create_exam')) {
                $table->boolean('can_create_exam')->default(true)->after('is_active');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_update_exam')) {
                $table->boolean('can_update_exam')->default(true)->after('can_create_exam');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_delete_exam')) {
                $table->boolean('can_delete_exam')->default(true)->after('can_update_exam');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_approve_exam')) {
                $table->boolean('can_approve_exam')->default(false)->after('can_delete_exam');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_enter_exam_scores')) {
                $table->boolean('can_enter_exam_scores')->default(true)->after('can_approve_exam');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_edit_exam_scores')) {
                $table->boolean('can_edit_exam_scores')->default(true)->after('can_enter_exam_scores');
            }

            if (!Schema::hasColumn('school_teaching_assignments', 'can_use_question_bank')) {
                $table->boolean('can_use_question_bank')->default(true)->after('can_edit_exam_scores');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_teaching_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('school_teaching_assignments', 'can_use_question_bank')) {
                $table->dropColumn('can_use_question_bank');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_edit_exam_scores')) {
                $table->dropColumn('can_edit_exam_scores');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_enter_exam_scores')) {
                $table->dropColumn('can_enter_exam_scores');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_approve_exam')) {
                $table->dropColumn('can_approve_exam');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_delete_exam')) {
                $table->dropColumn('can_delete_exam');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_update_exam')) {
                $table->dropColumn('can_update_exam');
            }
            if (Schema::hasColumn('school_teaching_assignments', 'can_create_exam')) {
                $table->dropColumn('can_create_exam');
            }
        });
    }
};
