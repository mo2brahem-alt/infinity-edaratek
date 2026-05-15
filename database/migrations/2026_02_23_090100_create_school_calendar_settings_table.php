<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_calendar_settings')) {
            return;
        }

        Schema::create('school_calendar_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->foreign('school_id', 'scs_school_fk')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('week_start_day')->default(0);
            $table->json('weekly_off_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('school_id', 'scs_school_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_settings');
    }
};

