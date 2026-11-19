<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('firebase_project_id', 128)->collation('utf8mb4_bin');
            $table->string('firebase_uid', 128)->collation('utf8mb4_bin');
            $table->string('provider', 32)->collation('utf8mb4_bin');
            $table->string('provider_uid', 255)->collation('utf8mb4_bin');
            $table->timestamp('linked_at');
            $table->timestamp('last_verified_at');
            $table->timestamps();
            $table->unique(['firebase_project_id', 'firebase_uid'], 'external_firebase_owner_unique');
            $table->unique(['firebase_project_id', 'provider', 'provider_uid'], 'external_provider_owner_unique');
            $table->unique(['user_id', 'firebase_project_id', 'provider'], 'external_user_provider_unique');
        });
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('external_identity_id')->nullable()->constrained('external_identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Never leave usable Google-issued sessions after losing their provenance.
        \Illuminate\Support\Facades\DB::table('personal_access_tokens')->whereNotNull('external_identity_id')->delete();
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('external_identity_id');
        });
        Schema::dropIfExists('external_identities');
    }
};
