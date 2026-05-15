<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_course_plan_units')) {
            Schema::create('school_course_plan_units', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_course_offering_id')->constrained('school_course_offerings')->cascadeOnDelete();
                $table->string('name', 150);
                $table->unsignedInteger('sort_order')->default(0);
                $table->date('start_date');
                $table->date('end_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(
                    ['school_id', 'school_course_offering_id', 'sort_order'],
                    'school_course_plan_units_scope_sort_index'
                );
            });
        }

        if (!Schema::hasTable('school_course_plan_lessons')) {
            Schema::create('school_course_plan_lessons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_course_plan_unit_id')->constrained('school_course_plan_units')->cascadeOnDelete();
                $table->string('name', 150);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('description', 1000)->nullable();
                $table->timestamps();

                $table->index(
                    ['school_id', 'school_course_plan_unit_id', 'sort_order'],
                    'school_course_plan_lessons_scope_sort_index'
                );
            });
        }

        if (!Schema::hasTable('school_course_plan_topics')) {
            Schema::create('school_course_plan_topics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('school_course_plan_lesson_id')->constrained('school_course_plan_lessons')->cascadeOnDelete();
                $table->string('name', 150);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('description', 1000)->nullable();
                $table->timestamps();

                $table->index(
                    ['school_id', 'school_course_plan_lesson_id', 'sort_order'],
                    'school_course_plan_topics_scope_sort_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_course_plan_topics');
        Schema::dropIfExists('school_course_plan_lessons');
        Schema::dropIfExists('school_course_plan_units');
    }
};
