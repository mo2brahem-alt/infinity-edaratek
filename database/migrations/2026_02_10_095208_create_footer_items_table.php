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
    Schema::create('footer_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('footer_column_id')->constrained()->cascadeOnDelete();
        $table->string('label'); // نص الرابط (مثلاً: "اتصل بنا")
        $table->string('url')->nullable(); // الرابط
        $table->integer('order')->default(0); // ترتيب الرابط داخل العمود
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('footer_items');
    }
};
