<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_exam_settings')) {
            Schema::create('school_exam_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->boolean('allow_subject_schedule_slot_overlap')->default(false);
                $table->time('exam_day_start_time')->nullable();
                $table->time('exam_day_end_time')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('school_id', 'school_exam_settings_school_unique');
            });
        }

        if (!Schema::hasTable('school_exam_templates')) {
            Schema::create('school_exam_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->string('name', 191);
                $table->string('exam_type', 30);
                $table->decimal('default_max_score', 8, 2)->default(100);
                $table->decimal('default_passing_score', 8, 2)->default(50);
                $table->boolean('requires_approval')->default(false);
                $table->boolean('teacher_can_override_max_score')->default(true);
                $table->boolean('teacher_can_override_passing_score')->default(true);
                $table->boolean('affects_final_result')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'name'], 'school_exam_templates_scope_name_unique');
                $table->index(['school_id', 'is_active', 'sort_order'], 'school_exam_templates_scope_active_sort_idx');
            });
        }

        if (!Schema::hasTable('school_exams')) {
            Schema::create('school_exams', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_exam_template_id')->nullable()->constrained('school_exam_templates')->nullOnDelete();
                $table->foreignId('school_term_id')->constrained('school_terms')->cascadeOnDelete();
                $table->foreignId('school_stage_id')->constrained('school_stages')->cascadeOnDelete();
                $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
                $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
                $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title', 191);
                $table->date('exam_date');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->decimal('max_score', 8, 2);
                $table->decimal('passing_score', 8, 2);
                $table->string('status', 30)->default('draft');
                $table->boolean('requires_approval')->default(false);
                $table->boolean('allow_subject_schedule_overlap')->default(false);
                $table->boolean('affects_final_result')->default(true);
                $table->string('room_label', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('postpone_reason')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'status'], 'school_exams_scope_status_idx');
                $table->index(['school_id', 'exam_date', 'starts_at', 'ends_at'], 'school_exams_scope_datetime_idx');
                $table->index(['school_id', 'teacher_user_id', 'exam_date'], 'school_exams_scope_teacher_date_idx');
                $table->index(['school_id', 'school_classroom_id', 'exam_date'], 'school_exams_scope_classroom_date_idx');
                $table->index(['school_id', 'room_label', 'exam_date'], 'school_exams_scope_room_date_idx');
            });
        }

        if (!Schema::hasTable('school_question_bank_items')) {
            Schema::create('school_question_bank_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
                $table->foreignId('school_stage_id')->nullable()->constrained('school_stages')->nullOnDelete();
                $table->foreignId('school_term_id')->nullable()->constrained('school_terms')->nullOnDelete();
                $table->string('unit_name', 150)->nullable();
                $table->string('lesson_name', 150)->nullable();
                $table->longText('question_text');
                $table->string('question_type', 40);
                $table->decimal('question_score', 8, 2)->default(1);
                $table->string('selection_mode', 20)->default('required');
                $table->string('difficulty', 20)->default('medium');
                $table->string('learning_outcome', 255)->nullable();
                $table->longText('model_answer')->nullable();
                $table->longText('answer_explanation')->nullable();
                $table->string('status', 20)->default('active');
                $table->json('tags')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'school_subject_id', 'status'], 'school_questions_scope_subject_status_idx');
                $table->index(['school_id', 'question_type', 'difficulty'], 'school_questions_scope_type_difficulty_idx');
            });
        }

        if (!Schema::hasTable('school_question_options')) {
            Schema::create('school_question_options', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_question_bank_item_id')->constrained('school_question_bank_items')->cascadeOnDelete();
                $table->text('option_text');
                $table->boolean('is_correct')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['school_id', 'school_question_bank_item_id'], 'school_question_options_scope_question_idx');
            });
        }

        if (!Schema::hasTable('school_exam_questions')) {
            Schema::create('school_exam_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_exam_id')->constrained('school_exams')->cascadeOnDelete();
                $table->foreignId('school_question_bank_item_id')->constrained('school_question_bank_items')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->decimal('score', 8, 2)->default(1);
                $table->boolean('is_required')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['school_exam_id', 'school_question_bank_item_id'], 'school_exam_questions_unique_exam_question');
                $table->index(['school_id', 'school_exam_id', 'sort_order'], 'school_exam_questions_scope_exam_sort_idx');
            });
        }

        if (!Schema::hasTable('school_exam_student_scores')) {
            Schema::create('school_exam_student_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_exam_id')->constrained('school_exams')->cascadeOnDelete();
                $table->foreignId('school_student_id')->constrained('school_students')->cascadeOnDelete();
                $table->decimal('score', 8, 2)->nullable();
                $table->string('attendance_status', 20)->default('present');
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_finalized')->default(false);
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();

                $table->unique(['school_exam_id', 'school_student_id'], 'school_exam_student_scores_unique_exam_student');
                $table->index(['school_id', 'school_exam_id', 'attendance_status'], 'school_exam_student_scores_scope_exam_status_idx');
            });
        }

        if (!Schema::hasTable('school_exam_status_logs')) {
            Schema::create('school_exam_status_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_exam_id')->constrained('school_exams')->cascadeOnDelete();
                $table->string('old_status', 30)->nullable();
                $table->string('new_status', 30);
                $table->text('reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('changed_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'school_exam_id'], 'school_exam_status_logs_scope_exam_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_exam_status_logs');
        Schema::dropIfExists('school_exam_student_scores');
        Schema::dropIfExists('school_exam_questions');
        Schema::dropIfExists('school_question_options');
        Schema::dropIfExists('school_question_bank_items');
        Schema::dropIfExists('school_exams');
        Schema::dropIfExists('school_exam_templates');
        Schema::dropIfExists('school_exam_settings');
    }
};
