<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPlansPageDesignTest extends TestCase
{
    public function test_admin_plans_page_uses_shared_filter_search_and_state_primitives(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Plans/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppFilterBar from '@/Components/AppFilterBar.vue';", $content);
        $this->assertStringContainsString("import AppSearchField from '@/Components/AppSearchField.vue';", $content);
        $this->assertStringContainsString("import AppInlineAlert from '@/Components/AppInlineAlert.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString('<AppFilterBar', $content);
        $this->assertStringContainsString('<AppSearchField', $content);
        $this->assertStringContainsString('<AppStatePanel', $content);
        $this->assertStringContainsString('activeFilterCount', $content);
        $this->assertStringContainsString('hasActiveFilters', $content);
        $this->assertStringContainsString(":variant=\"hasActiveFilters ? 'no-results' : 'empty'\"", $content);
        $this->assertStringContainsString('مسح الفلاتر', $content);
    }
}
