<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_default_holiday_templates', 'reference_key')) {
                $table->string('reference_key', 120)->nullable()->after('name');
            }

            if (!Schema::hasColumn('school_default_holiday_templates', 'holiday_category')) {
                $table->string('holiday_category', 40)->nullable()->after('reference_key');
            }
        });

        Schema::table('school_holidays', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_holidays', 'reference_key')) {
                $table->string('reference_key', 120)->nullable()->after('name');
            }

            if (!Schema::hasColumn('school_holidays', 'holiday_category')) {
                $table->string('holiday_category', 40)->nullable()->after('reference_key');
            }
        });

        $this->makeTemplateHolidayDatesNullable();

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->index(
                ['country_id', 'education_type_id', 'directorate_id', 'reference_key'],
                'sd_holiday_reference_lookup_index'
            );
        });

        Schema::table('school_holidays', function (Blueprint $table): void {
            $table->index(
                ['school_id', 'reference_key'],
                'school_holidays_reference_lookup_index'
            );
        });
    }

    public function down(): void
    {
        $today = now()->toDateString();

        DB::table('school_default_holiday_templates')
            ->whereNull('start_date')
            ->update(['start_date' => $today]);

        DB::table('school_default_holiday_templates')
            ->whereNull('end_date')
            ->update(['end_date' => DB::raw('start_date')]);

        Schema::table('school_holidays', function (Blueprint $table): void {
            $table->dropIndex('school_holidays_reference_lookup_index');
            $table->dropColumn(['reference_key', 'holiday_category']);
        });

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->dropIndex('sd_holiday_reference_lookup_index');
            $table->dropColumn(['reference_key', 'holiday_category']);
        });

        $this->makeTemplateHolidayDatesRequired();
    }

    private function makeTemplateHolidayDatesNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `school_default_holiday_templates` MODIFY `start_date` DATE NULL');
            DB::statement('ALTER TABLE `school_default_holiday_templates` MODIFY `end_date` DATE NULL');

            return;
        }

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    private function makeTemplateHolidayDatesRequired(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `school_default_holiday_templates` MODIFY `start_date` DATE NOT NULL');
            DB::statement('ALTER TABLE `school_default_holiday_templates` MODIFY `end_date` DATE NOT NULL');

            return;
        }

        Schema::table('school_default_holiday_templates', function (Blueprint $table): void {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
