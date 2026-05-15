<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_subjects')) {
            return;
        }

        if (!Schema::hasColumn('school_subjects', 'branches')) {
            Schema::table('school_subjects', function (Blueprint $table): void {
                $table->json('branches')->nullable()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_subjects')) {
            return;
        }

        if (Schema::hasColumn('school_subjects', 'branches')) {
            Schema::table('school_subjects', function (Blueprint $table): void {
                $table->dropColumn('branches');
            });
        }
    }
};

