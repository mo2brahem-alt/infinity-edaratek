<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageComponent;
use App\Support\PublicContentNormalizer;
use Inertia\Inertia;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicContentNormalizer $publicContentNormalizer,
    ) {
    }

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $inactiveShortcodes = PageComponent::query()
            ->where('is_active', false)
            ->pluck('shortcode')
            ->filter(fn (mixed $shortcode): bool => is_string($shortcode) && trim($shortcode) !== '')
            ->values()
            ->all();

        $page->safe_content = $this->publicContentNormalizer->sanitizeHtml(
            $this->publicContentNormalizer->stripShortcodes($page->content, $inactiveShortcodes)
        );

        $components = PageComponent::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (PageComponent $component) {
                $component->safe_content = $this->publicContentNormalizer->safeComponentHtmlForRender($component->content);
                $component->content = $this->publicContentNormalizer->normalizeComponentContentForRender($component->content);

                return $component;
            });

        return Inertia::render('PageViewer', [
            'page' => $page,
            'components' => $components,
        ]);
    }
}
