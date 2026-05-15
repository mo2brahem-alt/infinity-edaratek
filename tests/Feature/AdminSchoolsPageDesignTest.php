<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSchoolsPageDesignTest extends TestCase
{
    public function test_admin_schools_page_uses_shared_filter_and_state_primitives(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Schools/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppInlineAlert from '@/Components/AppInlineAlert.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString("import { useActionDialog } from '@/composables/useActionDialog';", $content);
        $this->assertStringContainsString('<AppInlineAlert', $content);
        $this->assertStringContainsString('<AppStatePanel', $content);
        $this->assertStringContainsString('education-settings-hero', $content);
        $this->assertStringContainsString('education-settings-panel', $content);
        $this->assertStringContainsString('ui-select', $content);
        $this->assertStringContainsString('ui-input', $content);
        $this->assertStringContainsString('ui-primary-button', $content);
        $this->assertStringContainsString('ui-secondary-button', $content);
    }
}
