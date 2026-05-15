<?php

namespace Database\Seeders;

use App\Models\PageComponent;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class HomePageComponentSeeder extends Seeder
{
    public function run(): void
    {
        $content = json_encode([
            'type' => 'banner',
            'title' => 'منصة إدارتك لإدارة المدارس والعمليات التعليمية',
            'subtitle' => 'نظّم الإشراف والمتابعة والحضور والخطط الأكاديمية من لوحة واحدة واضحة وسريعة.',
            'mediaType' => 'video',
            'media' => '/videos/intro.mp4',
            'btnText' => 'تسجيل الدخول',
            'btnUrl' => '/login',
            'height' => 'min-h-[70vh]',
            'overlay' => 'bg-slate-950/60',
            'alignment' => 'text-right',
            'titleColor' => '#ffffff',
            'subtitleColor' => '#e5e7eb',
            'btnBgColor' => '#2563eb',
            'btnTextColor' => '#ffffff',
            'glassBgColor' => '#0f172a',
            'glassOpacity' => 45,
            'glassHeight' => 320,
            'glassMarginTop' => 0,
            'glassMarginBottom' => 0,
            'glassMarginRight' => 0,
            'glassMarginLeft' => 0,
            'design' => [
                'marginTop' => 0,
                'marginBottom' => 0,
                'paddingTop' => 120,
                'paddingBottom' => 120,
                'backgroundType' => 'gradient',
                'backgroundColor' => '#0f172a',
                'backgroundGradient' => 'linear-gradient(135deg, #0f172a 0%, #111827 45%, #1d4ed8 100%)',
                'backgroundImage' => '',
                'backgroundOpacity' => 100,
                'textAlign' => 'text-right',
                'titleSize' => 48,
                'subtitleSize' => 20,
                'bodySize' => 18,
                'titleWeight' => 800,
                'bodyWeight' => 400,
                'titleLineHeight' => 1.25,
                'bodyLineHeight' => 1.8,
                'imageWidth' => 100,
                'imageHeight' => 420,
                'imageFit' => 'cover',
                'imagePosition' => 'center center',
                'imageRadius' => 24,
                'imageDirection' => 'normal',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        PageComponent::query()->updateOrCreate(
            ['shortcode' => '[home-banner]'],
            [
                'name' => 'البنر الرئيسي',
                'content' => $content,
                'is_active' => true,
            ]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'home_page_content'],
            [
                'value' => '[home-banner]',
                'type' => 'text',
                'group' => 'general',
            ]
        );
    }
}
