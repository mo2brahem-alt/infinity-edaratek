<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;
class SettingsSeeder extends Seeder
{
   public function run(): void
{
    $settings = [
        // المظهر
        ['key' => 'site_name', 'value' => 'إدارتك', 'type' => 'text', 'group' => 'general'],
        ['key' => 'primary_color', 'value' => '#2563eb', 'type' => 'color', 'group' => 'appearance'], // أزرق
        ['key' => 'secondary_color', 'value' => '#1e1b4b', 'type' => 'color', 'group' => 'appearance'], // كحلي غامق
        
        // الميديا
        ['key' => 'site_logo', 'value' => null, 'type' => 'image', 'group' => 'media'],
        ['key' => 'hero_video', 'value' => null, 'type' => 'video', 'group' => 'media'],
        ['key' => 'banner_image', 'value' => null, 'type' => 'image', 'group' => 'media'],
        ['key' => 'footer_text', 'value' => 'جميع الحقوق محفوظة © 2026', 'type' => 'text', 'group' => 'general'],
    ];

    foreach ($settings as $setting) {
        Setting::updateOrCreate(['key' => $setting['key']], $setting);
    }
}

}