<?php

namespace App\Http\Controllers;

use App\Models\PageComponent;
use App\Services\Pricing\PricingComponentPlanSyncService;
use App\Support\PublicContentNormalizer;
use App\Support\SharedUiCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function __construct(
        private readonly PricingComponentPlanSyncService $pricingComponentPlanSyncService,
        private readonly PublicContentNormalizer $publicContentNormalizer,
    ) {
    }

    public function index(Request $request)
    {
        $hasSettingsTable = Schema::hasTable('settings');
        $hasPageComponentsTable = Schema::hasTable('page_components');

        $appSettings = $hasSettingsTable
            ? collect(SharedUiCache::appSettings())
            : collect();

        $siteLogo = (string) ($appSettings->get('site_logo') ?? '');

        $activeComponents = collect();
        $inactiveShortcodes = [];

        if ($hasPageComponentsTable) {
            $inactiveShortcodes = PageComponent::query()
                ->where('is_active', false)
                ->pluck('shortcode')
                ->filter(fn (mixed $shortcode): bool => is_string($shortcode) && trim($shortcode) !== '')
                ->values()
                ->all();

            $activeComponents = PageComponent::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(function (PageComponent $component) use ($siteLogo) {
                $originalContent = (string) $component->content;
                $syncedContent = $this->pricingComponentPlanSyncService->syncContent($originalContent);

                if ($syncedContent !== $originalContent) {
                    $component->content = $syncedContent;
                    $component->save();
                }

                $component->safe_content = $this->publicContentNormalizer->safeComponentHtmlForRender($syncedContent);
                $component->content = $this->publicContentNormalizer->normalizeComponentContentForRender($syncedContent, $siteLogo);

                return $component;
                });
        }

        $homeContent = $hasSettingsTable
            ? $this->publicContentNormalizer->sanitizeHtml(
                $this->publicContentNormalizer->stripShortcodes(
                    DB::table('settings')->where('key', 'home_page_content')->value('value'),
                    $inactiveShortcodes
                )
            )
            : null;

        return Inertia::render('Welcome', [
            'homeContent' => $homeContent,
            'components' => $activeComponents,
            'app_settings' => $appSettings,
            'registrationNotice' => $request->query('registration') === 'pending-approval'
                ? 'تم إرسال طلب الانضمام للمسؤول، وسيتم تفعيل الحساب بعد المراجعة.'
                : null,
        ]);
    }
}
