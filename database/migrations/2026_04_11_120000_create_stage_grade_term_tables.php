<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recover cleanly from a previously failed MySQL migration where the
        // first table was created before a long foreign-key identifier crashed.
        if (
            Schema::hasTable('school_default_stage_grade_term_templates')
            && ! Schema::hasTable('school_stage_grade_terms')
        ) {
            Schema::drop('school_default_stage_grade_term_templates');
        }

        if (! Schema::hasTable('school_default_stage_grade_term_templates')) {
            Schema::create('school_default_stage_grade_term_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('school_default_stage_grade_template_id');
                $table->string('name', 100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['school_default_stage_grade_template_id', 'name'],
                    'sd_stage_grade_term_templates_scope_name_unique'
                );
                $table->index(
                    ['school_default_stage_grade_template_id', 'is_active', 'sort_order'],
                    'sd_stage_grade_term_templates_scope_sort_index'
                );

                $table->foreign(
                    'school_default_stage_grade_template_id',
                    'sdsgtt_grade_template_fk'
                )->references('id')->on('school_default_stage_grade_templates')->cascadeOnDelete();
                $table->foreign('created_by', 'sdsgtt_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'sdsgtt_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('school_stage_grade_terms')) {
            Schema::create('school_stage_grade_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('school_id');
                $table->unsignedBigInteger('school_stage_grade_id');
                $table->string('name', 100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['school_id', 'school_stage_grade_id', 'name'],
                    'school_stage_grade_terms_scope_name_unique'
                );
                $table->index(
                    ['school_id', 'school_stage_grade_id', 'sort_order'],
                    'school_stage_grade_terms_scope_sort_index'
                );

                $table->foreign('school_id', 'ssgt_school_fk')
                    ->references('id')->on('schools')->cascadeOnDelete();
                $table->foreign('school_stage_grade_id', 'ssgt_stage_grade_fk')
                    ->references('id')->on('school_stage_grades')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_stage_grade_terms');
        Schema::dropIfExists('school_default_stage_grade_term_templates');
    }
};
