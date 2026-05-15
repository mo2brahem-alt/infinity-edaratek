<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_student_attendances')) {
            return;
        }

        Schema::create('school_student_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('school_classroom_id')->constrained('school_classrooms')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('PRESENT');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('permission_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'school_student_id', 'attendance_date'], 'school_student_attendance_unique');
            $table->index(['school_id', 'school_classroom_id', 'attendance_date'], 'school_classroom_attendance_index');
            $table->index(['school_id', 'status', 'attendance_date'], 'school_status_attendance_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_student_attendances');
    }
};
