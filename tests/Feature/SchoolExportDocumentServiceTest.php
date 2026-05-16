<?php

namespace Tests\Feature;

use App\Models\School;
use App\Services\Exports\SchoolExportDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolExportDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_preamble_uses_utf8_bom_and_arabic_school_metadata(): void
    {
        $school = School::query()->create([
            'name' => 'مدرسة البيان',
            'school_id' => 'SCH-UTF-001',
        ]);

        $stream = fopen('php://temp', 'w+');
        $this->assertIsResource($stream);

        app(SchoolExportDocumentService::class)->writeCsvPreamble(
            $stream,
            $school,
            'تقرير الحضور',
            null,
            ['الفترة' => '2026-05-01 إلى 2026-05-16']
        );

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        $this->assertIsString($content);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('مدرسة البيان', $content);
        $this->assertStringContainsString('تقرير الحضور', $content);
        $this->assertStringContainsString('الفترة', $content);
    }

    public function test_safe_filename_uses_school_code_and_timestamp_safe_segments(): void
    {
        $school = School::query()->create([
            'name' => 'مدرسة البيان',
            'school_id' => 'SCH-UTF-001',
        ]);

        $filename = app(SchoolExportDocumentService::class)->safeFileName(
            'attendance-report',
            $school,
            'csv',
            ['الصف الأول']
        );

        $this->assertStringStartsWith('attendance-report-sch-utf-001-', $filename);
        $this->assertStringEndsWith('.csv', $filename);
        $this->assertDoesNotMatchRegularExpression('/[\\\\\\/]/', $filename);
    }
}
