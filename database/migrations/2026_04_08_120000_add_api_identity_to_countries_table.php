<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->string('iso2_code', 2)->nullable()->after('name');
            $table->string('api_source')->nullable()->after('iso2_code');
            $table->timestamp('api_synced_at')->nullable()->after('api_source');

            $table->unique('iso2_code', 'countries_iso2_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->dropUnique('countries_iso2_code_unique');
            $table->dropColumn(['iso2_code', 'api_source', 'api_synced_at']);
        });
    }
};
