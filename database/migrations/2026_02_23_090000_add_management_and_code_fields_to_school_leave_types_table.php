<?php

use App\Models\SchoolLeaveType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE_UNIQUE = 'school_leave_type_code_unique';
    private const CATEGORY_ACTIVE_INDEX = 'school_leave_type_category_active_index';

    public function up(): void
    {
        if (!Schema::hasTable('school_leave_types')) {
            return;
        }

        Schema::table('school_leave_types', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_leave_types', 'code')) {
                $table->string('code', 60)->nullable()->after('school_id');
            }

            if (!Schema::hasColumn('school_leave_types', 'category')) {
                $table->string('category', 20)->default(SchoolLeaveType::CATEGORY_STUDENT)->after('name');
            }
        });

        try {
            Schema::table('school_leave_types', function (Blueprint $table): void {
                $table->unique(['school_id', 'code'], self::CODE_UNIQUE);
            });
        } catch (\Throwable $exception) {
            if (!$this->isSafeDuplicateDefinition($exception)) {
                throw $exception;
            }
        }

        try {
            Schema::table('school_leave_types', function (Blueprint $table): void {
                $table->index(['school_id', 'category', 'is_active'], self::CATEGORY_ACTIVE_INDEX);
            });
        } catch (\Throwable $exception) {
            if (!$this->isSafeDuplicateDefinition($exception)) {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('school_leave_types')) {
            return;
        }

        try {
            Schema::table('school_leave_types', function (Blueprint $table): void {
                $table->dropUnique(self::CODE_UNIQUE);
            });
        } catch (\Throwable) {
            // Ignore if unique key does not exist.
        }

        try {
            Schema::table('school_leave_types', function (Blueprint $table): void {
                $table->dropIndex(self::CATEGORY_ACTIVE_INDEX);
            });
        } catch (\Throwable) {
            // Ignore if index does not exist.
        }

        Schema::table('school_leave_types', function (Blueprint $table): void {
            if (Schema::hasColumn('school_leave_types', 'code')) {
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('school_leave_types', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    private function isSafeDuplicateDefinition(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'already exists');
    }
};

