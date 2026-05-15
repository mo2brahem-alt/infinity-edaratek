<?php

namespace Tests\Unit;

use App\Support\PublicHtmlSanitizer;
use Tests\TestCase;

class PublicHtmlSanitizerTest extends TestCase
{
    public function test_it_removes_script_event_handlers_and_javascript_urls(): void
    {
        $sanitizer = new PublicHtmlSanitizer();

        $sanitized = $sanitizer->sanitize(
            '<div onclick="alert(1)"><script>alert(1)</script><a href="javascript:alert(1)" onmouseover="evil()">رابط</a><img src="https://example.com/image.png" onerror="boom()"></div>'
        );

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('onclick=', $sanitized);
        $this->assertStringNotContainsString('onmouseover=', $sanitized);
        $this->assertStringNotContainsString('onerror=', $sanitized);
        $this->assertStringNotContainsString('javascript:alert(1)', $sanitized);
        $this->assertStringContainsString('<a>رابط</a>', $sanitized);
        $this->assertStringContainsString('<img src="https://example.com/image.png">', $sanitized);
    }

    public function test_it_preserves_allowed_markup_and_safe_links(): void
    {
        $sanitizer = new PublicHtmlSanitizer();

        $sanitized = $sanitizer->sanitize(
            '<h2>عنوان</h2><p>نص <strong>مهم</strong> مع <a href="https://example.com" target="_blank">رابط آمن</a>.</p><ul><li>عنصر أول</li><li>عنصر ثان</li></ul>'
        );

        $this->assertStringContainsString('<h2>عنوان</h2>', $sanitized);
        $this->assertStringContainsString('<strong>مهم</strong>', $sanitized);
        $this->assertStringContainsString('<ul><li>عنصر أول</li><li>عنصر ثان</li></ul>', $sanitized);
        $this->assertStringContainsString('href="https://example.com"', $sanitized);
        $this->assertStringContainsString('target="_blank"', $sanitized);
        $this->assertStringContainsString('rel="noopener noreferrer"', $sanitized);
    }
}
