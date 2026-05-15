<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('status_history')) {
            Schema::create('status_history', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type')->index();
                $table->unsignedBigInteger('entity_id')->index();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_history');
    }
};
