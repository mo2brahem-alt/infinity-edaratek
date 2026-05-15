<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuickSetupWizardStructureTest extends TestCase
{
    public function test_quick_setup_prerequisites_use_rtl_friendly_list_structure(): void
    {
        $content = file_get_contents(resource_path('js/Components/School/QuickSetupWizardModal.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('<ul v-if="Array.isArray(currentStep.prerequisites)', $content);
        $this->assertStringContainsString('class="flex items-start justify-between gap-2 rounded border border-gray-700/70 bg-gray-900/60 px-3 py-2"', $content);
        $this->assertStringContainsString(":aria-label=\"dependency.met ? 'مكتمل' : 'غير مكتمل'\"", $content);
        $this->assertStringNotContainsString("{{ dependency.met ? '✓' : '✕' }} {{ dependency.label }}", $content);
    }
}
