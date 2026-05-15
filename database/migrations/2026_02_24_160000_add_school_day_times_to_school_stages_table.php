<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_stages')) {
            return;
        }

        $hasStart = Schema::hasColumn('school_stages', 'school_day_start_time');
        $hasEnd = Schema::hasColumn('school_stages', 'school_day_end_time');

        if (!$hasStart || !$hasEnd) {
            Schema::table('school_stages', function (Blueprint $table) use ($hasStart, $hasEnd): void {
                if (!$hasStart) {
                    $table->time('school_day_start_time')->nullable()->after('sort_order');
                }

                if (!$hasEnd) {
                    $table->time('school_day_end_time')->nullable()->after('school_day_start_time');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_stages')) {
            return;
        }

        $drop = [];
        if (Schema::hasColumn('school_stages', 'school_day_start_time')) {
            $drop[] = 'school_day_start_time';
        }
        if (Schema::hasColumn('school_stages', 'school_day_end_time')) {
            $drop[] = 'school_day_end_time';
        }

        if (count($drop) > 0) {
            Schema::table('school_stages', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }
};
