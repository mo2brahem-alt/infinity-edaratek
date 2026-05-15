<?php

namespace Tests\Feature;

use Tests\TestCase;

class ManagerStructurePageDesignTest extends TestCase
{
    public function test_manager_structure_page_uses_shared_shell_and_surface_primitives(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/SchoolStructure.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('ui-page-shell manager-structure-shell', $content);
        $this->assertStringContainsString('ui-page-hero manager-structure-hero', $content);
        $this->assertStringContainsString('ui-page-header', $content);
        $this->assertStringContainsString('ui-page-kicker manager-structure-eyebrow', $content);
        $this->assertStringContainsString('ui-page-title text-2xl font-black text-white', $content);
        $this->assertStringContainsString('ui-section manager-structure-card', $content);
        $this->assertStringContainsString('ui-filter-bar manager-structure-form', $content);
        $this->assertStringContainsString('ui-form-shell manager-structure-form', $content);
        $this->assertStringContainsString('ui-card-soft manager-structure-card-soft', $content);
        $this->assertStringContainsString('ui-select manager-structure-input', $content);
        $this->assertStringContainsString('ui-input manager-structure-input', $content);
        $this->assertStringContainsString('ui-primary-button manager-structure-primary-button', $content);
        $this->assertStringContainsString('ui-secondary-button manager-structure-secondary-button', $content);
        $this->assertStringContainsString('aria-label="إغلاق نافذة المستخدم"', $content);
        $this->assertStringContainsString('manager-structure-shell--light', $content);
        $this->assertStringContainsString('useThemeMode', $content);
        $this->assertStringContainsString(":global(html.theme-light) .manager-structure-hero", $content);
    }

    public function test_manager_structure_page_contains_updated_sections_and_filters(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/SchoolStructure.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('مساحة تنظيم المدرسة', $content);
        $this->assertStringContainsString('الإدارات والأدوار المعتمدة', $content);
        $this->assertStringContainsString('مستخدمو المدرسة', $content);
        $this->assertStringContainsString('الأدوار العامة للمستخدم', $content);
        $this->assertStringContainsString('مجموعات الصلاحيات المدرسية', $content);
        $this->assertStringContainsString('إضافة مستخدم جديد', $content);
        $this->assertStringContainsString('بحث سريع', $content);
        $this->assertStringContainsString('مسح الفلاتر', $content);
    }
}
