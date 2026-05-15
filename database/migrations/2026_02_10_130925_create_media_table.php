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
    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('file_name'); // اسم الملف الأصلي
        $table->string('file_path'); // المسار في التخزين
        $table->string('file_type'); // image, video
        $table->string('mime_type'); // image/jpeg, video/mp4
        $table->unsignedBigInteger('file_size'); // بالحجم بايت
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
