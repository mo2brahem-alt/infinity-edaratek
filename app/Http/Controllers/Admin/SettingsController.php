<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterColumn;
use App\Models\HeaderMenu;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Setting;
use App\Support\PublicContentNormalizer;
use App\Support\SafeSvg;
use App\Support\SafePublicUrl;
use App\Support\SharedUiCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SettingsController extends Controller
{
    private const MAX_TEXT_SETTING_LENGTH = 5000;

    public function __construct(
        private readonly PublicContentNormalizer $publicContentNormalizer,
    ) {
    }

    public function index(Request $request)
    {
        $initialTab = (string) $request->string('tab')->toString();

        if ($initialTab === 'default_templates') {
            return redirect()->route('admin.school_defaults.index');
        }

        $settings = Setting::all()->groupBy('group');
        $footerColumns = FooterColumn::with('items')->orderBy('order')->get();
        $headerMenus = HeaderMenu::with('items')->orderBy('order')->get();
        $pages = Page::orderByDesc('id')->get();
        $components = PageComponent::orderByDesc('id')->get();

        return Inertia::render('Admin/Settings/Index', [
            'initialTab' => $initialTab,
            'settings' => $settings,
            'footerColumns' => $footerColumns,
            'headerMenus' => $headerMenus,
            'pages' => $pages,
            'components' => $components,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate($this->settingsValidationRules($request));

        $data = $request->except(['_token']);

        foreach ($data as $key => $value) {
            $this->assertValidSettingKey((string) $key);
            $group = $this->resolveGroup((string) $key);

            if ($request->hasFile((string) $key)) {
                $file = $request->file((string) $key);

                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        $key => 'الملف المرفوع غير صالح.',
                    ]);
                }

                if (SafeSvg::isSvg($file)) {
                    SafeSvg::assertUploadIsSafe($file, (string) $key);
                }

                $setting = Setting::where('key', $key)->first();
                if ($setting && $setting->value) {
                    Storage::disk('public')->delete($setting->value);
                }

                $path = $file->store('settings', 'public');

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $path, 'type' => 'file', 'group' => $group]
                );

                continue;
            }

            if (in_array($key, ['site_logo', 'site_icon', 'hero_video', 'banner_media'], true)
                && is_string($value)
                && trim($value) === ''
            ) {
                $setting = Setting::where('key', $key)->first();
                if ($setting && $setting->value) {
                    Storage::disk('public')->delete($setting->value);
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => null, 'type' => 'file', 'group' => $group]
                );

                continue;
            }

            if (is_array($value) || is_object($value)) {
                throw ValidationException::withMessages([
                    $key => 'صيغة الحقل غير مدعومة.',
                ]);
            }

            $normalizedValue = $this->normalizeSettingValue((string) $key, $value, $request);
            $type = in_array($key, ['site_logo', 'site_icon', 'banner_media'], true) ? 'file' : 'text';

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $normalizedValue, 'type' => $type, 'group' => $group]
            );
        }

        SharedUiCache::clear();

        return redirect()->back()->with('message', 'تم حفظ الإعدادات بنجاح');
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsValidationRules(Request $request): array
    {
        $rules = [
            'show_skip_intro' => ['nullable', 'boolean'],
            'home_background_effects_enabled' => ['nullable', 'boolean'],
            'home_background_effect_intensity' => ['nullable', 'string', 'in:subtle,normal,strong'],
            'header_show_logo' => ['nullable', 'boolean'],
            'header_brand_position' => ['nullable', 'string', 'in:right,left,center'],
            'header_logo_width' => ['nullable', 'integer', 'min:24', 'max:320'],
            'header_logo_height' => ['nullable', 'integer', 'min:24', 'max:240'],
            'header_logo_padding_inline' => ['nullable', 'integer', 'min:0', 'max:80'],
            'header_logo_padding_block' => ['nullable', 'integer', 'min:0', 'max:80'],
            'header_logo_margin_inline' => ['nullable', 'integer', 'min:0', 'max:120'],
            'header_logo_margin_block' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];

        $mediaRules = [
            'site_logo' => ['mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml', 'max:4096'],
            'site_icon' => ['mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml', 'max:2048'],
            'hero_video' => ['mimetypes:video/mp4,video/webm,video/ogg', 'max:102400'],
            'banner_media' => ['mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/ogg', 'max:102400'],
        ];

        foreach ($mediaRules as $key => $constraints) {
            if ($request->hasFile($key)) {
                $rules[$key] = array_merge(['nullable', 'file'], $constraints);
                continue;
            }

            $rules[$key] = ['nullable', 'string', 'max:2048'];
        }

        return $rules;
    }

    private function resolveGroup(string $key): string
    {
        if (Str::startsWith($key, 'banner_') || $key === 'show_skip_intro') {
            return 'banner';
        }

        if (in_array($key, ['site_logo', 'site_icon', 'hero_video', 'banner_media'], true)) {
            return 'media';
        }

        return 'general';
    }

    private function assertValidSettingKey(string $key): void
    {
        if (mb_strlen($key) > 100 || preg_match('/^[a-zA-Z0-9_.-]+$/', $key) !== 1) {
            throw ValidationException::withMessages([
                $key => 'اسم الإعداد غير صالح.',
            ]);
        }
    }

    private function normalizeSettingValue(string $key, mixed $value, Request $request): ?string
    {
        if (in_array($key, ['show_skip_intro', 'home_background_effects_enabled', 'header_show_logo'], true)) {
            return $request->boolean($key) ? '1' : '0';
        }

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if (mb_strlen($text) > self::MAX_TEXT_SETTING_LENGTH) {
            throw ValidationException::withMessages([
                $key => 'طول النص يتجاوز الحد المسموح.',
            ]);
        }

        if ($this->isSafeUrlSetting($key)) {
            $normalizedUrl = SafePublicUrl::normalize($text);

            if ($normalizedUrl === null) {
                throw ValidationException::withMessages([
                    $key => 'الرابط غير آمن أو غير مدعوم. يُسمح فقط بروابط http وhttps وmailto وtel أو الروابط النسبية الآمنة.',
                ]);
            }

            return $normalizedUrl;
        }

        if ($this->isSanitizedPublicContentSetting($key)) {
            return $this->publicContentNormalizer->sanitizeHtml($text);
        }

        return $text;
    }

    private function isSanitizedPublicContentSetting(string $key): bool
    {
        return in_array($key, ['home_page_content'], true);
    }

    private function isSafeUrlSetting(string $key): bool
    {
        return in_array($key, [
            'banner_btn_url',
            'header_facebook',
            'header_twitter',
            'header_instagram',
            'header_linkedin',
            'header_contact_url',
        ], true);
    }
}
