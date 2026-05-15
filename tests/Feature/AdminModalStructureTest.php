<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminModalStructureTest extends TestCase
{
    public function test_shared_app_modal_provides_accessible_dialog_contract(): void
    {
        $content = file_get_contents(resource_path('js/Components/AppModal.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("aria-modal=\"true\"", $content);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden';", $content);
        $this->assertStringContainsString("document.addEventListener('keydown', handleKeydown);", $content);
        $this->assertStringContainsString("event.key === 'Escape'", $content);
        $this->assertStringContainsString("role=\"dialog\"", $content);
    }

    public function test_supervisor_assignments_page_uses_modal_for_creation_flow(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/SupervisorAssignments/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppModal from '@/Components/AppModal.vue';", $content);
        $this->assertStringContainsString('isCreateModalOpen', $content);
        $this->assertStringContainsString('<AppModal', $content);
        $this->assertStringNotContainsString('mb-6 grid grid-cols-1 gap-3 rounded-xl border border-gray-700 bg-gray-800 p-4 md:grid-cols-4', $content);
    }

    public function test_footer_page_uses_modal_for_column_creation_and_no_longer_embeds_inline_form(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Admin/Footer/Index.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString("import AppModal from '@/Components/AppModal.vue';", $content);
        $this->assertStringContainsString('showColumnModal', $content);
        $this->assertStringContainsString('<AppModal', $content);
        $this->assertStringNotContainsString('mb-8 flex items-end gap-4 rounded-xl bg-white/5 p-4', $content);
    }
}
