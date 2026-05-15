<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table): void {
                if (!Schema::hasColumn('departments', 'school_id')) {
                    $table->foreignId('school_id')
                        ->nullable()
                        ->after('name')
                        ->constrained('schools')
                        ->nullOnDelete();

                    $table->index(['school_id', 'name']);
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'school_staff_type')) {
                    $table->string('school_staff_type')
                        ->nullable()
                        ->after('school_id')
                        ->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'school_staff_type')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('school_staff_type');
            });
        }

        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'school_id')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropIndex(['school_id', 'name']);
                $table->dropConstrainedForeignId('school_id');
            });
        }
    }
};
