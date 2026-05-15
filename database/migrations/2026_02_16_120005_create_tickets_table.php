<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->longText('description');
                $table->string('priority')->nullable()->index();
                $table->date('due_date')->nullable()->index();
                $table->string('status')->default('OPEN')->index();
                $table->longText('manager_final_report')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->index(['assigned_to', 'status']);
                $table->index(['created_by', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
