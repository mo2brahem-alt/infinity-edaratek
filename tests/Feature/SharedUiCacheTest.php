<?php

namespace Tests\Feature;

use App\Models\FooterColumn;
use App\Models\HeaderMenu;
use App\Models\Setting;
use App\Models\User;
use App\Support\SharedUiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedUiCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('features.shared_ui.cache_enabled', true);
        config()->set('features.shared_ui.cache_ttl_seconds', 300);
    }

    public function test_shared_ui_cache_returns_cached_settings_until_cleared(): void
    {
        Setting::query()->create([
            'key' => 'site_name',
            'value' => 'Old Name',
            'type' => 'text',
            'group' => 'general',
        ]);

        $first = SharedUiCache::appSettings();
        $this->assertSame('Old Name', $first['site_name']);

        Setting::query()->where('key', 'site_name')->update(['value' => 'New Name']);

        $cached = SharedUiCache::appSettings();
        $this->assertSame('Old Name', $cached['site_name']);

        SharedUiCache::clear();
        $fresh = SharedUiCache::appSettings();
        $this->assertSame('New Name', $fresh['site_name']);
    }

    public function test_settings_update_clears_shared_ui_cache(): void
    {
        $admin = $this->createSuperAdmin();

        Setting::query()->create([
            'key' => 'site_name',
            'value' => 'Before Update',
            'type' => 'text',
            'group' => 'general',
        ]);

        $this->assertSame('Before Update', SharedUiCache::appSettings()['site_name']);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_name' => 'After Update',
        ]);

        $response->assertRedirect();

        $fresh = SharedUiCache::appSettings();
        $this->assertSame('After Update', $fresh['site_name']);
    }

    public function test_settings_update_updates_home_content_when_media_settings_already_exist(): void
    {
        $admin = $this->createSuperAdmin();

        Setting::query()->create([
            'key' => 'site_logo',
            'value' => 'settings/logo.png',
            'type' => 'file',
            'group' => 'media',
        ]);

        Setting::query()->create([
            'key' => 'site_icon',
            'value' => 'settings/icon.png',
            'type' => 'file',
            'group' => 'media',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'home_page_content' => '[banner-123]',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'home_page_content',
            'value' => '[banner-123]',
            'type' => 'text',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'site_logo',
            'value' => 'settings/logo.png',
            'type' => 'file',
        ]);
    }

    public function test_settings_update_saves_site_icon_and_home_background_effect_toggle(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_icon' => 'settings/site-icon.png',
            'home_background_effects_enabled' => '1',
            'home_background_effect_intensity' => 'strong',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'site_icon',
            'value' => 'settings/site-icon.png',
            'type' => 'file',
            'group' => 'media',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'home_background_effects_enabled',
            'value' => '1',
            'type' => 'text',
            'group' => 'general',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'home_background_effect_intensity',
            'value' => 'strong',
            'type' => 'text',
            'group' => 'general',
        ]);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_icon' => '',
            'home_background_effects_enabled' => '0',
            'home_background_effect_intensity' => 'subtle',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'site_icon',
            'value' => null,
            'type' => 'file',
            'group' => 'media',
        ]);

        $this->assertSame('0', SharedUiCache::appSettings()['home_background_effects_enabled']);
        $this->assertSame('subtle', SharedUiCache::appSettings()['home_background_effect_intensity']);
    }

    public function test_settings_update_saves_global_logo_controls_for_dashboard_layouts(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'site_logo' => 'settings/global-logo.svg',
            'header_show_logo' => '1',
            'header_logo_width' => 112,
            'header_logo_height' => 56,
            'header_logo_padding_inline' => 6,
            'header_logo_padding_block' => 4,
            'header_logo_margin_inline' => 10,
            'header_logo_margin_block' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'site_logo',
            'value' => 'settings/global-logo.svg',
            'type' => 'file',
            'group' => 'media',
        ]);

        $settings = SharedUiCache::appSettings();

        $this->assertSame('settings/global-logo.svg', $settings['site_logo']);
        $this->assertSame('1', $settings['header_show_logo']);
        $this->assertSame('112', $settings['header_logo_width']);
        $this->assertSame('56', $settings['header_logo_height']);
        $this->assertSame('6', $settings['header_logo_padding_inline']);
        $this->assertSame('4', $settings['header_logo_padding_block']);
        $this->assertSame('10', $settings['header_logo_margin_inline']);
        $this->assertSame('2', $settings['header_logo_margin_block']);
    }

    public function test_header_menu_store_clears_shared_ui_cache(): void
    {
        $admin = $this->createSuperAdmin();

        HeaderMenu::query()->create([
            'title' => 'Menu A',
            'url' => '/a',
            'order' => 1,
        ]);

        $this->assertCount(1, SharedUiCache::headerMenus());

        $response = $this->actingAs($admin)->post(route('admin.header.menu.store'), [
            'title' => 'Menu B',
            'url' => '/b',
        ]);

        $response->assertRedirect();

        $menus = SharedUiCache::headerMenus();
        $this->assertCount(2, $menus);
    }

    public function test_footer_column_store_clears_shared_ui_cache(): void
    {
        $admin = $this->createSuperAdmin();

        FooterColumn::query()->create([
            'title' => 'Column A',
            'order' => 1,
        ]);

        $this->assertCount(1, SharedUiCache::footerColumns());

        $response = $this->actingAs($admin)->post(route('admin.footer.column.store'), [
            'title' => 'Column B',
        ]);

        $response->assertRedirect();

        $columns = SharedUiCache::footerColumns();
        $this->assertCount(2, $columns);
    }

    public function test_shared_auth_payload_includes_leave_management_permissions(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'can_manage_student_leaves' => false,
            'can_manage_leave_types' => true,
            'can_manage_school_calendar' => true,
            'can_manage_school_holidays' => false,
        ]);
        $user->assignRole('staff');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.can_manage_leave_types', true)
                ->where('auth.user.can_manage_school_calendar', true)
                ->where('auth.user.can_manage_school_holidays', false)
            );
    }

    public function test_shared_payload_includes_student_leaves_feature_flag(): void
    {
        config()->set('features.student_leaves.enabled', false);

        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('features.student_leaves_enabled', false)
            );
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
