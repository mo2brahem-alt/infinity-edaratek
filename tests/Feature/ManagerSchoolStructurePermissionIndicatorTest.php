<?php

namespace Tests\Feature;

use Tests\TestCase;

class ManagerSchoolStructurePermissionIndicatorTest extends TestCase
{
    public function test_manager_school_structure_uses_checkmark_for_selected_permission_cards(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Manager/SchoolStructure.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('.manager-structure-checkbox::after', $content);
        $this->assertStringContainsString("content: '✓';", $content);
        $this->assertStringContainsString('.manager-structure-checkbox:checked', $content);
        $this->assertStringContainsString('.manager-structure-checkbox:focus-visible', $content);
    }
}
