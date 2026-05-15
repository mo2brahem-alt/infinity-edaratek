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
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique(); // مفتاح الإعداد (مثلاً site_logo)
        $table->text('value')->nullable(); // القيمة (الرابط أو النص)
        $table->string('type')->default('text'); // نوع الحقل (text, image, color, video)
        $table->string('group')->default('general'); // للتنظيم (general, appearance, media)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
