<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_supervision_requests')) {
            Schema::create('school_supervision_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('region_id')->nullable()->constrained('educational_directorates')->nullOnDelete();
                $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('SUPERVISOR_REQUESTED')->index();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('manager_action_at')->nullable();
                $table->timestamp('supervisor_confirmed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->index(['supervisor_id', 'status']);
                $table->index(['manager_id', 'status']);
                $table->index(['school_id', 'supervisor_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_supervision_requests');
    }
};
