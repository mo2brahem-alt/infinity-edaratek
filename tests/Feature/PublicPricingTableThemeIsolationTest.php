<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPricingTableThemeIsolationTest extends TestCase
{
    public function test_public_pricing_cards_are_isolated_from_dashboard_light_theme(): void
    {
        $content = file_get_contents(resource_path('js/Components/Shortcodes/PricingTable.vue'));

        $this->assertIsString($content);
        $this->assertStringContainsString('pricing-table-section', $content);
        $this->assertStringContainsString('pricing-billing-toggle', $content);
        $this->assertStringContainsString('pricing-plan-card', $content);
        $this->assertStringContainsString('pricing-plan-title', $content);
        $this->assertStringContainsString(':global(html.theme-light) .pricing-table-section', $content);
        $this->assertStringContainsString('background: var(--pricing-card-bg) !important;', $content);
        $this->assertStringContainsString('color: var(--pricing-text) !important;', $content);
    }
}
