<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_teaching_assignments')) {
            return;
        }

        Schema::create('school_teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_course_offering_id')->constrained('school_course_offerings')->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_course_offering_id'], 'school_teaching_assignment_unique_offering');
            $table->index(['school_id', 'teacher_user_id'], 'school_teaching_assignment_teacher_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_teaching_assignments');
    }
};
