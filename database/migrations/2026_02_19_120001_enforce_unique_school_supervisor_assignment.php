<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_supervisor_assignments')) {
            return;
        }

        // Keep only the newest row per school before adding uniqueness.
        $duplicateSchoolIds = DB::table('school_supervisor_assignments')
            ->whereNotNull('school_id')
            ->groupBy('school_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('school_id');

        foreach ($duplicateSchoolIds as $schoolId) {
            $rowToKeepId = DB::table('school_supervisor_assignments')
                ->where('school_id', $schoolId)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->value('id');

            DB::table('school_supervisor_assignments')
                ->where('school_id', $schoolId)
                ->where('id', '!=', $rowToKeepId)
                ->delete();
        }

        Schema::table('school_supervisor_assignments', function (Blueprint $table): void {
            $table->unique('school_id', 'school_supervisor_assignments_school_id_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_supervisor_assignments')) {
            return;
        }

        Schema::table('school_supervisor_assignments', function (Blueprint $table): void {
            $table->dropUnique('school_supervisor_assignments_school_id_unique');
        });
    }
};
