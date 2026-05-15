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
    Schema::create('pages', function (Blueprint $table) {
        $table->id();
        $table->string('title');          // عنوان الصفحة
        $table->string('slug')->unique(); // الرابط (مثال: privacy-policy)
        $table->longText('content')->nullable(); // المحتوى
        $table->boolean('is_active')->default(true); // حالة الصفحة
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
