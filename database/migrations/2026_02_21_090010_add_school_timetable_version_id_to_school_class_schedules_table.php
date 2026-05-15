<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_class_schedules')) {
            return;
        }

        Schema::table('school_class_schedules', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_class_schedules', 'school_timetable_version_id')) {
                $table->foreignId('school_timetable_version_id')
                    ->nullable()
                    ->after('school_term_id')
                    ->constrained('school_timetable_versions')
                    ->nullOnDelete();

                $table->index(
                    ['school_id', 'school_term_id', 'school_timetable_version_id'],
                    'school_schedule_term_version_filter_index'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_class_schedules')) {
            return;
        }

        Schema::table('school_class_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('school_class_schedules', 'school_timetable_version_id')) {
                $table->dropIndex('school_schedule_term_version_filter_index');
                $table->dropConstrainedForeignId('school_timetable_version_id');
            }
        });
    }
};
