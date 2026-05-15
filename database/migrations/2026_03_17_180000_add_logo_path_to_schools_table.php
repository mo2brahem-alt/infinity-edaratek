<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'logo_path')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->string('logo_path')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'logo_path')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->dropColumn('logo_path');
            });
        }
    }
};
