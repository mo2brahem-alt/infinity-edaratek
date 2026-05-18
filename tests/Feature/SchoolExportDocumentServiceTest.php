<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Services\Exports\SchoolExportDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolExportDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_preamble_uses_utf8_bom_and_arabic_school_metadata(): void
    {
        $school = $this->makeExportSchool();

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
        $school = $this->makeExportSchool();

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

    public function test_word_headers_use_utf8_and_attachment_download(): void
    {
        $headers = app(SchoolExportDocumentService::class)->wordHeaders('attendance-report.doc');

        $this->assertSame('application/msword; charset=UTF-8', $headers['Content-Type']);
        $this->assertStringContainsString('attendance-report.doc', $headers['Content-Disposition']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function test_report_dataset_view_declares_utf8_rtl_and_arabic_content(): void
    {
        $school = $this->makeExportSchool();

        $html = view('exports.school.report-datasets', [
            'school' => $school,
            'schoolLogoImage' => null,
            'documentTitle' => 'تقرير حضور الطلاب',
            'documentSubtitle' => 'تقرير رسمي',
            'generatedAt' => now(),
            'exportedBy' => null,
            'details' => ['الفصل' => 'الأول'],
            'datasets' => [[
                'title' => 'تفاصيل الحضور',
                'columns' => [['key' => 'student_name', 'label' => 'اسم الطالب']],
                'rows' => [['student_name' => 'أحمد']],
                'total' => 1,
            ]],
        ])->render();

        $this->assertStringContainsString('charset="utf-8"', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('مدرسة البيان', $html);
        $this->assertStringContainsString('اسم الطالب', $html);
        $this->assertStringContainsString('تم إنشاء هذا المستند بواسطة منصة إدارتك في', $html);
    }

    private function makeExportSchool(): School
    {
        $directorate = EducationalDirectorate::query()->create([
            'name' => 'Export Test Directorate',
            'governorate' => 'Riyadh',
        ]);

        return School::query()->create([
            'directorate_id' => $directorate->id,
            'name' => 'مدرسة البيان',
            'school_id' => 'SCH-UTF-001',
        ]);
    }
}
