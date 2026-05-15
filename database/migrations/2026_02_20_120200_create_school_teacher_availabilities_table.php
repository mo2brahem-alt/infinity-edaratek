<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_teacher_availabilities')) {
            return;
        }

        Schema::create('school_teacher_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedSmallInteger('session_index');
            $table->boolean('is_available')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'teacher_user_id', 'day_of_week', 'session_index'],
                'teacher_availability_unique_slot'
            );
            $table->index(['school_id', 'teacher_user_id'], 'teacher_availability_school_teacher_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_teacher_availabilities');
    }
};
