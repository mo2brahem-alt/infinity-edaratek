<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_course_offerings')) {
            return;
        }

        Schema::create('school_course_offerings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_term_id')->constrained('school_terms')->cascadeOnDelete();
            $table->foreignId('school_stage_id')->constrained('school_stages')->cascadeOnDelete();
            $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_term_id', 'school_classroom_id', 'school_subject_id'],
                'school_course_offering_unique_term_class_subject'
            );
            $table->index(['school_id', 'school_term_id', 'school_classroom_id'], 'school_course_offering_filter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_course_offerings');
    }
};
