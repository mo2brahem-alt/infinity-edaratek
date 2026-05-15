<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterColumn;
use App\Models\FooterItem;
use App\Support\SafePublicUrl;
use App\Support\SharedUiCache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FooterController extends Controller
{
    public function index()
    {
        $columns = FooterColumn::with('items')->orderBy('order')->get();

        return Inertia::render('Admin/Footer/Index', ['columns' => $columns]);
    }

    public function storeColumn(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        FooterColumn::create([
            'title' => $validated['title'],
            'order' => FooterColumn::count() + 1,
        ]);

        SharedUiCache::clear();

        return back();
    }

    public function destroyColumn($id)
    {
        FooterColumn::findOrFail($id)->delete();
        SharedUiCache::clear();

        return back();
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'footer_column_id' => 'required|exists:footer_columns,id',
            'label' => 'required|string|max:255',
            'url' => 'required|string|max:2048',
        ]);

        $validated['url'] = $this->normalizePublicUrl($validated['url'], 'url') ?? '#';

        FooterItem::create($validated);
        SharedUiCache::clear();

        return back();
    }

    public function destroyItem($id)
    {
        FooterItem::findOrFail($id)->delete();
        SharedUiCache::clear();

        return back();
    }

    private function normalizePublicUrl(?string $value, string $field): ?string
    {
        $normalized = SafePublicUrl::normalize($value);

        if ($normalized !== null) {
            return $normalized;
        }

        throw ValidationException::withMessages([
            $field => 'الرابط غير آمن أو غير مدعوم. يُسمح فقط بروابط http وhttps وmailto وtel أو الروابط النسبية الآمنة.',
        ]);
    }
}
