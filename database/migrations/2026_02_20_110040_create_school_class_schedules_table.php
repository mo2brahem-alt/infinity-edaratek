<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_class_schedules')) {
            return;
        }

        Schema::create('school_class_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_term_id')->constrained('school_terms')->cascadeOnDelete();
            $table->foreignId('school_stage_id')->constrained('school_stages')->cascadeOnDelete();
            $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('schedule_scope', 20);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('session_date')->nullable();
            $table->unsignedSmallInteger('session_index');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'school_term_id', 'school_classroom_id'], 'school_schedule_filter_index');
            $table->index(['school_id', 'teacher_user_id', 'schedule_scope'], 'school_schedule_teacher_index');
            $table->index(['school_id', 'schedule_scope', 'day_of_week', 'day_of_month', 'session_date'], 'school_schedule_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_schedules');
    }
};
