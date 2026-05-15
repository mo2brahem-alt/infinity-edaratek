<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('school_supervisor_assignments')) {
            Schema::create('school_supervisor_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('directorate_id')->nullable()->constrained('educational_directorates')->cascadeOnDelete();
                $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['supervisor_id', 'is_active']);
                $table->index(['directorate_id', 'is_active']);
                $table->index(['school_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_supervisor_assignments');
    }
};
