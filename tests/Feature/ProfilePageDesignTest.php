<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProfilePageDesignTest extends TestCase
{
    public function test_profile_page_uses_account_center_layout_and_accessible_photo_controls(): void
    {
        $content = file_get_contents(resource_path('js/Pages/Profile/Edit.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('ui-page-shell max-w-6xl', $content);
        $this->assertStringContainsString('ui-page-hero', $content);
        $this->assertStringContainsString('ui-form-shell', $content);
        $this->assertStringContainsString('profile-photo-input', $content);
        $this->assertStringContainsString('aria-controls="profile-photo-input"', $content);
        $this->assertStringContainsString('aria-label="اختيار صورة شخصية جديدة"', $content);
        $this->assertStringContainsString('ui-secondary-button', $content);
        $this->assertStringContainsString('ui-primary-button', $content);
        $this->assertStringContainsString('ui-inline-alert ui-inline-alert--info', $content);
    }
}
