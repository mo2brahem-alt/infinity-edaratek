<?php

namespace Tests\Unit;

use Tests\TestCase;

class NativeDialogRegressionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function dialogFilesProvider(): array
    {
        return [
            'admin plans' => ['resources/js/Pages/Admin/Plans/Index.vue'],
            'academic planning' => ['resources/js/Pages/School/AcademicPlanning.vue'],
            'student leaves' => ['resources/js/Pages/School/StudentLeaves.vue'],
            'media manager modal' => ['resources/js/Components/MediaManagerModal.vue'],
            'admin settings' => ['resources/js/Pages/Admin/Settings/Index.vue'],
            'admin schools' => ['resources/js/Pages/Admin/Schools/Index.vue'],
            'admin roles' => ['resources/js/Pages/Admin/Roles/Index.vue'],
            'contact form shortcode' => ['resources/js/Components/Shortcodes/ContactForm.vue'],
        ];
    }

    /**
     * @dataProvider dialogFilesProvider
     */
    public function test_representative_frontend_flows_do_not_use_native_browser_dialogs(string $relativePath): void
    {
        $absolutePath = base_path($relativePath);
        $contents = file_get_contents($absolutePath);

        $this->assertNotFalse($contents, "تعذر قراءة الملف: {$relativePath}");

        preg_match_all('/(?<![\\w$.])(confirm|alert|prompt)\\s*\\(/', $contents, $matches);

        $this->assertSame(
            [],
            $matches[0] ?? [],
            "تم العثور على native dialog داخل الملف {$relativePath}"
        );
    }
}
