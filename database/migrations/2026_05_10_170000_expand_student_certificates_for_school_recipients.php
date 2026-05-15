<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_certificates')) {
            return;
        }

        if (!$this->hasRecipientColumns()) {
            Schema::table('student_certificates', function (Blueprint $table): void {
                $table->string('recipient_type', 40)->default('student')->after('school_student_id');
                $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');
                $table->string('recipient_name')->nullable()->after('recipient_id');
                $table->string('recipient_label')->nullable()->after('recipient_name');
                $table->json('recipient_context_json')->nullable()->after('recipient_label');
                $table->index(['school_id', 'recipient_type', 'recipient_id'], 'student_certificates_recipient_idx');
            });
        }

        $this->makeStudentReferenceNullable();
        $this->backfillStudentRecipients();
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_certificates')) {
            return;
        }

        Schema::table('student_certificates', function (Blueprint $table): void {
            try {
                $table->dropIndex('student_certificates_recipient_idx');
            } catch (\Throwable) {
                //
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('student_certificates', 'recipient_context_json') ? 'recipient_context_json' : null,
                Schema::hasColumn('student_certificates', 'recipient_label') ? 'recipient_label' : null,
                Schema::hasColumn('student_certificates', 'recipient_name') ? 'recipient_name' : null,
                Schema::hasColumn('student_certificates', 'recipient_id') ? 'recipient_id' : null,
                Schema::hasColumn('student_certificates', 'recipient_type') ? 'recipient_type' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasRecipientColumns(): bool
    {
        return Schema::hasColumn('student_certificates', 'recipient_type')
            && Schema::hasColumn('student_certificates', 'recipient_id')
            && Schema::hasColumn('student_certificates', 'recipient_name')
            && Schema::hasColumn('student_certificates', 'recipient_label')
            && Schema::hasColumn('student_certificates', 'recipient_context_json');
    }

    private function makeStudentReferenceNullable(): void
    {
        if (!Schema::hasColumn('student_certificates', 'school_student_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('student_certificates', function (Blueprint $table): void {
            try {
                $table->dropForeign(['school_student_id']);
            } catch (\Throwable) {
                //
            }
        });

        Schema::table('student_certificates', function (Blueprint $table): void {
            $table->foreignId('school_student_id')->nullable()->change();
        });

        Schema::table('student_certificates', function (Blueprint $table): void {
            $table->foreign('school_student_id')
                ->references('id')
                ->on('school_students')
                ->nullOnDelete();
        });
    }

    private function backfillStudentRecipients(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::table('student_certificates')
                ->whereNull('recipient_id')
                ->whereNotNull('school_student_id')
                ->update([
                    'recipient_type' => 'student',
                    'recipient_label' => 'طالب',
                ]);

            return;
        }

        DB::statement("
            UPDATE student_certificates sc
            INNER JOIN school_students ss ON ss.id = sc.school_student_id
            SET
                sc.recipient_type = 'student',
                sc.recipient_id = sc.school_student_id,
                sc.recipient_name = ss.full_name,
                sc.recipient_label = 'طالب'
            WHERE sc.school_student_id IS NOT NULL
              AND (sc.recipient_id IS NULL OR sc.recipient_name IS NULL)
        ");
    }
};
