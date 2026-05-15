<?php

namespace App\Services\School;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SchoolDefaultTemplateScopeRegistry
{
    private const SETTING_KEY = 'school_default_template_scopes';
    private const SETTING_GROUP = 'school_defaults';

    public function all(): Collection
    {
        return $this->entries()
            ->groupBy(fn (array $entry) => sprintf('%d:%d', (int) $entry['country_id'], (int) $entry['education_type_id']))
            ->map(function (Collection $entries): array {
                $preferred = $entries->first(fn (array $entry) => $entry['directorate_id'] === null)
                    ?? $entries
                        ->sortByDesc(fn (array $entry) => strtotime((string) ($entry['updated_at'] ?? '')) ?: 0)
                        ->first();

                return [
                    ...$preferred,
                    'directorate_id' => null,
                ];
            })
            ->values();
    }

    public function findByDirectorate(?int $directorateId): ?array
    {
        if ($directorateId === null) {
            return null;
        }

        return $this->entries()
            ->first(fn (array $entry) => (int) $entry['directorate_id'] === $directorateId);
    }

    public function find(?int $countryId, ?int $educationTypeId, ?int $directorateId = null): ?array
    {
        if ($countryId !== null && $educationTypeId !== null) {
            return $this->all()
                ->first(fn (array $entry) => (int) $entry['country_id'] === $countryId && (int) $entry['education_type_id'] === $educationTypeId);
        }

        return $this->findByDirectorate($directorateId);
    }

    /**
     * @param  array<string, mixed>  $referenceSnapshot
     * @return array<string, mixed>
     */
    public function upsert(
        string $templateName,
        int $countryId,
        int $educationTypeId,
        ?int $directorateId = null,
        array $referenceSnapshot = []
    ): array {
        $payload = [
            'template_name' => trim($templateName),
            'country_id' => $countryId,
            'education_type_id' => $educationTypeId,
            'directorate_id' => null,
            'reference_snapshot' => $this->normalizeReferenceSnapshot($referenceSnapshot),
            'updated_at' => now()->toISOString(),
        ];

        $entries = $this->entries()
            ->reject(fn (array $entry) => (int) $entry['country_id'] === $countryId && (int) $entry['education_type_id'] === $educationTypeId)
            ->push($payload)
            ->values()
            ->all();

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'group' => self::SETTING_GROUP,
                'type' => 'json',
                'value' => json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        return $payload;
    }

    public function delete(int $countryId, int $educationTypeId): void
    {
        $remainingEntries = $this->entries()
            ->reject(
                fn (array $entry) => (int) $entry['country_id'] === $countryId
                    && (int) $entry['education_type_id'] === $educationTypeId
            )
            ->values()
            ->all();

        if ($remainingEntries === []) {
            Setting::query()
                ->where('key', self::SETTING_KEY)
                ->delete();

            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'group' => self::SETTING_GROUP,
                'type' => 'json',
                'value' => json_encode($remainingEntries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public function appliesToDirectorate(?int $directorateId, ?int $countryId, ?int $educationTypeId): bool
    {
        if ($countryId === null || $educationTypeId === null) {
            return true;
        }

        $entry = $this->find($countryId, $educationTypeId, $directorateId);

        if ($entry === null) {
            return true;
        }

        if ($countryId !== null && (int) $entry['country_id'] !== $countryId) {
            return false;
        }

        if ($educationTypeId !== null && (int) $entry['education_type_id'] !== $educationTypeId) {
            return false;
        }

        return true;
    }

    private function entries(): Collection
    {
        $raw = Setting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        if (!is_string($raw) || trim($raw) === '') {
            return collect();
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->filter(fn ($entry) => is_array($entry))
            ->flatMap(fn (array $entry) => $this->normalizeEntry($entry))
            ->filter(fn (array $entry) => $entry['template_name'] !== '' && $entry['country_id'] && $entry['education_type_id'])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeEntry(array $entry): Collection
    {
        $directorateIds = collect($entry['directorate_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $singleDirectorateId = isset($entry['directorate_id']) ? (int) $entry['directorate_id'] : null;
        if ($singleDirectorateId !== null && $singleDirectorateId > 0) {
            $directorateIds = collect([$singleDirectorateId]);
        }

        if ($directorateIds->isEmpty()) {
            $directorateIds = collect([null]);
        }

        return $directorateIds->map(function (?int $directorateId) use ($entry): array {
            return [
                'template_name' => trim((string) ($entry['template_name'] ?? '')),
                'country_id' => isset($entry['country_id']) ? (int) $entry['country_id'] : null,
                'education_type_id' => isset($entry['education_type_id']) ? (int) $entry['education_type_id'] : null,
                'directorate_id' => $directorateId,
                'reference_snapshot' => $this->normalizeReferenceSnapshot($entry['reference_snapshot'] ?? []),
                'updated_at' => $entry['updated_at'] ?? null,
            ];
        });
    }

    /**
     * @param  mixed  $snapshot
     * @return array<string, mixed>
     */
    public function normalizeReferenceSnapshot(mixed $snapshot): array
    {
        if (!is_array($snapshot)) {
            return [];
        }

        return [
            'status' => (string) ($snapshot['status'] ?? ''),
            'year' => isset($snapshot['year']) ? (int) $snapshot['year'] : null,
            'requested_data' => collect($snapshot['requested_data'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all(),
            'supported_data' => collect($snapshot['supported_data'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all(),
            'unavailable_data' => collect($snapshot['unavailable_data'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all(),
            'available_counts' => collect($snapshot['available_counts'] ?? [])
                ->mapWithKeys(fn ($value, $key) => [trim((string) $key) => max(0, (int) $value)])
                ->filter(fn ($value, $key) => $key !== '')
                ->all(),
            'holidays' => collect($snapshot['holidays'] ?? [])
                ->filter(fn ($holiday) => is_array($holiday))
                ->map(fn (array $holiday) => [
                    'name' => trim((string) ($holiday['name'] ?? '')),
                    'local_name' => trim((string) ($holiday['local_name'] ?? '')) ?: null,
                    'date' => trim((string) ($holiday['date'] ?? '')) ?: null,
                    'notes' => trim((string) ($holiday['notes'] ?? '')) ?: null,
                    'reference_key' => trim((string) ($holiday['reference_key'] ?? '')) ?: null,
                    'holiday_category' => trim((string) ($holiday['holiday_category'] ?? '')) ?: null,
                    'types' => collect($holiday['types'] ?? [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $holiday) => $holiday['name'] !== '' && ($holiday['date'] !== null || $holiday['reference_key'] !== null))
                ->values()
                ->all(),
            'academic_year' => is_array($snapshot['academic_year'] ?? null)
                ? [
                    'name' => trim((string) ($snapshot['academic_year']['name'] ?? '')),
                    'starts_on' => trim((string) ($snapshot['academic_year']['starts_on'] ?? '')) ?: null,
                    'ends_on' => trim((string) ($snapshot['academic_year']['ends_on'] ?? '')) ?: null,
                    'source' => trim((string) ($snapshot['academic_year']['source'] ?? 'api')) ?: 'api',
                ]
                : null,
            'stage_terms' => collect($snapshot['stage_terms'] ?? [])
                ->filter(fn ($term) => is_array($term))
                ->map(fn (array $term) => [
                    'stage_name' => trim((string) ($term['stage_name'] ?? $term['stage'] ?? '')) ?: null,
                    'name' => trim((string) ($term['name'] ?? $term['label'] ?? '')),
                    'start_date' => trim((string) ($term['start_date'] ?? $term['starts_on'] ?? '')) ?: null,
                    'end_date' => trim((string) ($term['end_date'] ?? $term['ends_on'] ?? '')) ?: null,
                    'sort_order' => max(0, (int) ($term['sort_order'] ?? 0)),
                    'source' => trim((string) ($term['source'] ?? 'api')) ?: 'api',
                ])
                ->filter(fn (array $term) => $term['name'] !== '')
                ->values()
                ->all(),
            'academic_terms' => collect($snapshot['academic_terms'] ?? [])
                ->filter(fn ($term) => is_array($term))
                ->map(fn (array $term) => [
                    'stage_name' => trim((string) ($term['stage_name'] ?? $term['stage'] ?? '')) ?: null,
                    'name' => trim((string) ($term['name'] ?? $term['label'] ?? '')),
                    'start_date' => trim((string) ($term['start_date'] ?? $term['starts_on'] ?? '')) ?: null,
                    'end_date' => trim((string) ($term['end_date'] ?? $term['ends_on'] ?? '')) ?: null,
                    'sort_order' => max(0, (int) ($term['sort_order'] ?? 0)),
                    'source' => trim((string) ($term['source'] ?? 'api')) ?: 'api',
                ])
                ->filter(fn (array $term) => $term['name'] !== '')
                ->values()
                ->all(),
            'message' => trim((string) ($snapshot['message'] ?? '')),
            'fetched_at' => $snapshot['fetched_at'] ?? null,
            'source' => is_array($snapshot['source'] ?? null)
                ? [
                    'key' => trim((string) ($snapshot['source']['key'] ?? '')) ?: null,
                    'label' => trim((string) ($snapshot['source']['label'] ?? '')) ?: null,
                ]
                : null,
            'country' => is_array($snapshot['country'] ?? null)
                ? [
                    'id' => isset($snapshot['country']['id']) ? (int) $snapshot['country']['id'] : null,
                    'name' => trim((string) ($snapshot['country']['name'] ?? '')),
                    'code' => trim((string) ($snapshot['country']['code'] ?? '')) ?: null,
                ]
                : null,
        ];
    }
}
