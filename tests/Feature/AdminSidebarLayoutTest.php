<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminSidebarLayoutTest extends TestCase
{
    public function test_super_admin_sidebar_has_required_labels_and_order(): void
    {
        $layout = file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));
        $this->assertNotFalse($layout, 'Admin layout file must be readable.');

        $this->assertStringContainsString('نظرة عامة', $layout);
        $this->assertStringNotContainsString('الإعدادات العامة', $layout);
        $this->assertStringContainsString('القوالب الافتراضية', $layout);
        $this->assertStringContainsString('إدارة الحسابات', $layout);
        $this->assertStringContainsString('إدارة الاشتراكات', $layout);
        $this->assertStringContainsString('مظهر الموقع', $layout);

        $this->assertBefore($layout, "route('admin.dashboard')", "route('admin.school_defaults.index')");
        $this->assertBefore($layout, "route('admin.school_defaults.index')", 'إدارة الحسابات');
        $this->assertBefore($layout, 'إدارة الحسابات', "route('admin.plans.index')");
        $this->assertBefore($layout, "route('admin.plans.index')", "route('admin.settings.index')");
    }

    public function test_super_admin_sidebar_uses_unified_shell_cards_and_routes(): void
    {
        $layout = file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));
        $this->assertNotFalse($layout, 'Admin layout file must be readable.');

        $this->assertStringContainsString('admin-dashboard-shell', $layout);
        $this->assertStringContainsString('admin-sidebar-shell', $layout);
        $this->assertStringContainsString('admin-body-grid', $layout);
        $this->assertStringContainsString('admin-content-shell', $layout);
        $this->assertStringContainsString('admin-workspace', $layout);
        $this->assertStringContainsString('admin-main-shell', $layout);
        $this->assertStringContainsString('admin-main-scroll', $layout);
        $this->assertStringContainsString('admin-footer-shell', $layout);
        $this->assertStringContainsString('admin-footer-band', $layout);
        $this->assertStringContainsString('admin-dashboard-shell relative isolate flex min-h-[100svh] flex-col overflow-x-hidden overflow-y-visible', $layout);
        $this->assertStringContainsString('md:grid-cols-[18rem_minmax(0,1fr)]', $layout);
        $this->assertStringContainsString('admin-main-shell min-w-0 overflow-visible md:flex-1 md:min-h-0 md:overflow-x-hidden md:overflow-y-auto', $layout);
        $this->assertStringContainsString('max-width: none;', $layout);
        $this->assertStringContainsString('scrollbar-gutter: auto;', $layout);
        $this->assertStringContainsString('overscroll-behavior: auto;', $layout);
        $this->assertStringContainsString('admin-sidebar-brand', $layout);
        $this->assertStringContainsString('admin-sidebar-user-card', $layout);
        $this->assertStringContainsString('admin-sidebar-group', $layout);
        $this->assertStringContainsString('admin-sidebar-logout', $layout);
        $this->assertStringNotContainsString('admin-sidebar-badge', $layout);

        $this->assertStringContainsString("route('admin.dashboard')", $layout);
        $this->assertStringNotContainsString("route('admin.schools.index')", $layout);
        $this->assertStringContainsString("route('admin.school_defaults.index')", $layout);
        $this->assertStringContainsString("route('users.index')", $layout);
        $this->assertStringContainsString('#users-section', $layout);
        $this->assertStringContainsString('#schools-section', $layout);
        $this->assertStringContainsString("route('roles.index')", $layout);
        $this->assertStringContainsString("route('departments.index')", $layout);
        $this->assertStringContainsString("route('admin.plans.index')", $layout);
        $this->assertStringContainsString("route('admin.settings.index')", $layout);
    }

    private function assertBefore(string $content, string $first, string $second): void
    {
        $firstPos = mb_strpos($content, $first);
        $secondPos = mb_strpos($content, $second);

        $this->assertNotFalse($firstPos, "Expected marker not found: {$first}");
        $this->assertNotFalse($secondPos, "Expected marker not found: {$second}");
        $this->assertTrue($firstPos < $secondPos, "Expected '{$first}' to appear before '{$second}'.");
    }
}
