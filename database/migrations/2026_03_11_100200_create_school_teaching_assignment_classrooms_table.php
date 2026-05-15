<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_teaching_assignment_classrooms')) {
            return;
        }

        Schema::create('school_teaching_assignment_classrooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_teaching_assignment_id')->constrained('school_teaching_assignments')->cascadeOnDelete();
            $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_teaching_assignment_id', 'school_classroom_id'],
                'school_teaching_assignment_classrooms_unique_assignment_classroom'
            );
            $table->index(
                ['school_id', 'school_classroom_id'],
                'school_teaching_assignment_classrooms_scope_classroom_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_teaching_assignment_classrooms');
    }
};
