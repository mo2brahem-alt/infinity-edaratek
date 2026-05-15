<?php

namespace Tests\Feature;

use App\Models\FooterColumn;
use App\Models\FooterItem;
use App\Models\PageComponent;
use App\Models\Setting;
use App\Models\User;
use App\Support\SharedUiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicHomepageSettingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('features.shared_ui.cache_enabled', true);
        config()->set('features.shared_ui.cache_ttl_seconds', 300);
    }

    public function test_public_homepage_reads_updated_super_admin_settings_after_admin_save(): void
    {
        $admin = $this->createSuperAdmin();

        Setting::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['value' => 'Old Name', 'type' => 'text', 'group' => 'general']
        );

        SharedUiCache::appSettings();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_name' => 'Edaratek Public',
            'header_contact_text' => 'ابدأ الآن',
            'header_contact_url' => 'https://example.com/contact',
            'header_linkedin' => 'https://linkedin.com/company/edaratek',
            'footer_desc' => 'وصف عام جديد للمنصة',
            'home_page_content' => '[hero-banner]',
        ])->assertRedirect();

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertSame('Edaratek Public', data_get($page, 'props.app_settings.site_name'));
        $this->assertSame('ابدأ الآن', data_get($page, 'props.app_settings.header_contact_text'));
        $this->assertSame('https://example.com/contact', data_get($page, 'props.app_settings.header_contact_url'));
        $this->assertSame('https://linkedin.com/company/edaratek', data_get($page, 'props.app_settings.header_linkedin'));
        $this->assertSame('وصف عام جديد للمنصة', data_get($page, 'props.app_settings.footer_desc'));
    }

    public function test_public_homepage_receives_header_logo_visibility_position_and_spacing_settings_after_admin_save(): void
    {
        $admin = $this->createSuperAdmin();

        SharedUiCache::appSettings();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_name' => 'Edaratek Public',
            'site_logo' => 'settings/site-logo.png',
            'header_show_logo' => '1',
            'header_brand_position' => 'center',
            'header_logo_width' => 96,
            'header_logo_height' => 52,
            'header_logo_padding_inline' => 10,
            'header_logo_padding_block' => 6,
            'header_logo_margin_inline' => 18,
            'header_logo_margin_block' => 8,
        ])->assertRedirect();

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertSame('settings/site-logo.png', data_get($page, 'props.app_settings.site_logo'));
        $this->assertSame('1', data_get($page, 'props.app_settings.header_show_logo'));
        $this->assertSame('center', data_get($page, 'props.app_settings.header_brand_position'));
        $this->assertSame('96', data_get($page, 'props.app_settings.header_logo_width'));
        $this->assertSame('52', data_get($page, 'props.app_settings.header_logo_height'));
        $this->assertSame('10', data_get($page, 'props.app_settings.header_logo_padding_inline'));
        $this->assertSame('6', data_get($page, 'props.app_settings.header_logo_padding_block'));
        $this->assertSame('18', data_get($page, 'props.app_settings.header_logo_margin_inline'));
        $this->assertSame('8', data_get($page, 'props.app_settings.header_logo_margin_block'));
    }

    public function test_public_homepage_receives_site_icon_and_background_effect_setting(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_icon' => 'settings/site-icon.png',
            'home_background_effects_enabled' => '1',
            'home_background_effect_intensity' => 'strong',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $response = $this->get(route('welcome'));
        $response->assertOk();
        $response->assertSee('media-files/settings/site-icon.png', false);

        $page = $response->viewData('page');

        $this->assertSame('settings/site-icon.png', data_get($page, 'props.app_settings.site_icon'));
        $this->assertSame('1', data_get($page, 'props.app_settings.home_background_effects_enabled'));
        $this->assertSame('strong', data_get($page, 'props.app_settings.home_background_effect_intensity'));
    }

    public function test_public_homepage_ignores_inactive_components_and_strips_their_shortcodes(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'home_page_content'],
            [
                'value' => '[active-section][inactive-section]<p>محتوى عام</p>',
                'type' => 'text',
                'group' => 'general',
            ]
        );

        PageComponent::query()->create([
            'name' => 'Active Section',
            'shortcode' => '[active-section]',
            'content' => json_encode([
                'type' => 'section_title',
                'title' => 'القسم النشط',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
        ]);

        PageComponent::query()->create([
            'name' => 'Inactive Section',
            'shortcode' => '[inactive-section]',
            'content' => json_encode([
                'type' => 'section_title',
                'title' => 'يجب ألا يظهر',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => false,
        ]);

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');

        $this->assertCount(1, data_get($page, 'props.components', []));
        $this->assertStringNotContainsString('[inactive-section]', (string) data_get($page, 'props.homeContent', ''));
    }

    public function test_public_homepage_normalizes_legacy_component_background_settings_for_render(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'home_page_content'],
            [
                'value' => '[legacy-section]',
                'type' => 'text',
                'group' => 'general',
            ]
        );

        PageComponent::query()->create([
            'name' => 'Legacy Section',
            'shortcode' => '[legacy-section]',
            'content' => json_encode([
                'type' => 'section_title',
                'title' => 'عنوان',
                'bgType' => 'image',
                'bgImage' => 'uploads/legacy-hero.jpg',
                'bgImageOpacity' => 62,
                'titleSize' => 52,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
        ]);

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');
        $payload = json_decode((string) data_get($page, 'props.components.0.content', '{}'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('image', data_get($payload, 'design.backgroundType'));
        $this->assertSame('uploads/legacy-hero.jpg', data_get($payload, 'design.backgroundImage'));
        $this->assertSame(62, data_get($payload, 'design.backgroundOpacity'));
        $this->assertSame(52, data_get($payload, 'design.titleSize'));
    }

    public function test_public_homepage_shared_footer_payload_ignores_inactive_columns_and_items(): void
    {
        $activeColumn = FooterColumn::query()->create([
            'title' => 'روابط مهمة',
            'order' => 1,
            'is_active' => true,
        ]);

        FooterItem::query()->create([
            'footer_column_id' => $activeColumn->id,
            'label' => 'المدونة',
            'url' => '/blog',
            'order' => 1,
            'is_active' => true,
        ]);

        FooterItem::query()->create([
            'footer_column_id' => $activeColumn->id,
            'label' => 'رابط معطل',
            'url' => '/disabled-link',
            'order' => 2,
            'is_active' => false,
        ]);

        FooterColumn::query()->create([
            'title' => 'قسم معطل',
            'order' => 2,
            'is_active' => false,
        ]);

        SharedUiCache::clear();

        $response = $this->get(route('welcome'));
        $response->assertOk();

        $page = $response->viewData('page');
        $footerColumns = data_get($page, 'props.footerColumns', []);

        $this->assertCount(1, $footerColumns);
        $this->assertSame('روابط مهمة', data_get($footerColumns, '0.title'));
        $this->assertCount(1, data_get($footerColumns, '0.items', []));
        $this->assertSame('المدونة', data_get($footerColumns, '0.items.0.label'));
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
