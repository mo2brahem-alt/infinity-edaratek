<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ATTENDANCE_INDEX = 'ss_att_student_status_date_idx';
    private const LEAVE_INDEX = 'sslr_student_status_period_idx';

    public function up(): void
    {
        if (Schema::hasTable('school_student_attendances')) {
            try {
                Schema::table('school_student_attendances', function (Blueprint $table): void {
                    $table->index(
                        ['school_id', 'school_student_id', 'status', 'attendance_date'],
                        self::ATTENDANCE_INDEX
                    );
                });
            } catch (\Throwable $exception) {
                if (!$this->isSafeDuplicateDefinition($exception)) {
                    throw $exception;
                }
            }
        }

        if (Schema::hasTable('school_student_leave_requests')) {
            try {
                Schema::table('school_student_leave_requests', function (Blueprint $table): void {
                    $table->index(
                        ['school_id', 'school_student_id', 'status', 'start_date', 'end_date'],
                        self::LEAVE_INDEX
                    );
                });
            } catch (\Throwable $exception) {
                if (!$this->isSafeDuplicateDefinition($exception)) {
                    throw $exception;
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_student_attendances')) {
            try {
                Schema::table('school_student_attendances', function (Blueprint $table): void {
                    $table->dropIndex(self::ATTENDANCE_INDEX);
                });
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }
        }

        if (Schema::hasTable('school_student_leave_requests')) {
            try {
                Schema::table('school_student_leave_requests', function (Blueprint $table): void {
                    $table->dropIndex(self::LEAVE_INDEX);
                });
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }
        }
    }

    private function isSafeDuplicateDefinition(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'already exists');
    }
};

