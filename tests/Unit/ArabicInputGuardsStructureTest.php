<?php

namespace Tests\Unit;

use Tests\TestCase;

class ArabicInputGuardsStructureTest extends TestCase
{
    public function test_app_bootstraps_shared_input_guards(): void
    {
        $contents = file_get_contents(resource_path('js/app.js'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString("installInputGuards", $contents);
        $this->assertStringContainsString("installInputGuards?.();", $contents);
    }

    public function test_shared_input_guards_define_arabic_validation_messages(): void
    {
        $contents = file_get_contents(resource_path('js/utils/installInputGuards.js'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('يرجى إدخال بريد إلكتروني صحيح.', $contents);
        $this->assertStringContainsString('يرجى إدخال رقم جوال سعودي صحيح', $contents);
        $this->assertStringContainsString("document.addEventListener('submit'", $contents);
        $this->assertStringContainsString('target.setCustomValidity', $contents);
    }
}
