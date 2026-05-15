<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('association_requests')) {
            Schema::create('association_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title')->default('School supervision association request');
                $table->string('status')->default('PENDING')->index();
                $table->text('notes')->nullable();
                $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'status']);
                $table->index(['manager_user_id', 'status']);
                $table->index(['supervisor_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('association_requests');
    }
};
