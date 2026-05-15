<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schools') || !Schema::hasColumn('schools', 'supervision_status')) {
            return;
        }

        DB::table('schools')
            ->where('status', School::STATUS_ACTIVE)
            ->whereNotNull('manager_user_id')
            ->whereNotNull('supervisor_id')
            ->where(function ($query): void {
                $query->whereNull('supervision_status')
                    ->orWhere('supervision_status', School::SUPERVISION_STATUS_SUSPENDED);
            })
            ->update([
                'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Backfill only.
    }
};
