<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->timestamp('dispatch_claimed_at')->nullable()->after('attempts');
            $table->string('dispatch_claim_token', 80)->nullable()->unique()->after('dispatch_claimed_at');
            $table->timestamp('dispatch_uncertain_at')->nullable()->after('dispatch_claim_token');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropUnique(['dispatch_claim_token']);
            $table->dropColumn([
                'dispatch_claimed_at',
                'dispatch_claim_token',
                'dispatch_uncertain_at',
            ]);
        });
    }
};
