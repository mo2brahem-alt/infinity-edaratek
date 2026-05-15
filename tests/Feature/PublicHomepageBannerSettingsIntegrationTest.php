<?php

namespace Tests\Feature;

use App\Models\PageComponent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicHomepageBannerSettingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_receives_updated_banner_layout_settings_from_super_admin_component_editor(): void
    {
        $admin = $this->createSuperAdmin();

        Setting::query()->updateOrCreate(
            ['key' => 'home_page_content'],
            [
                'value' => '[main-banner]',
                'type' => 'text',
                'group' => 'general',
            ]
        );

        $component = PageComponent::query()->create([
            'name' => 'Main Banner',
            'shortcode' => '[main-banner]',
            'content' => json_encode([
                'type' => 'banner',
                'title' => 'Old title',
                'subtitle' => 'Old subtitle',
                'mediaType' => 'image',
                'media' => 'uploads/old-banner.jpg',
                'height' => 'min-h-[500px]',
                'glassHeight' => 280,
                'glassMarginTop' => 0,
                'glassMarginBottom' => 0,
                'glassMarginRight' => 0,
                'glassMarginLeft' => 0,
                'design' => [
                    'paddingTop' => 0,
                    'paddingBottom' => 0,
                    'textAlign' => 'text-right',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.components.update', $component), [
            'name' => 'Main Banner',
            'shortcode' => '[main-banner]',
            'content' => json_encode([
                'type' => 'banner',
                'title' => 'رؤية واحدة، أداء متكامل',
                'subtitle' => 'جسر التواصل بين الرقابة والتنفيذ',
                'mediaType' => 'video',
                'media' => 'uploads/banner-video.mp4',
                'height' => 'min-h-screen',
                'alignment' => 'text-center',
                'glassBgColor' => '#ffffff',
                'glassOpacity' => 0,
                'glassBlur' => 1,
                'glassHeight' => 0,
                'glassMarginTop' => 24,
                'glassMarginBottom' => 16,
                'glassMarginRight' => 300,
                'glassMarginLeft' => 12,
                'design' => [
                    'paddingTop' => 18,
                    'paddingBottom' => 26,
                    'textAlign' => 'text-center',
                    'titleSize' => 44,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ])->assertRedirect();

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');
        $payload = json_decode((string) data_get($page, 'props.components.0.content', '{}'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('video', data_get($payload, 'mediaType'));
        $this->assertSame('min-h-screen', data_get($payload, 'height'));
        $this->assertSame(0, data_get($payload, 'glassHeight'));
        $this->assertSame(24, data_get($payload, 'glassMarginTop'));
        $this->assertSame(300, data_get($payload, 'glassMarginRight'));
        $this->assertSame(18, data_get($payload, 'design.paddingTop'));
        $this->assertSame(26, data_get($payload, 'design.paddingBottom'));
    }

    private function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }
}
