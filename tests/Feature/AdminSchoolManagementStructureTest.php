<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSchoolManagementStructureTest extends TestCase
{
    public function test_admin_school_management_hides_standalone_taxonomy_integration_ui(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Schools/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringNotContainsString('تكامل الدول والمحافظات', $content);
        $this->assertStringNotContainsString('syncCountriesFromGlobalApi', $content);
        $this->assertStringNotContainsString('إعادة المزامنة', $content);
        $this->assertStringContainsString('ensureDirectorateGovernorates', $content);
        $this->assertStringContainsString("route('admin.governorates.sync_global')", $content);
        $this->assertStringContainsString('directorateGovernoratesSyncing', $content);
        $this->assertStringContainsString('أنواع التعليم والنطاقات التعليمية', $content);
    }

    public function test_admin_school_management_removes_filter_bar_from_education_types_and_directorates_page(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Schools/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringNotContainsString("import AppFilterBar from '@/Components/AppFilterBar.vue';", $content);
        $this->assertStringNotContainsString('<AppFilterBar', $content);
        $this->assertStringNotContainsString('filterForm', $content);
        $this->assertStringNotContainsString('hasActiveFilters', $content);
        $this->assertStringNotContainsString('إلغاء الفلاتر', $content);
        $this->assertStringContainsString('أنواع التعليم', $content);
        $this->assertStringContainsString('النطاقات التعليمية المتاحة', $content);
    }
    public function test_admin_school_management_uses_enhanced_reference_layout_for_taxonomy_sections(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Schools/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('education-settings-page', $content);
        $this->assertStringContainsString('html.theme-light .education-settings-page', $content);
        $this->assertStringContainsString('educationTypeRows', $content);
        $this->assertStringContainsString('totalLinkedSchools', $content);
        $this->assertStringContainsString('activeDirectoratesCount', $content);
        $this->assertStringContainsString('ui-avatar', $content);
        $this->assertStringContainsString('border-cyan-900/40', $content);
        $this->assertStringContainsString('directorateFormTitle', $content);
        $this->assertStringContainsString("import AppModal from '@/Components/AppModal.vue';", $content);
        $this->assertStringContainsString('openCreateEducationTypeModal', $content);
        $this->assertStringContainsString('openCreateDirectorateModal', $content);
        $this->assertStringContainsString('<AppModal', $content);
        $this->assertStringNotContainsString('تهيئة النموذج', $content);
    }
}
