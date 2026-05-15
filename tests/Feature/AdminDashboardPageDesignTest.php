<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminDashboardPageDesignTest extends TestCase
{
    public function test_admin_dashboard_uses_real_metrics_structure_and_shared_dashboard_primitives(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Dashboard.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AdminLayout from '@/Layouts/AdminLayout.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString('metrics:', $content);
        $this->assertStringContainsString('recentSchools:', $content);
        $this->assertStringContainsString('primaryStats', $content);
        $this->assertStringContainsString('secondarySummaries', $content);
        $this->assertStringContainsString('schoolStatusMeta', $content);
        $this->assertStringContainsString('ui-page-shell', $content);
        $this->assertStringContainsString('ui-page-hero', $content);
        $this->assertStringContainsString('ui-stat-grid', $content);
        $this->assertStringContainsString('ui-table-shell', $content);
        $this->assertStringContainsString('<AppStatePanel', $content);
        $this->assertStringContainsString('recentSchools.length', $content);
        $this->assertStringNotContainsString('$12,400', $content);
        $this->assertStringNotContainsString('85%', $content);
    }
}
