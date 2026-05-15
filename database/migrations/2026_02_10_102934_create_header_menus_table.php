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
    Schema::create('header_menus', function (Blueprint $table) {
        $table->id();
        $table->string('title'); // عنوان القائمة (مثلاً: "خدماتنا")
        $table->string('url')->nullable(); // رابط مباشر (إذا لم تكن قائمة منسدلة)
        $table->integer('order')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('header_menus');
    }
};
