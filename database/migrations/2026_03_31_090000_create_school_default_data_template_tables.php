<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_default_stage_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->time('school_day_start_time')->nullable();
            $table->time('school_day_end_time')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name', 'school_default_stage_templates_name_unique');
            $table->unique('code', 'school_default_stage_templates_code_unique');
            $table->index(['is_active', 'sort_order'], 'school_default_stage_templates_active_sort_index');
        });

        Schema::create('school_default_stage_grade_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_default_stage_template_id');
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                'school_default_stage_template_id',
                'sd_stage_grade_templates_stage_fk'
            )->references('id')->on('school_default_stage_templates')->cascadeOnDelete();

            $table->unique(
                ['school_default_stage_template_id', 'name'],
                'school_default_stage_grade_templates_scope_name_unique'
            );
            $table->index(
                ['school_default_stage_template_id', 'is_active', 'sort_order'],
                'school_default_stage_grade_templates_scope_active_sort_index'
            );
        });

        Schema::create('school_default_classroom_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_default_stage_template_id');
            $table->foreignId('school_default_stage_grade_template_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                'school_default_stage_template_id',
                'sd_classroom_templates_stage_fk'
            )->references('id')->on('school_default_stage_templates')->cascadeOnDelete();

            $table->foreign(
                'school_default_stage_grade_template_id',
                'sd_classroom_templates_grade_fk'
            )->references('id')->on('school_default_stage_grade_templates')->cascadeOnDelete();

            $table->unique(
                [
                    'school_default_stage_template_id',
                    'school_default_stage_grade_template_id',
                    'name',
                ],
                'school_default_classroom_templates_scope_name_unique'
            );
            $table->unique('code', 'school_default_classroom_templates_code_unique');
            $table->index(
                ['school_default_stage_template_id', 'school_default_stage_grade_template_id', 'is_active', 'sort_order'],
                'school_default_classroom_templates_scope_active_sort_index'
            );
        });

        Schema::create('school_default_academic_year_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name', 'school_default_academic_year_templates_name_unique');
            $table->index(['is_active', 'starts_on', 'ends_on'], 'school_default_academic_year_templates_active_period_index');
        });

        Schema::create('school_default_holiday_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('return_date')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date'], 'school_default_holiday_templates_active_period_index');
        });

        Schema::create('school_default_leave_type_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name', 'school_default_leave_type_templates_name_unique');
            $table->unique('code', 'school_default_leave_type_templates_code_unique');
            $table->index(['is_active', 'name'], 'school_default_leave_type_templates_active_name_index');
        });

        Schema::create('school_default_subject_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->json('branches')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('name', 'school_default_subject_templates_name_unique');
            $table->unique('code', 'school_default_subject_templates_code_unique');
            $table->index(['is_active', 'name'], 'school_default_subject_templates_active_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_default_subject_templates');
        Schema::dropIfExists('school_default_leave_type_templates');
        Schema::dropIfExists('school_default_holiday_templates');
        Schema::dropIfExists('school_default_academic_year_templates');
        Schema::dropIfExists('school_default_classroom_templates');
        Schema::dropIfExists('school_default_stage_grade_templates');
        Schema::dropIfExists('school_default_stage_templates');
    }
};
