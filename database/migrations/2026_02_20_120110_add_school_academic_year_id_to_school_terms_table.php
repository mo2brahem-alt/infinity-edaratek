<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_terms') || Schema::hasColumn('school_terms', 'school_academic_year_id')) {
            return;
        }

        Schema::table('school_terms', function (Blueprint $table): void {
            $table->foreignId('school_academic_year_id')
                ->nullable()
                ->after('school_id')
                ->constrained('school_academic_years')
                ->nullOnDelete();

            $table->index(['school_id', 'school_academic_year_id'], 'school_terms_year_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_terms') || !Schema::hasColumn('school_terms', 'school_academic_year_id')) {
            return;
        }

        Schema::table('school_terms', function (Blueprint $table): void {
            $table->dropIndex('school_terms_year_index');
            $table->dropConstrainedForeignId('school_academic_year_id');
        });
    }
};
