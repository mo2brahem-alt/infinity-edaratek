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
    Schema::create('header_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('header_menu_id')->constrained()->cascadeOnDelete();
        $table->string('label'); // اسم الرابط الفرعي
        $table->string('url'); // الرابط الفرعي
        $table->integer('order')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('header_items');
    }
};
