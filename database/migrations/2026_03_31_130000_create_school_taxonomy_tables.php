<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();

            $table->unique('name', 'countries_name_unique');
        });

        Schema::create('education_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();

            $table->unique('name', 'education_types_name_unique');
        });

        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('country_id', 'governorates_country_fk')
                ->references('id')
                ->on('countries')
                ->cascadeOnDelete();

            $table->unique(['country_id', 'name'], 'governorates_country_name_unique');
            $table->index(['country_id', 'name'], 'governorates_country_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governorates');
        Schema::dropIfExists('education_types');
        Schema::dropIfExists('countries');
    }
};
