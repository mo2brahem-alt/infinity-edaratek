<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchoolReportsPageDesignTest extends TestCase
{
    public function test_school_reports_page_uses_shared_filters_search_and_no_results_state(): void
    {
        $content = file_get_contents(resource_path('js/Pages/School/Reports.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppFilterBar from '@/Components/AppFilterBar.vue';", $content);
        $this->assertStringContainsString("import AppSearchField from '@/Components/AppSearchField.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString('<AppFilterBar', $content);
        $this->assertStringContainsString('<AppSearchField', $content);
        $this->assertStringContainsString('variant="no-results"', $content);
        $this->assertStringContainsString('ui-table-shell', $content);
        $this->assertStringContainsString('ui-mobile-card-list', $content);
    }
}
