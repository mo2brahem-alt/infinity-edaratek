<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_holidays')) {
            return;
        }

        Schema::create('school_holidays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->foreign('school_id', 'sh_school_fk')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('return_date')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'start_date', 'end_date'], 'sh_school_period_idx');
            $table->index(['school_id', 'is_active', 'start_date'], 'sh_school_active_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holidays');
    }
};

