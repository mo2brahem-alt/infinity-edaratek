<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_stage_school', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('education_stage_id')->constrained('education_stages')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['education_stage_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_stage_school');
    }
};
