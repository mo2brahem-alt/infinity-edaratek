<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminUsersPageDesignTest extends TestCase
{
    public function test_admin_users_page_uses_unified_filters_table_and_dialog_patterns(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Users/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppFilterBar from '@/Components/AppFilterBar.vue';", $content);
        $this->assertStringContainsString("import AppSearchField from '@/Components/AppSearchField.vue';", $content);
        $this->assertStringContainsString("import AppStatePanel from '@/Components/AppStatePanel.vue';", $content);
        $this->assertStringContainsString("import { useActionDialog } from '@/composables/useActionDialog';", $content);
        $this->assertStringContainsString('ui-page-shell', $content);
        $this->assertStringContainsString('ui-page-hero', $content);
        $this->assertStringContainsString('<Head title="إدارة الحسابات"', $content);
        $this->assertStringContainsString('المستخدمون', $content);
        $this->assertStringContainsString('المدارس', $content);
        $this->assertStringContainsString('id="users-section"', $content);
        $this->assertStringContainsString('id="schools-section"', $content);
        $this->assertStringContainsString('const selectedSchool = ref(null);', $content);
        $this->assertStringContainsString('openSchoolDetails(school)', $content);
        $this->assertStringContainsString('closeSchoolDetails', $content);
        $this->assertStringContainsString('selectedSchool.structure?.stages', $content);
        $this->assertStringContainsString('<AppFilterBar', $content);
        $this->assertStringContainsString('<AppSearchField', $content);
        $this->assertStringContainsString('ui-table-shell', $content);
        $this->assertStringContainsString('ui-mobile-card-list', $content);
        $this->assertStringContainsString('aria-label="بحث في المستخدمين"', $content);
        $this->assertStringContainsString(':aria-label="`تعديل بيانات المستخدم ${user.name}`"', $content);
        $this->assertStringContainsString(':aria-label="`حذف المستخدم ${user.name}`"', $content);
        $this->assertStringContainsString('actionDialog.confirm', $content);
        $this->assertStringNotContainsString('window.confirm(', $content);
    }
}
