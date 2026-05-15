<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subtasks')) {
            Schema::create('subtasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable()->index();
                $table->string('status')->default('OPEN')->index();
                $table->timestamps();

                $table->index(['ticket_id', 'status']);
                $table->index(['assigned_to', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subtasks');
    }
};
