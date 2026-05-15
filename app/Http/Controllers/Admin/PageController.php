<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\PublicContentNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicContentNormalizer $publicContentNormalizer,
    ) {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        Page::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'content' => $this->publicContentNormalizer->sanitizeHtml($validated['content'] ?? null),
        ]);

        return back()->with('message', 'تم إنشاء الصفحة بنجاح');
    }

    public function update(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,' . $id,
            'content' => 'nullable|string',
        ]);

        $page->update([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['slug']),
            'content' => $this->publicContentNormalizer->sanitizeHtml($validated['content'] ?? null),
        ]);

        return back()->with('message', 'تم تحديث الصفحة بنجاح');
    }

    public function destroy($id)
    {
        Page::findOrFail($id)->delete();

        return back()->with('message', 'تم حذف الصفحة');
    }
}
