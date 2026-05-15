<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'approval_status')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('approval_status', 40)->default('approved')->after('is_active')->index();
            });
        }

        if (! Schema::hasColumn('users', 'approved_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            });
        }

        if (! Schema::hasColumn('users', 'approved_by')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'rejected_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('rejected_at')->nullable()->after('approved_by');
            });
        }

        if (! Schema::hasColumn('users', 'rejected_by')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'approval_notes')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('approval_notes')->nullable()->after('rejected_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('users', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('users', 'approval_notes') ? 'approval_notes' : null,
                Schema::hasColumn('users', 'rejected_at') ? 'rejected_at' : null,
                Schema::hasColumn('users', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('users', 'approval_status') ? 'approval_status' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
