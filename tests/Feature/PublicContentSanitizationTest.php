<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_exposes_sanitized_home_content(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'home_page_content'],
            [
                'value' => '<h2>مرحبا</h2><script>alert(1)</script><a href="javascript:alert(1)" onclick="evil()">رابط</a><a href="https://example.com" target="_blank">رابط آمن</a>',
                'type' => 'text',
                'group' => 'general',
            ]
        );

        $response = $this->get(route('welcome'));

        $response->assertOk();

        $page = $response->viewData('page');
        $content = data_get($page, 'props.homeContent', '');

        $this->assertStringContainsString('<h2>مرحبا</h2>', $content);
        $this->assertStringContainsString('href="https://example.com"', $content);
        $this->assertStringContainsString('rel="noopener noreferrer"', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onclick=', $content);
        $this->assertStringNotContainsString('javascript:alert(1)', $content);
    }

    public function test_page_viewer_exposes_sanitized_page_and_component_html(): void
    {
        Page::query()->create([
            'title' => 'سياسة الخصوصية',
            'slug' => 'privacy-policy',
            'content' => '<p onclick="bad()">محتوى الصفحة</p>[plain-html]<iframe src="https://evil.example"></iframe>',
            'is_active' => true,
        ]);

        PageComponent::query()->create([
            'name' => 'Plain Html',
            'shortcode' => '[plain-html]',
            'content' => '<div><img src="javascript:alert(1)" onerror="evil()"><a href="/safe-path">رابط داخلي</a></div>',
            'is_active' => true,
        ]);

        $response = $this->get(route('page.show', ['slug' => 'privacy-policy']));

        $response->assertOk();

        $page = $response->viewData('page');
        $pageContent = data_get($page, 'props.page.safe_content', '');
        $componentContent = data_get($page, 'props.components.0.safe_content', '');

        $this->assertStringContainsString('<p>محتوى الصفحة</p>', $pageContent);
        $this->assertStringNotContainsString('onclick=', $pageContent);
        $this->assertStringNotContainsString('<iframe', $pageContent);

        $this->assertStringContainsString('href="/safe-path"', $componentContent);
        $this->assertStringNotContainsString('javascript:alert(1)', $componentContent);
        $this->assertStringNotContainsString('onerror=', $componentContent);
    }
}
