<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up(): void
{
    Schema::create('educational_directorates', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // اسم الإدارة (مثلاً: إدارة شمال الرياض)
        $table->string('governorate'); // المحافظة (مثلاً: الرياض)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('educational_directorates');
    }
};
