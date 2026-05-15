<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // سنقوم بإضافة الإعدادات كصفوف في جدول settings إذا كان يعتمد نظام Key-Value
        // أو كأعمدة إذا كان يعتمد نظام الأعمدة. بناءً على الكود السابق، يبدو أنه Key-Value
        
        $settings = [
            ['key' => 'btn_bg_color', 'value' => '#2563eb'],
            ['key' => 'btn_text_color', 'value' => '#ffffff'],
            ['key' => 'btn_style', 'value' => 'solid'], // solid, glass, gradient, outline
            ['key' => 'btn_shape', 'value' => 'rounded-lg'], // rounded-none, rounded-lg, rounded-full
            ['key' => 'btn_animation', 'value' => 'hover-scale'], // none, hover-scale, hover-glow, pulse
        ];

        foreach ($settings as $setting) {
            // التحقق من وجود الإعداد قبل إضافته لتجنب التكرار
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('settings')->insert([
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        // اختياري: حذف الإعدادات عند التراجع
    }
};
