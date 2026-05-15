<?php

namespace Tests\Unit;

use Tests\TestCase;

class ThemeModeStructureTest extends TestCase
{
    public function test_theme_css_contains_shared_tokens_for_light_and_dark_modes(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('--theme-bg', $css);
        $this->assertStringContainsString('--theme-surface-elevated', $css);
        $this->assertStringContainsString('html.theme-light', $css);
        $this->assertStringContainsString('html.theme-dark', $css);
        $this->assertStringContainsString('.app-action-dialog__panel', $css);
    }

    public function test_theme_mode_composable_updates_dom_theme_attributes(): void
    {
        $script = file_get_contents(base_path('resources/js/composables/useThemeMode.js'));

        $this->assertNotFalse($script);
        $this->assertStringContainsString("root.classList.toggle('theme-light'", $script);
        $this->assertStringContainsString("root.classList.toggle('theme-dark'", $script);
        $this->assertStringContainsString("root.setAttribute('data-theme', normalizedMode)", $script);
    }
}
