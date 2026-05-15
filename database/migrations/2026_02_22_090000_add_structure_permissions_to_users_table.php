<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'can_manage_student_structure')) {
                $table->boolean('can_manage_student_structure')
                    ->nullable()
                    ->after('school_staff_type');
            }

            if (!Schema::hasColumn('users', 'can_manage_student_attendance')) {
                $table->boolean('can_manage_student_attendance')
                    ->nullable()
                    ->after('can_manage_student_structure');
            }

            if (!Schema::hasColumn('users', 'can_manage_academic_planning')) {
                $table->boolean('can_manage_academic_planning')
                    ->nullable()
                    ->after('can_manage_student_attendance');
            }
        });
    }

    public function down(): void
    {
        $dropColumns = [];
        foreach ([
            'can_manage_student_structure',
            'can_manage_student_attendance',
            'can_manage_academic_planning',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $dropColumns[] = $column;
            }
        }

        if (count($dropColumns) > 0) {
            Schema::table('users', function (Blueprint $table) use ($dropColumns): void {
                $table->dropColumn($dropColumns);
            });
        }
    }
};

