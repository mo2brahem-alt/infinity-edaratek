<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_course_offerings')) {
            return;
        }

        Schema::table('school_course_offerings', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_course_offerings', 'school_stage_grade_id')) {
                $table->foreignId('school_stage_grade_id')
                    ->nullable()
                    ->after('school_stage_id')
                    ->constrained('school_stage_grades')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('school_course_offerings', 'alert_before_term_end_days')) {
                $table->unsignedSmallInteger('alert_before_term_end_days')
                    ->default(0)
                    ->after('sort_order');
            }

            $table->index(
                ['school_id', 'school_term_id', 'school_stage_id', 'school_stage_grade_id'],
                'school_course_offerings_scope_stage_grade_index'
            );
        });

        $rows = DB::table('school_course_offerings')
            ->select(['id', 'school_id', 'school_stage_id', 'school_classroom_id', 'school_stage_grade_id'])
            ->whereNull('school_stage_grade_id')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $schoolId = (int) ($row->school_id ?? 0);
            $stageId = (int) ($row->school_stage_id ?? 0);
            $classroomId = (int) ($row->school_classroom_id ?? 0);
            if ($schoolId <= 0 || $stageId <= 0 || $classroomId <= 0) {
                continue;
            }

            $classroom = DB::table('school_classrooms')
                ->where('school_id', $schoolId)
                ->where('school_stage_id', $stageId)
                ->where('id', $classroomId)
                ->first(['grade_name']);

            $gradeName = trim((string) ($classroom->grade_name ?? ''));
            if ($gradeName === '') {
                continue;
            }

            $gradeId = DB::table('school_stage_grades')
                ->where('school_id', $schoolId)
                ->where('school_stage_id', $stageId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($gradeName)])
                ->value('id');

            if (!$gradeId) {
                $nextSortOrder = ((int) DB::table('school_stage_grades')
                    ->where('school_id', $schoolId)
                    ->where('school_stage_id', $stageId)
                    ->max('sort_order')) + 1;

                $gradeId = DB::table('school_stage_grades')->insertGetId([
                    'school_id' => $schoolId,
                    'school_stage_id' => $stageId,
                    'name' => $gradeName,
                    'sort_order' => $nextSortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ((int) $gradeId > 0) {
                DB::table('school_course_offerings')
                    ->where('id', (int) $row->id)
                    ->update([
                        'school_stage_grade_id' => (int) $gradeId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_course_offerings')) {
            return;
        }

        Schema::table('school_course_offerings', function (Blueprint $table): void {
            if (Schema::hasColumn('school_course_offerings', 'school_stage_grade_id')) {
                try {
                    $table->dropForeign(['school_stage_grade_id']);
                } catch (\Throwable $e) {
                    // no-op
                }

                $table->dropColumn('school_stage_grade_id');
            }

            if (Schema::hasColumn('school_course_offerings', 'alert_before_term_end_days')) {
                $table->dropColumn('alert_before_term_end_days');
            }

            try {
                $table->dropIndex('school_course_offerings_scope_stage_grade_index');
            } catch (\Throwable $e) {
                // no-op
            }
        });
    }
};
