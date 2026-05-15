<?php

namespace App\Support;

use App\Models\FooterColumn;
use App\Models\HeaderMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SharedUiCache
{
    public const SETTINGS_KEY = 'shared_ui:settings';
    public const HEADER_MENUS_KEY = 'shared_ui:header_menus';
    public const FOOTER_COLUMNS_KEY = 'shared_ui:footer_columns';

    /**
     * @return array<string, mixed>
     */
    public static function appSettings(): array
    {
        return self::remember(self::SETTINGS_KEY, function (): array {
            $settings = DB::table('settings')->pluck('value', 'key')->toArray();

            foreach ([
                'banner_btn_url',
                'header_facebook',
                'header_twitter',
                'header_instagram',
                'header_linkedin',
                'header_contact_url',
            ] as $key) {
                if (!array_key_exists($key, $settings)) {
                    continue;
                }

                $settings[$key] = SafePublicUrl::normalize($settings[$key]);
            }

            return $settings;
        }, []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function headerMenus(): array
    {
        return self::remember(self::HEADER_MENUS_KEY, function (): array {
            return HeaderMenu::with('items')
                ->orderBy('order')
                ->get()
                ->map(function (HeaderMenu $menu): array {
                    $row = $menu->toArray();
                    $row['url'] = SafePublicUrl::normalize($row['url'] ?? null) ?? '#';
                    $row['items'] = collect($row['items'] ?? [])
                        ->map(function (array $item): array {
                            $item['url'] = SafePublicUrl::normalize($item['url'] ?? null) ?? '#';

                            return $item;
                        })
                        ->values()
                        ->all();

                    return $row;
                })
                ->toArray();
        }, []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function footerColumns(): array
    {
        return self::remember(self::FOOTER_COLUMNS_KEY, function (): array {
            return FooterColumn::query()
                ->where('is_active', true)
                ->with([
                    'items' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('order'),
                ])
                ->orderBy('order')
                ->get()
                ->map(function (FooterColumn $column): array {
                    $row = $column->toArray();
                    $row['items'] = collect($row['items'] ?? [])
                        ->map(function (array $item): array {
                            $item['url'] = SafePublicUrl::normalize($item['url'] ?? null) ?? '#';

                            return $item;
                        })
                        ->values()
                        ->all();

                    return $row;
                })
                ->toArray();
        }, []);
    }

    public static function clear(): void
    {
        foreach ([self::SETTINGS_KEY, self::HEADER_MENUS_KEY, self::FOOTER_COLUMNS_KEY] as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // Swallow cache backend errors to avoid breaking admin writes.
            }
        }
    }

    /**
     * @template T
     * @param \Closure(): T $resolver
     * @param T $fallback
     * @return T
     */
    private static function remember(string $key, \Closure $resolver, mixed $fallback): mixed
    {
        try {
            if (!self::cacheEnabled()) {
                return $resolver();
            }

            $ttlSeconds = max(0, (int) config('features.shared_ui.cache_ttl_seconds', 300));
            if ($ttlSeconds <= 0) {
                return $resolver();
            }

            return Cache::remember($key, now()->addSeconds($ttlSeconds), $resolver);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function cacheEnabled(): bool
    {
        return (bool) config('features.shared_ui.cache_enabled', false);
    }
}
