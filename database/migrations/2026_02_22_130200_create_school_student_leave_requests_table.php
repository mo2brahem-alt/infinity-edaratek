<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_student_leave_requests')) {
            return;
        }

        Schema::create('school_student_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('school_leave_type_id')->constrained('school_leave_types')->cascadeOnDelete();
            $table->string('source', 20)->default('PRE_APPROVED');
            $table->string('status', 20)->default('PENDING');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 1000)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['school_id', 'school_student_id', 'start_date', 'end_date'],
                'school_student_leave_period_index'
            );
            $table->index(
                ['school_id', 'status', 'start_date', 'end_date'],
                'school_student_leave_status_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_student_leave_requests');
    }
};

