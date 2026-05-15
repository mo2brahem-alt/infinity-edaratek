<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educational_directorates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('governorate_id')->nullable()->after('country_id');
            $table->foreignId('education_type_id')->nullable()->after('governorate_id');

            $table->foreign('country_id', 'edir_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('governorate_id', 'edir_governorate_fk')
                ->references('id')
                ->on('governorates')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'edir_education_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->unique(
                ['country_id', 'governorate_id', 'education_type_id'],
                'edir_scope_unique'
            );
            $table->index(['country_id', 'governorate_id'], 'edir_country_governorate_index');
            $table->index(['education_type_id'], 'edir_education_type_index');
        });

        Schema::table('school_default_stage_templates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('education_type_id')->nullable()->after('country_id');

            $table->foreign('country_id', 'sd_stage_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'sd_stage_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->dropUnique('school_default_stage_templates_name_unique');
            $table->unique(
                ['country_id', 'education_type_id', 'name'],
                'sd_stage_scope_name_unique'
            );
            $table->index(['country_id', 'education_type_id', 'is_active'], 'sd_stage_scope_active_index');
        });

        Schema::table('school_default_academic_year_templates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('education_type_id')->nullable()->after('country_id');

            $table->foreign('country_id', 'sd_year_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'sd_year_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->dropUnique('school_default_academic_year_templates_name_unique');
            $table->unique(
                ['country_id', 'education_type_id', 'name'],
                'sd_year_scope_name_unique'
            );
            $table->index(['country_id', 'education_type_id', 'is_active'], 'sd_year_scope_active_index');
        });

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('education_type_id')->nullable()->after('country_id');

            $table->foreign('country_id', 'sd_holiday_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'sd_holiday_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->index(['country_id', 'education_type_id', 'is_active'], 'sd_holiday_scope_active_index');
        });

        Schema::table('school_default_leave_type_templates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('education_type_id')->nullable()->after('country_id');

            $table->foreign('country_id', 'sd_leave_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'sd_leave_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->dropUnique('school_default_leave_type_templates_name_unique');
            $table->unique(
                ['country_id', 'education_type_id', 'name'],
                'sd_leave_scope_name_unique'
            );
            $table->index(['country_id', 'education_type_id', 'is_active'], 'sd_leave_scope_active_index');
        });

        Schema::table('school_default_subject_templates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id');
            $table->foreignId('education_type_id')->nullable()->after('country_id');

            $table->foreign('country_id', 'sd_subject_country_fk')
                ->references('id')
                ->on('countries')
                ->nullOnDelete();
            $table->foreign('education_type_id', 'sd_subject_type_fk')
                ->references('id')
                ->on('education_types')
                ->nullOnDelete();

            $table->dropUnique('school_default_subject_templates_name_unique');
            $table->unique(
                ['country_id', 'education_type_id', 'name'],
                'sd_subject_scope_name_unique'
            );
            $table->index(['country_id', 'education_type_id', 'is_active'], 'sd_subject_scope_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('school_default_subject_templates', function (Blueprint $table): void {
            $table->dropUnique('sd_subject_scope_name_unique');
            $table->dropIndex('sd_subject_scope_active_index');
            $table->dropForeign('sd_subject_country_fk');
            $table->dropForeign('sd_subject_type_fk');
            $table->dropColumn(['country_id', 'education_type_id']);
            $table->unique('name', 'school_default_subject_templates_name_unique');
        });

        Schema::table('school_default_leave_type_templates', function (Blueprint $table): void {
            $table->dropUnique('sd_leave_scope_name_unique');
            $table->dropIndex('sd_leave_scope_active_index');
            $table->dropForeign('sd_leave_country_fk');
            $table->dropForeign('sd_leave_type_fk');
            $table->dropColumn(['country_id', 'education_type_id']);
            $table->unique('name', 'school_default_leave_type_templates_name_unique');
        });

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->dropIndex('sd_holiday_scope_active_index');
            $table->dropForeign('sd_holiday_country_fk');
            $table->dropForeign('sd_holiday_type_fk');
            $table->dropColumn(['country_id', 'education_type_id']);
        });

        Schema::table('school_default_academic_year_templates', function (Blueprint $table): void {
            $table->dropUnique('sd_year_scope_name_unique');
            $table->dropIndex('sd_year_scope_active_index');
            $table->dropForeign('sd_year_country_fk');
            $table->dropForeign('sd_year_type_fk');
            $table->dropColumn(['country_id', 'education_type_id']);
            $table->unique('name', 'school_default_academic_year_templates_name_unique');
        });

        Schema::table('school_default_stage_templates', function (Blueprint $table): void {
            $table->dropUnique('sd_stage_scope_name_unique');
            $table->dropIndex('sd_stage_scope_active_index');
            $table->dropForeign('sd_stage_country_fk');
            $table->dropForeign('sd_stage_type_fk');
            $table->dropColumn(['country_id', 'education_type_id']);
            $table->unique('name', 'school_default_stage_templates_name_unique');
        });

        Schema::table('educational_directorates', function (Blueprint $table): void {
            $table->dropUnique('edir_scope_unique');
            $table->dropIndex('edir_country_governorate_index');
            $table->dropIndex('edir_education_type_index');
            $table->dropForeign('edir_country_fk');
            $table->dropForeign('edir_governorate_fk');
            $table->dropForeign('edir_education_type_fk');
            $table->dropColumn(['country_id', 'governorate_id', 'education_type_id']);
        });
    }
};
