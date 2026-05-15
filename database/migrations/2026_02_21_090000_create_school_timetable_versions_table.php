<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_timetable_versions')) {
            return;
        }

        Schema::create('school_timetable_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_term_id')->constrained('school_terms')->cascadeOnDelete();
            $table->string('name', 255);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'school_term_id', 'name'],
                'school_timetable_versions_unique_name_per_term'
            );
            $table->index(
                ['school_id', 'school_term_id', 'is_published'],
                'school_timetable_versions_filter_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_timetable_versions');
    }
};
