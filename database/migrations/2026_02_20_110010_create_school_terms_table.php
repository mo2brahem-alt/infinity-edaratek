<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_terms')) {
            return;
        }

        Schema::create('school_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'start_date', 'end_date'], 'school_terms_period_index');
            $table->index(['school_id', 'is_active'], 'school_terms_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_terms');
    }
};
