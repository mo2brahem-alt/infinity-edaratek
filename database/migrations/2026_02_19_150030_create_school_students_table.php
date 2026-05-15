<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('student_code')->nullable();
            $table->string('national_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'student_code']);
            $table->unique(['school_id', 'national_id']);
            $table->index(['school_id', 'school_classroom_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_students');
    }
};
