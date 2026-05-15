<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->timestamp('default_data_imported_at')->nullable()->after('logo_path');
            $table->foreignId('default_data_imported_by')
                ->nullable()
                ->after('default_data_imported_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_data_imported_by');
            $table->dropColumn('default_data_imported_at');
        });
    }
};
