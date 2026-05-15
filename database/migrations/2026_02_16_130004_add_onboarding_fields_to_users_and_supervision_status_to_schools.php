<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'onboarding_region_id')) {
                $table->foreignId('onboarding_region_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained('educational_directorates')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')
                    ->nullable()
                    ->after('onboarding_region_id');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'supervision_status')) {
                $table->string('supervision_status')
                    ->nullable()
                    ->default('SUSPENDED')
                    ->after('status')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->dropColumn('onboarding_completed_at');
            }

            if (Schema::hasColumn('users', 'onboarding_region_id')) {
                $table->dropConstrainedForeignId('onboarding_region_id');
            }
        });

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'supervision_status')) {
                $table->dropColumn('supervision_status');
            }
        });
    }
};
