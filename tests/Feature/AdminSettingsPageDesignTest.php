<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSettingsPageDesignTest extends TestCase
{
    public function test_admin_settings_page_uses_shared_feedback_and_settings_grouping_primitives(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Settings/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppInlineAlert from '@/Components/AppInlineAlert.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString("const activeTabMeta = computed(() => settingTabs.value.find((tab) => tab.key === activeTab.value) || null);", $content);
        $this->assertStringContainsString('<AppInlineAlert', $content);
        $this->assertStringContainsString('<AppStatePanel', $content);
        $this->assertStringContainsString('class="ui-settings-panel"', $content);
        $this->assertStringContainsString('أيقونة الموقع', $content);
        $this->assertStringContainsString('home_background_effects_enabled', $content);
        $this->assertStringContainsString('home_background_effect_intensity', $content);
        $this->assertStringContainsString('تفعيل مؤثرات الخلفية', $content);
        $this->assertStringContainsString('قوة حركة التأثير', $content);
        $this->assertStringContainsString('لا توجد مكونات مضافة بعد', $content);
    }
}
