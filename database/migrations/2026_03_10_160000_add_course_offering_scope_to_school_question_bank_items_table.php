<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_question_bank_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_question_bank_items', 'school_course_offering_id')) {
                $table->foreignId('school_course_offering_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained('school_course_offerings')
                    ->nullOnDelete();
                $table->index(
                    ['school_id', 'school_course_offering_id'],
                    'school_questions_scope_offering_idx'
                );
            }

            if (!Schema::hasColumn('school_question_bank_items', 'chapter_name')) {
                $table->string('chapter_name', 150)
                    ->nullable()
                    ->after('unit_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_question_bank_items', function (Blueprint $table): void {
            if (Schema::hasColumn('school_question_bank_items', 'chapter_name')) {
                $table->dropColumn('chapter_name');
            }

            if (Schema::hasColumn('school_question_bank_items', 'school_course_offering_id')) {
                $table->dropIndex('school_questions_scope_offering_idx');
                $table->dropConstrainedForeignId('school_course_offering_id');
            }
        });
    }
};

