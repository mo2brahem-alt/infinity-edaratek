<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attachments')) {
            return;
        }

        Schema::table('attachments', function (Blueprint $table): void {
            if (!Schema::hasColumn('attachments', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('ticket_message_id')->constrained('schools')->nullOnDelete();
            }

            if (!Schema::hasColumn('attachments', 'attachable_type')) {
                $table->nullableMorphs('attachable');
            }

            if (!Schema::hasColumn('attachments', 'module')) {
                $table->string('module', 80)->nullable()->after('attachable_id');
            }

            if (!Schema::hasColumn('attachments', 'action_type')) {
                $table->string('action_type', 120)->nullable()->after('module');
            }

            if (!Schema::hasColumn('attachments', 'stored_name')) {
                $table->string('stored_name')->nullable()->after('file_name');
            }

            if (!Schema::hasColumn('attachments', 'disk')) {
                $table->string('disk', 80)->nullable()->after('stored_name');
            }

            if (!Schema::hasColumn('attachments', 'extension')) {
                $table->string('extension', 20)->nullable()->after('mime_type');
            }

            if (!Schema::hasColumn('attachments', 'description')) {
                $table->text('description')->nullable()->after('file_size');
            }

            if (!Schema::hasColumn('attachments', 'metadata')) {
                $table->json('metadata')->nullable()->after('description');
            }

            if (!Schema::hasColumn('attachments', 'is_private')) {
                $table->boolean('is_private')->default(true)->after('metadata');
            }

            if (!Schema::hasColumn('attachments', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attachments')) {
            return;
        }

        Schema::table('attachments', function (Blueprint $table): void {
            if (Schema::hasColumn('attachments', 'school_id')) {
                $table->dropForeign(['school_id']);
            }

            if (Schema::hasColumn('attachments', 'attachable_type') || Schema::hasColumn('attachments', 'attachable_id')) {
                $table->dropMorphs('attachable');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('attachments', 'school_id') ? 'school_id' : null,
                Schema::hasColumn('attachments', 'module') ? 'module' : null,
                Schema::hasColumn('attachments', 'action_type') ? 'action_type' : null,
                Schema::hasColumn('attachments', 'stored_name') ? 'stored_name' : null,
                Schema::hasColumn('attachments', 'disk') ? 'disk' : null,
                Schema::hasColumn('attachments', 'extension') ? 'extension' : null,
                Schema::hasColumn('attachments', 'description') ? 'description' : null,
                Schema::hasColumn('attachments', 'metadata') ? 'metadata' : null,
                Schema::hasColumn('attachments', 'is_private') ? 'is_private' : null,
                Schema::hasColumn('attachments', 'deleted_at') ? 'deleted_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
