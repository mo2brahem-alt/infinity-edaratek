<?php

namespace App\Services\Certificates;

use App\Models\CertificateTemplate;
use App\Support\CertificateOptionLibrary;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CertificateTemplateService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $schoolId, int $actorId, array $payload): CertificateTemplate
    {
        return CertificateTemplate::query()->create($this->payload($schoolId, $actorId, $payload, true));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(CertificateTemplate $template, int $schoolId, int $actorId, array $payload): CertificateTemplate
    {
        $this->assertTemplateBelongsToSchool($template, $schoolId);
        $template->forceFill($this->payload($schoolId, $actorId, $payload, false))->save();

        return $template->refresh();
    }

    public function assertTemplateBelongsToSchool(?CertificateTemplate $template, int $schoolId): void
    {
        if (!$template || (int) $template->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'certificate_template_id' => 'لا يمكنك استخدام قالب شهادة خارج نطاق مدرستك.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payload(int $schoolId, int $actorId, array $payload, bool $creating): array
    {
        $type = (string) ($payload['type'] ?? CertificateTemplate::TYPE_APPRECIATION);
        $frameKey = $payload['frame_key'] ?? null;

        if (!in_array($type, CertificateOptionLibrary::typeValues(), true)) {
            throw ValidationException::withMessages(['type' => 'نوع الشهادة غير صالح.']);
        }

        if ($frameKey !== null && $frameKey !== '' && !in_array($frameKey, CertificateOptionLibrary::frameKeys(), true)) {
            throw ValidationException::withMessages(['frame_key' => 'إطار الشهادة غير صالح.']);
        }

        $data = [
            'school_id' => $schoolId,
            'name' => trim((string) ($payload['name'] ?? '')),
            'type' => $type,
            'orientation' => (string) ($payload['orientation'] ?? 'landscape'),
            'paper_size' => (string) ($payload['paper_size'] ?? 'A4'),
            'frame_key' => $frameKey ?: null,
            'layout_json' => $this->arrayOrNull($payload['layout_json'] ?? null),
            'title_text' => trim((string) ($payload['title_text'] ?? CertificateOptionLibrary::labelForType($type))),
            'title_style_json' => $this->stylePayload(Arr::get($payload, 'title_style_json', []), 'Reem Kufi', 34),
            'student_name_style_json' => $this->stylePayload(Arr::get($payload, 'student_name_style_json', []), 'Amiri', 42),
            'body_style_json' => $this->stylePayload(Arr::get($payload, 'body_style_json', []), 'Cairo', 20),
            'date_style_json' => $this->stylePayload(Arr::get($payload, 'date_style_json', []), 'Cairo', 16),
            'signature_style_json' => $this->stylePayload(Arr::get($payload, 'signature_style_json', []), 'Cairo', 16),
            'default_body' => trim((string) ($payload['default_body'] ?? '')) ?: (CertificateOptionLibrary::phrases()[$type] ?? ''),
            'default_gender_mode' => $payload['default_gender_mode'] ?? null,
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ];

        if ($creating) {
            $data['created_by'] = $actorId > 0 ? $actorId : null;
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function stylePayload(mixed $value, string $defaultFont, int $defaultSize): array
    {
        $style = is_array($value) ? $value : [];
        $font = trim((string) ($style['font_family'] ?? $defaultFont));
        if (!in_array($font, CertificateOptionLibrary::fontValues(), true)) {
            $font = $defaultFont;
        }

        return [
            'font_family' => $font,
            'font_size' => max(10, min(72, (int) ($style['font_size'] ?? $defaultSize))),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($style['color'] ?? ''))
                ? (string) $style['color']
                : '#0f172a',
            'font_weight' => in_array((string) ($style['font_weight'] ?? ''), ['400', '500', '600', '700', '800', '900'], true)
                ? (string) $style['font_weight']
                : '700',
        ];
    }
}
