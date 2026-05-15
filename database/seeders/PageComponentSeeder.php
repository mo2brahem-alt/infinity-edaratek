<?php

namespace Database\Seeders;

use App\Models\PageComponent;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class PageComponentSeeder extends Seeder
{
    public function run(): void
    {
        $defaultHomeBanner = [
            'type' => 'info_section',
            'title' => 'منصة تعليمية موحدة لإدارة المدارس باحترافية',
            'text' => 'إدارتك تساعدك على تنظيم المدارس والمستخدمين والبنية الأكاديمية والتقارير داخل تجربة عربية واضحة ومناسبة للمدارس والإدارات التعليمية.',
            'btnText' => 'تسجيل الدخول',
            'btnUrl' => '/login',
            'btnBgColor' => '#2563eb',
            'btnTextColor' => '#ffffff',
            'textColor' => '#cbd5e1',
            'titleColor' => '#f8fafc',
            'bgColor' => 'transparent',
            'layout' => 'image_right',
            'design' => [
                'textAlign' => 'text-right',
                'backgroundType' => 'gradient',
                'backgroundGradient' => 'linear-gradient(135deg, #0f172a 0%, #111827 46%, #0f3b4c 100%)',
                'paddingTop' => 88,
                'paddingBottom' => 88,
                'titleSize' => 42,
                'bodySize' => 18,
                'bodyLineHeight' => 1.9,
                'marginTop' => 0,
                'marginBottom' => 24,
            ],
        ];

        $defaultHomeStats = [
            'type' => 'stats',
            'title' => 'مؤشرات سريعة عن المنصة',
            'titleColor' => '#f8fafc',
            'cardBgColor' => 'bg-slate-900/80',
            'numberColor' => '#38bdf8',
            'labelColor' => '#cbd5e1',
            'stats' => [
                ['number' => '12', 'label' => 'مدرسة مفعلة', 'suffix' => '+'],
                ['number' => '2500', 'label' => 'طالب داخل النظام', 'suffix' => '+'],
                ['number' => '180', 'label' => 'مستخدم نشط', 'suffix' => '+'],
                ['number' => '24', 'label' => 'تقرير تشغيلي', 'suffix' => '+'],
            ],
            'design' => [
                'backgroundType' => 'gradient',
                'backgroundGradient' => 'linear-gradient(135deg, #0f172a 0%, #111827 100%)',
                'paddingTop' => 72,
                'paddingBottom' => 72,
                'titleSize' => 38,
                'bodySize' => 17,
                'marginTop' => 0,
                'marginBottom' => 0,
            ],
        ];

        $defaultHomePricing = [
            'type' => 'pricing',
            'title' => 'باقات مناسبة للمدارس والإدارات التعليمية',
            'subtitle' => 'اختر الباقة المناسبة وابدأ تشغيل المدرسة أو الإشراف التعليمي داخل منصة واحدة متكاملة.',
            'titleColor' => '#f8fafc',
            'subtitleColor' => '#cbd5e1',
            'priceColor' => '#ffffff',
            'cardBgColor' => 'bg-slate-950/70',
            'plans_flow' => 'right',
            'plans' => [
                [
                    'name' => 'باقة مدير المدرسة',
                    'price' => '299',
                    'billingLabel' => '/ شهرياً',
                    'role_type' => 'SCHOOL_MANAGER',
                    'url' => '/pricing',
                    'isFeatured' => true,
                    'features' => [
                        ['text' => 'إدارة الهيكل الأكاديمي وهيكل المدرسة', 'included' => true],
                        ['text' => 'إدارة المستخدمين والصلاحيات', 'included' => true],
                        ['text' => 'التقارير والمتابعة التشغيلية', 'included' => true],
                    ],
                ],
                [
                    'name' => 'باقة المشرف التعليمي',
                    'price' => '199',
                    'billingLabel' => '/ شهرياً',
                    'role_type' => 'SUPERVISOR',
                    'url' => '/pricing',
                    'features' => [
                        ['text' => 'إدارة المدارس التابعة والمتابعة', 'included' => true],
                        ['text' => 'الطلبات والتذاكر والإشراف', 'included' => true],
                        ['text' => 'لوحة مؤشرات موحدة', 'included' => true],
                    ],
                ],
            ],
            'design' => [
                'textAlign' => 'text-center',
                'backgroundType' => 'none',
                'paddingTop' => 84,
                'paddingBottom' => 84,
                'titleSize' => 40,
                'subtitleSize' => 18,
                'marginTop' => 0,
                'marginBottom' => 0,
            ],
        ];

        $defaultHomeFaq = [
            'type' => 'faq',
            'title' => 'أسئلة شائعة قبل البدء',
            'titleColor' => '#f8fafc',
            'questionColor' => '#f8fafc',
            'answerColor' => '#cbd5e1',
            'items' => [
                [
                    'question' => 'هل يمكن تشغيل أكثر من مدرسة داخل المنصة؟',
                    'answer' => 'نعم، المنصة مصممة لدعم الإدارات التعليمية والمدارس المتعددة مع عزل كامل للبيانات والصلاحيات.',
                ],
                [
                    'question' => 'هل يمكن تخصيص الصفحة الرئيسية والمحتوى العام؟',
                    'answer' => 'نعم، يمكن تعديل المكونات والصفحات العامة من لوحة السوبر أدمن بما يتوافق مع هوية الجهة.',
                ],
                [
                    'question' => 'هل تدعم المنصة العربية وواجهة RTL؟',
                    'answer' => 'نعم، المشروع مبني أساسًا ليدعم العربية وRTL بشكل كامل داخل الصفحات العامة والداخلية.',
                ],
            ],
            'design' => [
                'textAlign' => 'text-center',
                'backgroundType' => 'none',
                'paddingTop' => 84,
                'paddingBottom' => 84,
                'titleSize' => 38,
                'subtitleSize' => 18,
                'bodySize' => 17,
                'marginTop' => 0,
                'marginBottom' => 0,
            ],
        ];

        PageComponent::query()->updateOrCreate(
            ['shortcode' => '[home-banner]'],
            [
                'name' => 'بانر الصفحة الرئيسية',
                'content' => json_encode($defaultHomeBanner, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => true,
            ]
        );

        PageComponent::query()->updateOrCreate(
            ['shortcode' => '[home-stats]'],
            [
                'name' => 'مؤشرات الصفحة الرئيسية',
                'content' => json_encode($defaultHomeStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => true,
            ]
        );

        PageComponent::query()->updateOrCreate(
            ['shortcode' => '[home-pricing]'],
            [
                'name' => 'باقات الصفحة الرئيسية',
                'content' => json_encode($defaultHomePricing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => true,
            ]
        );

        PageComponent::query()->updateOrCreate(
            ['shortcode' => '[home-faq]'],
            [
                'name' => 'الأسئلة الشائعة للصفحة الرئيسية',
                'content' => json_encode($defaultHomeFaq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => true,
            ]
        );

        $homePageContent = Setting::query()->where('key', 'home_page_content')->value('value');
        $normalizedContent = is_string($homePageContent) ? trim($homePageContent) : '';

        if ($normalizedContent === '' || $normalizedContent === '[home-banner]') {
            Setting::query()->updateOrCreate(
                ['key' => 'home_page_content'],
                [
                    'value' => implode("\n\n", [
                        '[home-banner]',
                        '[home-stats]',
                        '[home-pricing]',
                        '[home-faq]',
                    ]),
                    'type' => 'textarea',
                    'group' => 'general',
                ]
            );
        }
    }
}
