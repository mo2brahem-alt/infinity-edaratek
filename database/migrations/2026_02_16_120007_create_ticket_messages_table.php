<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_messages')) {
            Schema::create('ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('subtask_id')->nullable()->constrained('subtasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->longText('message');
                $table->string('message_type')->default('reply')->index();
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
                $table->index(['subtask_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
