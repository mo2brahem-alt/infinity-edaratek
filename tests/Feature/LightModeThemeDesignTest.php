<?php

namespace Tests\Feature;

use Tests\TestCase;

class LightModeThemeDesignTest extends TestCase
{
    public function test_shared_light_mode_palette_uses_tinted_surfaces_instead_of_flat_white(): void
    {
        $content = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($content);
        $this->assertStringContainsString('--theme-bg: #edf3fa;', $content);
        $this->assertStringContainsString('linear-gradient(180deg, #f7fbff 0%, #edf3fa 48%, #e7eef7 100%);', $content);
        $this->assertStringContainsString('--ui-surface-3: rgba(235, 242, 249, 0.99);', $content);
        $this->assertStringContainsString('--ui-text-primary: #081a2f;', $content);
        $this->assertStringContainsString('background-color: #edf3fa;', $content);
        $this->assertStringContainsString('background-color: rgba(59, 130, 246, 0.1);', $content);
        $this->assertStringContainsString("[class*='bg-slate-950/']", $content);
        $this->assertStringContainsString("[class*='bg-slate-800/']", $content);
        $this->assertStringContainsString("[class*='border-white/8']", $content);
        $this->assertStringContainsString("[class*='bg-black/80']", $content);
        $this->assertStringContainsString("[class~='bg-slate-700']", $content);
        $this->assertStringContainsString("[class~='bg-blue-600']", $content);
        $this->assertStringContainsString('color: #ffffff !important;', $content);
    }

    public function test_school_defaults_page_extends_light_mode_tuning_to_page_level_badges_and_reference_panels(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/SchoolDefaults/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page .school-defaults-reference-panel,", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page .school-defaults-setup-badge,", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page .school-defaults-count-pill,", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page .school-defaults-type-chip,", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-slate-700'],", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-blue-600'],", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page [class*='bg-cyan-500/10'],", $content);
        $this->assertStringContainsString("html.theme-light .school-defaults-page [class*='bg-cyan-500/10'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),", $content);
    }

    public function test_admin_dialogs_use_shared_light_mode_modal_shell(): void
    {
        $settings = file_get_contents(resource_path('js/Pages/Admin/Settings/Index.vue'));
        $roles = file_get_contents(resource_path('js/Pages/Admin/Roles/Index.vue'));

        $this->assertIsString($settings);
        $this->assertIsString($roles);
        $this->assertStringContainsString('ui-theme-modal-backdrop', $settings);
        $this->assertStringContainsString('ui-theme-modal-panel', $settings);
        $this->assertStringContainsString('ui-theme-modal-backdrop', $roles);
        $this->assertStringContainsString('ui-theme-modal-panel', $roles);
    }
}
