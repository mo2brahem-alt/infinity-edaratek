<?php

namespace Tests\Feature;

use Tests\TestCase;

class SharedFilterFeedbackFoundationTest extends TestCase
{
    public function test_shared_filter_search_and_feedback_components_exist_with_expected_contracts(): void
    {
        $filterBar = file_get_contents(resource_path('js/Components/AppFilterBar.vue'));
        $searchField = file_get_contents(resource_path('js/Components/AppSearchField.vue'));
        $inlineAlert = file_get_contents(resource_path('js/Components/AppInlineAlert.vue'));
        $pageFeedback = file_get_contents(resource_path('js/Components/AppPageFeedback.vue'));
        $statePanel = file_get_contents(resource_path('js/Components/AppStatePanel.vue'));

        $this->assertIsString($filterBar);
        $this->assertIsString($searchField);
        $this->assertIsString($inlineAlert);
        $this->assertIsString($pageFeedback);
        $this->assertIsString($statePanel);

        $this->assertStringContainsString('ui-filter-bar', $filterBar);
        $this->assertStringContainsString('ui-filter-header', $filterBar);
        $this->assertStringContainsString('ui-filter-footer', $filterBar);
        $this->assertStringContainsString('ui-search-shell', $searchField);
        $this->assertStringContainsString('ui-search-control', $searchField);
        $this->assertStringContainsString('aria-label="مسح البحث"', $searchField);
        $this->assertStringContainsString('ui-inline-alert-content', $inlineAlert);
        $this->assertStringContainsString("usePage()", $pageFeedback);
        $this->assertStringContainsString('page.props?.flash', $pageFeedback);
        $this->assertStringContainsString("props.variant === 'no-results'", $statePanel);
    }

    public function test_inertia_middleware_and_dashboard_layouts_share_and_render_page_feedback(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
        $adminLayout = file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $roleLayout = file_get_contents(resource_path('js/Layouts/RoleLayout.vue'));

        $this->assertIsString($middleware);
        $this->assertIsString($adminLayout);
        $this->assertIsString($roleLayout);

        $this->assertStringContainsString("'flash' => [", $middleware);
        $this->assertStringContainsString("'message' => fn () => \$request->session()->get('message')", $middleware);
        $this->assertStringContainsString("'error' => fn () => \$request->session()->get('error')", $middleware);
        $this->assertStringContainsString("import AppPageFeedback from '@/Components/AppPageFeedback.vue';", $adminLayout);
        $this->assertStringContainsString("import AppPageFeedback from '@/Components/AppPageFeedback.vue';", $roleLayout);
        $this->assertStringContainsString('<AppPageFeedback />', $adminLayout);
        $this->assertStringContainsString('<AppPageFeedback />', $roleLayout);
    }
}
