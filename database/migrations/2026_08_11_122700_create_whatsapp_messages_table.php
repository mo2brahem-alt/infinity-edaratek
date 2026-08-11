<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key', 120);
            $table->string('recipient_phone', 32);
            $table->string('template_name')->nullable();
            $table->string('language', 12)->nullable();
            $table->json('parameters')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('idempotency_key')->unique();
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'event_key']);
            $table->index(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
