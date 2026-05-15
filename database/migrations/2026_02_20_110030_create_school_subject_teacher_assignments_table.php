<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_subject_teacher_assignments')) {
            return;
        }

        Schema::create('school_subject_teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_subject_id', 'teacher_user_id'], 'subject_teacher_unique_assignment');
            $table->index(['school_id', 'teacher_user_id'], 'subject_teacher_school_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_subject_teacher_assignments');
    }
};
