<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_default_stage_templates', function (Blueprint $table): void {
            $table->foreignId('education_stage_id')
                ->nullable()
                ->after('directorate_id')
                ->constrained('education_stages')
                ->nullOnDelete();
        });

        $stageIdByCanonicalName = DB::table('education_stages')
            ->select(['id', 'name'])
            ->get()
            ->mapWithKeys(function ($stage): array {
                $canonicalName = $this->canonicalizeStageName($stage->name);

                return $canonicalName !== ''
                    ? [$canonicalName => (int) $stage->id]
                    : [];
            })
            ->all();

        DB::table('school_default_stage_templates')
            ->select(['id', 'name'])
            ->whereNull('education_stage_id')
            ->orderBy('id')
            ->get()
            ->each(function ($templateStage) use ($stageIdByCanonicalName): void {
                $canonicalName = $this->canonicalizeStageName($templateStage->name);

                if ($canonicalName === '') {
                    return;
                }

                $educationStageId = $stageIdByCanonicalName[$canonicalName] ?? null;

                if ($educationStageId === null) {
                    return;
                }

                DB::table('school_default_stage_templates')
                    ->where('id', (int) $templateStage->id)
                    ->update(['education_stage_id' => $educationStageId]);
            });
    }

    public function down(): void
    {
        Schema::table('school_default_stage_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('education_stage_id');
        });
    }

    private function canonicalizeStageName(?string $value): string
    {
        $normalized = $this->normalizeArabic($value);

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, 'رياضالاطفال') || str_contains($normalized, 'روض')) {
            return 'kindergarten';
        }

        if (str_contains($normalized, 'ابتد')) {
            return 'primary';
        }

        if (str_contains($normalized, 'متوسط')) {
            return 'middle';
        }

        if (str_contains($normalized, 'ثانو')) {
            return 'secondary';
        }

        $stripped = str_replace(
            ['المرحله', 'مرحله', 'التعليم', 'تعليم', 'المدرسه', 'مدرسه', 'المدارس', 'مدارس'],
            '',
            $normalized
        );

        return $stripped !== '' ? $stripped : $normalized;
    }

    private function normalizeArabic(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ئ', 'ؤ', 'ة', 'ـ'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ي', 'و', 'ه', ''],
            $normalized
        );

        return preg_replace('/[^\p{Arabic}\p{N}]+/u', '', $normalized) ?? '';
    }
};
