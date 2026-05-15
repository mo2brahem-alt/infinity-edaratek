<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_student_attendances')) {
            return;
        }

        if (!Schema::hasColumn('school_student_attendances', 'school_student_leave_request_id')) {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->unsignedBigInteger('school_student_leave_request_id')->nullable();
            });
        }

        try {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->foreign('school_student_leave_request_id', 'ssa_leave_req_fk')
                    ->references('id')
                    ->on('school_student_leave_requests')
                    ->nullOnDelete();
            });
        } catch (\Throwable $exception) {
            if (!$this->isSafeDuplicateDefinition($exception)) {
                throw $exception;
            }
        }

        try {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->index(
                    ['school_id', 'school_student_id', 'school_student_leave_request_id'],
                    'school_attendance_leave_lookup_idx'
                );
            });
        } catch (\Throwable $exception) {
            if (!$this->isSafeDuplicateDefinition($exception)) {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_student_attendances')) {
            return;
        }

        try {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->dropForeign('ssa_leave_req_fk');
            });
        } catch (\Throwable) {
            // Ignore when foreign key does not exist.
        }

        try {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->dropIndex('school_attendance_leave_lookup_idx');
            });
        } catch (\Throwable) {
            // Ignore when index does not exist.
        }

        if (Schema::hasColumn('school_student_attendances', 'school_student_leave_request_id')) {
            Schema::table('school_student_attendances', function (Blueprint $table): void {
                $table->dropColumn('school_student_leave_request_id');
            });
        }
    }

    private function isSafeDuplicateDefinition(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'already exists');
    }
};
