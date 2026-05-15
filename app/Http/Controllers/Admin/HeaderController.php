<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeaderItem;
use App\Models\HeaderMenu;
use App\Support\SafePublicUrl;
use App\Support\SharedUiCache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeaderController extends Controller
{
    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:2048',
        ]);

        HeaderMenu::create([
            'title' => $validated['title'],
            'url' => $this->normalizePublicUrl($validated['url'] ?? '#', 'url') ?? '#',
            'order' => HeaderMenu::count() + 1,
        ]);

        SharedUiCache::clear();

        return back();
    }

    public function destroyMenu($id)
    {
        HeaderMenu::findOrFail($id)->delete();
        SharedUiCache::clear();

        return back();
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'header_menu_id' => 'required|exists:header_menus,id',
            'label' => 'required|string|max:255',
            'url' => 'required|string|max:2048',
        ]);

        $validated['url'] = $this->normalizePublicUrl($validated['url'], 'url') ?? '#';

        HeaderItem::create($validated);

        SharedUiCache::clear();

        return back();
    }

    public function destroyItem($id)
    {
        HeaderItem::findOrFail($id)->delete();
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
