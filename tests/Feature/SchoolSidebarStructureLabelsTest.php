<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchoolSidebarStructureLabelsTest extends TestCase
{
    public function test_role_layout_contains_required_sidebar_group_and_page_labels(): void
    {
        $content = file_get_contents(resource_path('js/Layouts/RoleLayout.vue'));

        $this->assertIsString($content);

        $requiredLabels = [
            'الهيكل الأكاديمي',
            'المراحل الدراسية',
            'العام الدراسي',
            'الفصل الدراسي',
            'إعدادات التقويم المدرسي',
            'الهيكل الطلابي',
            'الفصول التعليمية',
            'الحضور اليومي',
            'إجازات الطلاب',
        ];

        foreach ($requiredLabels as $label) {
            $this->assertStringContainsString(
                $label,
                $content,
                "تعذر العثور على التسمية المطلوبة {$label} داخل القائمة الجانبية.",
            );
        }
    }

    public function test_role_layout_uses_scrollable_content_shell_and_full_width_footer(): void
    {
        $content = file_get_contents(resource_path('js/Layouts/RoleLayout.vue'));
        $footer = file_get_contents(resource_path('js/Components/Layout/AppDashboardFooter.vue'));

        $this->assertIsString($content);
        $this->assertIsString($footer);
        $this->assertStringContainsString('role-dashboard-shell', $content);
        $this->assertStringContainsString('role-body-grid', $content);
        $this->assertStringContainsString('sidebar-shell', $content);
        $this->assertStringContainsString('role-workspace', $content);
        $this->assertStringContainsString('role-main-shell', $content);
        $this->assertStringContainsString('role-main-scroll', $content);
        $this->assertStringContainsString('role-footer-band', $content);
        $this->assertStringContainsString('role-footer-shell', $content);
        $this->assertStringContainsString('sidebar-brand-card', $content);
        $this->assertStringContainsString('sidebar-user-card', $content);
        $this->assertStringContainsString('sidebar-scroll', $content);
        $this->assertStringContainsString('sidebar-primary-link', $content);
        $this->assertStringContainsString('sidebar-primary-title', $content);
        $this->assertStringContainsString('sidebar-accordion-container', $content);
        $this->assertStringContainsString('role-dashboard-shell relative isolate flex min-h-[100svh] flex-col overflow-x-hidden overflow-y-visible', $content);
        $this->assertStringContainsString('role-body-grid relative flex min-w-0 flex-col md:min-h-0 md:flex-1 md:grid', $content);
        $this->assertStringContainsString('sidebar-shell role-mobile-drawer fixed inset-y-0 right-0 z-[70] flex', $content);
        $this->assertStringContainsString('role-workspace flex min-w-0 flex-col md:min-h-0 md:flex-1 md:overflow-hidden', $content);
        $this->assertStringContainsString('role-main-shell min-w-0 overflow-visible md:flex-1 md:min-h-0 md:overflow-x-hidden md:overflow-y-auto', $content);
        $this->assertStringContainsString('scrollbar-gutter: auto;', $content);
        $this->assertStringContainsString('overscroll-behavior: auto;', $content);
        $this->assertStringContainsString('.sidebar-primary-link::before', $content);
        $this->assertStringContainsString('.sidebar-primary-title {', $content);
        $this->assertStringContainsString('.sidebar-accordion-header::before', $content);
        $this->assertStringContainsString('min-height: 3.1rem;', $content);
        $this->assertStringNotContainsString('sidebar-brand-badge', $content);
        $this->assertStringNotContainsString('{{ roleLabel }}', $footer);
    }
}
