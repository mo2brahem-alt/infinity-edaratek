<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_stage_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_stage_id')->constrained('school_stages')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['school_id', 'school_stage_id', 'name'],
                'school_stage_grades_scope_name_unique'
            );
            $table->index(
                ['school_id', 'school_stage_id', 'sort_order'],
                'school_stage_grades_scope_sort_index'
            );
        });

        $classrooms = DB::table('school_classrooms')
            ->select(['school_id', 'school_stage_id', 'grade_name'])
            ->orderBy('school_id')
            ->orderBy('school_stage_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $insertRows = [];
        $seenGrades = [];
        $sortOrderByStage = [];
        $now = now();

        foreach ($classrooms as $classroom) {
            $schoolId = (int) ($classroom->school_id ?? 0);
            $stageId = (int) ($classroom->school_stage_id ?? 0);
            $gradeName = trim((string) ($classroom->grade_name ?? ''));

            if ($schoolId <= 0 || $stageId <= 0 || $gradeName === '') {
                continue;
            }

            $scopeKey = $schoolId . ':' . $stageId;
            $gradeKey = $scopeKey . ':' . strtolower($gradeName);

            if (isset($seenGrades[$gradeKey])) {
                continue;
            }

            $seenGrades[$gradeKey] = true;
            $nextSortOrder = ((int) ($sortOrderByStage[$scopeKey] ?? 0)) + 1;
            $sortOrderByStage[$scopeKey] = $nextSortOrder;

            $insertRows[] = [
                'school_id' => $schoolId,
                'school_stage_id' => $stageId,
                'name' => $gradeName,
                'sort_order' => $nextSortOrder,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($insertRows) > 0) {
            DB::table('school_stage_grades')->insert($insertRows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_stage_grades');
    }
};
