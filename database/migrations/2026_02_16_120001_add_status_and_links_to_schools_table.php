<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'status')) {
                $table->string('status')->default('SUSPENDED')->after('notes')->index();
            }

            if (!Schema::hasColumn('schools', 'supervisor_id')) {
                $table->foreignId('supervisor_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('schools', 'manager_user_id')) {
                $table->foreignId('manager_user_id')
                    ->nullable()
                    ->after('supervisor_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'manager_user_id')) {
                $table->dropUnique(['manager_user_id']);
                $table->dropConstrainedForeignId('manager_user_id');
            }

            if (Schema::hasColumn('schools', 'supervisor_id')) {
                $table->dropConstrainedForeignId('supervisor_id');
            }

            if (Schema::hasColumn('schools', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
