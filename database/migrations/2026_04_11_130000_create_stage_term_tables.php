<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recover cleanly from a previously interrupted run where one table
        // was created but the paired table never finished.
        if (
            Schema::hasTable('school_default_stage_term_templates')
            && ! Schema::hasTable('school_stage_terms')
        ) {
            Schema::drop('school_default_stage_term_templates');
        }

        if (! Schema::hasTable('school_default_stage_term_templates')) {
            Schema::create('school_default_stage_term_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('school_default_stage_template_id');
                $table->string('name', 100);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('source', 20)->default('default');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['school_default_stage_template_id', 'name'],
                    'sd_stage_term_templates_scope_name_unique'
                );
                $table->index(
                    ['school_default_stage_template_id', 'is_active', 'sort_order'],
                    'sd_stage_term_templates_scope_sort_index'
                );

                $table->foreign(
                    'school_default_stage_template_id',
                    'sdstt_stage_template_fk'
                )->references('id')->on('school_default_stage_templates')->cascadeOnDelete();
                $table->foreign('created_by', 'sdstt_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'sdstt_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('school_stage_terms')) {
            Schema::create('school_stage_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('school_id');
                $table->unsignedBigInteger('school_stage_id');
                $table->string('name', 100);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('source', 20)->default('default');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['school_id', 'school_stage_id', 'name'],
                    'school_stage_terms_scope_name_unique'
                );
                $table->index(
                    ['school_id', 'school_stage_id', 'is_active', 'sort_order'],
                    'school_stage_terms_scope_sort_index'
                );

                $table->foreign('school_id', 'sst_school_fk')
                    ->references('id')->on('schools')->cascadeOnDelete();
                $table->foreign('school_stage_id', 'sst_stage_fk')
                    ->references('id')->on('school_stages')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_stage_terms');
        Schema::dropIfExists('school_default_stage_term_templates');
    }
};
