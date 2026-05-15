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
    Schema::create('footer_columns', function (Blueprint $table) {
        $table->id();
        $table->string('title'); // عنوان العمود (مثلاً: "خدماتنا")
        $table->integer('order')->default(0); // ترتيب العمود
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('footer_columns');
    }
};
