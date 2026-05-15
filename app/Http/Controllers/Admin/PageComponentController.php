<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageComponent;
use App\Services\Pricing\PricingComponentPlanSyncService;
use App\Support\PublicContentNormalizer;
use Illuminate\Http\Request;

class PageComponentController extends Controller
{
    public function __construct(
        private readonly PricingComponentPlanSyncService $pricingComponentPlanSyncService,
        private readonly PublicContentNormalizer $publicContentNormalizer,
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255|unique:page_components,shortcode|starts_with:[|ends_with:]',
            'content' => 'required|string',
        ]);

        $validated['content'] = $this->publicContentNormalizer->sanitizeComponentContent(
            $this->pricingComponentPlanSyncService->syncContent($validated['content'])
        );

        PageComponent::create($validated);

        return back()->with('message', 'تم إنشاء المكوّن بنجاح');
    }

    public function update(Request $request, $id)
    {
        $component = PageComponent::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shortcode' => 'required|string|max:255|starts_with:[|ends_with:]|unique:page_components,shortcode,' . $id,
            'content' => 'required|string',
        ]);

        $validated['content'] = $this->publicContentNormalizer->sanitizeComponentContent(
            $this->pricingComponentPlanSyncService->syncContent($validated['content'])
        );

        $component->update($validated);

        return back()->with('message', 'تم تحديث المكوّن بنجاح');
    }

    public function destroy($id)
    {
        PageComponent::findOrFail($id)->delete();

        return back()->with('message', 'تم حذف المكوّن');
    }
}
