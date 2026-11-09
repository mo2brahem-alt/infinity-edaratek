<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('private_file_migrations')) { return; }
        Schema::create('private_file_migrations', function (Blueprint $table): void {
            $table->id(); $table->unsignedInteger('schema_version')->default(1);
            $table->char('inventory_key', 64)->unique();
            $table->string('attachment_type')->nullable(); $table->unsignedBigInteger('attachment_id')->nullable();
            $table->string('source_disk', 64); $table->text('source_path');
            $table->string('destination_disk', 64); $table->text('destination_path');
            $table->unsignedBigInteger('source_size')->nullable(); $table->char('source_hash', 64)->nullable();
            $table->json('observed_source_metadata')->nullable();
            $table->string('state', 32)->default('inventoried');
            $table->string('safe_failure_code', 64)->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        // Retain the manifest and private bytes on rollback. Never republish files.
    }
};
