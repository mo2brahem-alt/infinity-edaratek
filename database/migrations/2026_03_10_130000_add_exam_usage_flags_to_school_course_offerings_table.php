<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_course_offerings', function (Blueprint $table): void {
            if (!Schema::hasColumn('school_course_offerings', 'usable_in_exams')) {
                $table->boolean('usable_in_exams')->default(true)->after('is_active');
            }

            if (!Schema::hasColumn('school_course_offerings', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('usable_in_exams');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_course_offerings', function (Blueprint $table): void {
            if (Schema::hasColumn('school_course_offerings', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('school_course_offerings', 'usable_in_exams')) {
                $table->dropColumn('usable_in_exams');
            }
        });
    }
};

