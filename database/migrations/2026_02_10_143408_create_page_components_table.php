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
    Schema::create('page_components', function (Blueprint $table) {
        $table->id();
        $table->string('name');           // اسم المكون (للتوضيح للأدمن)
        $table->string('shortcode')->unique(); // الكود القصير (مثال: [ad-banner])
        $table->longText('content');      // محتوى المكون (HTML)
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_components');
    }
};
