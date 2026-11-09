<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

final class NativeCollection
{
    public static function envelope(LengthAwarePaginator $p, array $data, string $path, array $filters = []): array
    {
        $link = static fn (int $page): string => $path.'?'.http_build_query(array_merge($filters, ['per_page' => $p->perPage(), 'page' => $page]));
        return ['data' => array_values($data),
            'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(),
                'last_page' => $p->lastPage(), 'from' => $p->firstItem(), 'to' => $p->lastItem()],
            'links' => ['first' => $link(1), 'last' => $link($p->lastPage()), 'prev' => $p->currentPage() > 1 ? $link($p->currentPage() - 1) : null,
                'next' => $p->hasMorePages() ? $link($p->currentPage() + 1) : null]];
    }
}
