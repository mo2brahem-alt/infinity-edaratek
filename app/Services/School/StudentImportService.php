<?php

namespace App\Services\School;

use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class StudentImportService
{
    private const MAX_FILE_BYTES = 2_097_152;
    private const MAX_ROWS = 500;

    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = [
        'اسم الطالب',
        'رقم الطالب',
        'رقم الهوية / الإقامة',
        'المرحلة',
        'الصف',
        'الفصل',
        'حالة الطالب',
        'ملاحظات',
    ];

    public function templateResponse(): BinaryFileResponse
    {
        $path = $this->buildTemplateFile();

        return response()
            ->download(
                $path,
                'students-import-template-' . now()->format('Y-m-d') . '.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )
            ->deleteFileAfterSend(true);
    }

    /**
     * @return array{
     *     ok: bool,
     *     summary: array{total_rows: int, valid_rows: int, failed_rows: int, imported_rows: int},
     *     errors: array<int, string>
     * }
     */
    public function import(UploadedFile $file, int $schoolId): array
    {
        $earlyError = $this->validateUploadedFile($file);
        if ($earlyError !== null) {
            return $this->failedResult([$earlyError]);
        }

        try {
            $rows = $this->readFirstWorksheet($file->getRealPath());
        } catch (RuntimeException $exception) {
            return $this->failedResult([$exception->getMessage()]);
        }

        if ($rows === []) {
            return $this->failedResult(['ملف Excel لا يحتوي على أي بيانات قابلة للقراءة.']);
        }

        $headerRow = $rows[array_key_first($rows)] ?? [];
        $headers = $this->resolveHeaders($headerRow);
        $missingHeaders = $this->missingHeaders($headers);

        if ($missingHeaders !== []) {
            return $this->failedResult([
                'الأعمدة المطلوبة غير مكتملة: ' . implode('، ', $missingHeaders) . '. يرجى تحميل القالب من النظام دون تغيير أسماء الأعمدة.',
            ]);
        }

        $reference = $this->schoolReferenceData($schoolId);
        $errors = [];
        $validRows = [];
        $seenCodes = [];
        $seenNationalIds = [];
        $totalRows = 0;

        foreach ($rows as $rowNumber => $row) {
            if ((int) $rowNumber === (int) array_key_first($rows)) {
                continue;
            }

            if ($this->isBlankRow($row)) {
                continue;
            }

            $totalRows++;

            if ($totalRows > self::MAX_ROWS) {
                $errors[] = 'الملف يتجاوز الحد الأقصى المسموح به وهو ' . self::MAX_ROWS . ' صفًا في عملية استيراد واحدة.';
                break;
            }

            $rowErrors = [];
            $fullName = $this->cell($row, $headers['full_name']);
            $studentCode = $this->cell($row, $headers['student_code'] ?? null);
            $nationalId = $this->cell($row, $headers['national_id'] ?? null);
            $stageName = $this->cell($row, $headers['stage_name']);
            $gradeName = $this->cell($row, $headers['grade_name']);
            $classroomName = $this->cell($row, $headers['classroom_name']);
            $statusLabel = $this->cell($row, $headers['is_active'] ?? null);

            if ($fullName === '') {
                $rowErrors[] = 'اسم الطالب مطلوب.';
            }

            if (mb_strlen($fullName) > 255) {
                $rowErrors[] = 'اسم الطالب يجب ألا يتجاوز 255 حرفًا.';
            }

            if (mb_strlen($studentCode) > 50) {
                $rowErrors[] = 'رقم الطالب يجب ألا يتجاوز 50 حرفًا.';
            }

            if (mb_strlen($nationalId) > 50) {
                $rowErrors[] = 'رقم الهوية / الإقامة يجب ألا يتجاوز 50 حرفًا.';
            }

            $stageKey = $this->lookupKey($stageName);
            $stage = $reference['stages'][$stageKey] ?? null;
            if ($stageName === '') {
                $rowErrors[] = 'اسم المرحلة مطلوب.';
            } elseif ($stage === null) {
                $rowErrors[] = 'المرحلة غير موجودة داخل هذه المدرسة.';
            }

            $gradeKey = $this->lookupKey($gradeName);
            if ($gradeName === '') {
                $rowErrors[] = 'اسم الصف مطلوب.';
            } elseif ($stage !== null && !isset($reference['grades'][$stage->id][$gradeKey])) {
                $rowErrors[] = 'الصف غير موجود داخل المرحلة المحددة في هذه المدرسة.';
            }

            $classroomKey = $this->lookupKey($classroomName);
            $classroom = null;
            if ($classroomName === '') {
                $rowErrors[] = 'اسم الفصل مطلوب.';
            } elseif ($stage !== null && $gradeName !== '') {
                $classroom = $reference['classrooms'][$stage->id][$gradeKey][$classroomKey] ?? null;
                if ($classroom === null) {
                    $rowErrors[] = 'الفصل غير موجود داخل الصف والمرحلة المحددين في هذه المدرسة.';
                }
            }

            if ($studentCode !== '') {
                $codeKey = $this->lookupKey($studentCode);
                if (isset($seenCodes[$codeKey])) {
                    $rowErrors[] = 'رقم الطالب مكرر داخل الملف.';
                } elseif (isset($reference['student_codes'][$codeKey])) {
                    $rowErrors[] = 'رقم الطالب موجود مسبقًا في المدرسة.';
                }
                $seenCodes[$codeKey] = true;
            }

            if ($nationalId !== '') {
                $nationalIdKey = $this->lookupKey($nationalId);
                if (isset($seenNationalIds[$nationalIdKey])) {
                    $rowErrors[] = 'رقم الهوية / الإقامة مكرر داخل الملف.';
                } elseif (isset($reference['national_ids'][$nationalIdKey])) {
                    $rowErrors[] = 'رقم الهوية / الإقامة موجود مسبقًا في المدرسة.';
                }
                $seenNationalIds[$nationalIdKey] = true;
            }

            $isActive = $this->parseStatus($statusLabel);
            if ($isActive === null) {
                $rowErrors[] = 'حالة الطالب يجب أن تكون "نشط" أو "غير نشط" عند تعبئتها.';
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $rowError) {
                    $errors[] = 'الصف ' . $rowNumber . ': ' . $rowError;
                }

                continue;
            }

            $validRows[] = [
                'school_id' => $schoolId,
                'school_classroom_id' => (int) $classroom->id,
                'full_name' => $fullName,
                'student_code' => $studentCode,
                'national_id' => $nationalId !== '' ? $nationalId : null,
                'is_active' => $isActive ?? true,
            ];
        }

        if ($totalRows === 0) {
            return $this->failedResult(['لا توجد صفوف طلاب بعد صف العناوين في ملف Excel.']);
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'summary' => [
                    'total_rows' => $totalRows,
                    'valid_rows' => count($validRows),
                    'failed_rows' => $totalRows - count($validRows),
                    'imported_rows' => 0,
                ],
                'errors' => $errors,
            ];
        }

        $importedRows = $this->persistRows($schoolId, $validRows);

        return [
            'ok' => true,
            'summary' => [
                'total_rows' => $totalRows,
                'valid_rows' => count($validRows),
                'failed_rows' => 0,
                'imported_rows' => $importedRows,
            ],
            'errors' => [],
        ];
    }

    private function validateUploadedFile(UploadedFile $file): ?string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        if ($extension !== 'xlsx') {
            return 'يرجى رفع ملف Excel بصيغة xlsx فقط.';
        }

        if ((int) $file->getSize() > self::MAX_FILE_BYTES) {
            return 'حجم ملف Excel أكبر من الحد المسموح به وهو 2 ميجابايت.';
        }

        if (!class_exists(ZipArchive::class)) {
            return 'خادم PHP لا يدعم ZipArchive المطلوب لقراءة ملفات Excel. يرجى تفعيل إضافة php-zip.';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function persistRows(int $schoolId, array $rows): int
    {
        return DB::transaction(function () use ($schoolId, $rows): int {
            DB::table('schools')->whereKey($schoolId)->lockForUpdate()->value('id');

            $usedCodes = SchoolStudent::query()
                ->where('school_id', $schoolId)
                ->whereNotNull('student_code')
                ->lockForUpdate()
                ->pluck('student_code')
                ->map(fn ($value): string => (string) $value)
                ->all();

            $nextCode = $this->nextGeneratedCodeNumber($usedCodes);
            $usedCodeLookup = [];
            foreach ($usedCodes as $code) {
                $usedCodeLookup[$this->lookupKey($code)] = true;
            }

            $now = now();
            $insertRows = [];

            foreach ($rows as $row) {
                $studentCode = (string) ($row['student_code'] ?? '');
                if ($studentCode === '') {
                    do {
                        $studentCode = sprintf('STU-%03d', $nextCode);
                        $nextCode++;
                    } while (isset($usedCodeLookup[$this->lookupKey($studentCode)]));
                }

                $usedCodeLookup[$this->lookupKey($studentCode)] = true;

                $insertRows[] = [
                    'school_id' => $schoolId,
                    'school_classroom_id' => (int) $row['school_classroom_id'],
                    'full_name' => (string) $row['full_name'],
                    'student_code' => $studentCode,
                    'national_id' => $row['national_id'] !== '' ? $row['national_id'] : null,
                    'is_active' => (bool) $row['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($insertRows !== []) {
                SchoolStudent::query()->insert($insertRows);
            }

            return count($insertRows);
        });
    }

    /**
     * @return array{
     *     stages: array<string, SchoolStage>,
     *     grades: array<int, array<string, bool>>,
     *     classrooms: array<int, array<string, array<string, SchoolClassroom>>>,
     *     student_codes: array<string, bool>,
     *     national_ids: array<string, bool>
     * }
     */
    private function schoolReferenceData(int $schoolId): array
    {
        $stages = [];
        foreach (SchoolStage::query()->where('school_id', $schoolId)->get(['id', 'name', 'is_active']) as $stage) {
            $stages[$this->lookupKey($stage->name)] = $stage;
        }

        $grades = [];
        foreach (SchoolStageGrade::query()->where('school_id', $schoolId)->get(['id', 'school_stage_id', 'name']) as $grade) {
            $grades[(int) $grade->school_stage_id][$this->lookupKey($grade->name)] = true;
        }

        $classrooms = [];
        foreach (SchoolClassroom::query()->where('school_id', $schoolId)->get(['id', 'school_stage_id', 'grade_name', 'name', 'is_active']) as $classroom) {
            $stageId = (int) $classroom->school_stage_id;
            $gradeKey = $this->lookupKey($classroom->grade_name);
            $classroomKey = $this->lookupKey($classroom->name);

            $grades[$stageId][$gradeKey] = true;
            $classrooms[$stageId][$gradeKey][$classroomKey] = $classroom;
        }

        $studentCodes = [];
        $nationalIds = [];

        SchoolStudent::query()
            ->where('school_id', $schoolId)
            ->get(['student_code', 'national_id'])
            ->each(function (SchoolStudent $student) use (&$studentCodes, &$nationalIds): void {
                if ($student->student_code !== null && trim((string) $student->student_code) !== '') {
                    $studentCodes[$this->lookupKey($student->student_code)] = true;
                }

                if ($student->national_id !== null && trim((string) $student->national_id) !== '') {
                    $nationalIds[$this->lookupKey($student->national_id)] = true;
                }
            });

        return [
            'stages' => $stages,
            'grades' => $grades,
            'classrooms' => $classrooms,
            'student_codes' => $studentCodes,
            'national_ids' => $nationalIds,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readFirstWorksheet(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('خادم PHP لا يدعم ZipArchive المطلوب لقراءة ملفات Excel. يرجى تفعيل إضافة php-zip.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('تعذر فتح ملف Excel. تأكد أن الملف بصيغة xlsx سليمة.');
        }

        try {
            $worksheetPath = $this->firstWorksheetPath($zip);
            $worksheetXml = $zip->getFromName($worksheetPath);
            if ($worksheetXml === false) {
                throw new RuntimeException('تعذر قراءة ورقة العمل الأولى داخل ملف Excel.');
            }

            $sharedStrings = $this->readSharedStrings($zip);

            return $this->parseWorksheetXml($worksheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new RuntimeException('ملف Excel لا يحتوي على علاقات المصنف المطلوبة.');
        }

        $rels = $this->xml($relsXml, 'تعذر قراءة علاقات المصنف داخل ملف Excel.');
        $relationships = $rels->xpath('//*[local-name()="Relationship"]') ?: [];

        foreach ($relationships as $relationship) {
            $type = (string) ($relationship['Type'] ?? '');
            if (!str_ends_with($type, '/worksheet')) {
                continue;
            }

            $target = (string) ($relationship['Target'] ?? '');
            if ($target === '') {
                continue;
            }

            if (str_starts_with($target, '/')) {
                return ltrim($target, '/');
            }

            return 'xl/' . ltrim($target, '/');
        }

        throw new RuntimeException('ملف Excel لا يحتوي على ورقة عمل قابلة للقراءة.');
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            return [];
        }

        $xml = $this->xml($sharedXml, 'تعذر قراءة النصوص المشتركة داخل ملف Excel.');
        $strings = [];

        foreach (($xml->xpath('//*[local-name()="si"]') ?: []) as $item) {
            $strings[] = $this->textFromXmlNode($item);
        }

        return $strings;
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseWorksheetXml(string $worksheetXml, array $sharedStrings): array
    {
        $xml = $this->xml($worksheetXml, 'تعذر قراءة بيانات ورقة العمل داخل ملف Excel.');
        $rows = [];

        foreach (($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: []) as $rowNode) {
            $rowNumber = (int) ($rowNode['r'] ?? (count($rows) + 1));
            $row = [];

            foreach (($rowNode->xpath('./*[local-name()="c"]') ?: []) as $cellNode) {
                $cellRef = (string) ($cellNode['r'] ?? '');
                $columnIndex = $this->columnIndexFromCellRef($cellRef);
                if ($columnIndex <= 0) {
                    $columnIndex = count($row) + 1;
                }

                $row[$columnIndex] = $this->cellValueFromXmlNode($cellNode, $sharedStrings);
            }

            $rows[$rowNumber] = $row;
        }

        ksort($rows);

        return $rows;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellValueFromXmlNode(SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $type = (string) ($cellNode['t'] ?? '');

        if ($type === 'inlineStr') {
            return $this->cleanCellValue($this->textFromXmlNode($cellNode));
        }

        $valueNodes = $cellNode->xpath('./*[local-name()="v"]') ?: [];
        $rawValue = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';

        if ($type === 's') {
            return $this->cleanCellValue($sharedStrings[(int) $rawValue] ?? '');
        }

        return $this->cleanCellValue($rawValue);
    }

    private function textFromXmlNode(SimpleXMLElement $node): string
    {
        $parts = [];
        foreach (($node->xpath('.//*[local-name()="t"]') ?: []) as $textNode) {
            $parts[] = (string) $textNode;
        }

        return implode('', $parts);
    }

    private function xml(string $xml, string $errorMessage): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$parsed instanceof SimpleXMLElement) {
            throw new RuntimeException($errorMessage);
        }

        return $parsed;
    }

    /**
     * @param array<int, string> $headerRow
     * @return array<string, int>
     */
    private function resolveHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $columnIndex => $label) {
            $key = $this->lookupKey($label);

            if (in_array($key, ['اسم الطالب', 'الطالب', 'اسم'], true)) {
                $headers['full_name'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['رقم الطالب', 'كود الطالب', 'student code', 'student_code'], true)) {
                $headers['student_code'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['رقم الهوية / الاقامة', 'رقم الهوية', 'رقم الاقامة', 'رقم الإقامة', 'national id', 'national_id'], true)) {
                $headers['national_id'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['المرحلة', 'اسم المرحلة'], true)) {
                $headers['stage_name'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['الصف', 'اسم الصف'], true)) {
                $headers['grade_name'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['الفصل', 'اسم الفصل', 'الشعبة', 'الشعبه'], true)) {
                $headers['classroom_name'] = (int) $columnIndex;
                continue;
            }

            if (in_array($key, ['حالة الطالب', 'الحالة', 'نشط'], true)) {
                $headers['is_active'] = (int) $columnIndex;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, int> $headers
     * @return array<int, string>
     */
    private function missingHeaders(array $headers): array
    {
        $required = [
            'full_name' => 'اسم الطالب',
            'stage_name' => 'المرحلة',
            'grade_name' => 'الصف',
            'classroom_name' => 'الفصل',
        ];

        $missing = [];
        foreach ($required as $key => $label) {
            if (!array_key_exists($key, $headers)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @param array<int, string> $row
     */
    private function cell(array $row, ?int $columnIndex): string
    {
        if ($columnIndex === null) {
            return '';
        }

        return $this->cleanCellValue($row[$columnIndex] ?? '');
    }

    /**
     * @param array<int, string> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanCellValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseStatus(string $value): ?bool
    {
        $key = $this->lookupKey($value);

        if ($key === '') {
            return true;
        }

        if (in_array($key, ['نشط', 'فعال', 'active', 'yes', 'نعم', '1'], true)) {
            return true;
        }

        if (in_array($key, ['غير نشط', 'غير فعال', 'inactive', 'no', 'لا', '0'], true)) {
            return false;
        }

        return null;
    }

    private function cleanCellValue(mixed $value): string
    {
        $value = str_replace("\u{FEFF}", '', (string) ($value ?? ''));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function lookupKey(mixed $value): string
    {
        $value = $this->cleanCellValue($value);
        $value = str_replace(['ـ', 'إ', 'أ', 'آ'], ['', 'ا', 'ا', 'ا'], $value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * @param array<int, string> $usedCodes
     */
    private function nextGeneratedCodeNumber(array $usedCodes): int
    {
        $max = 0;
        foreach ($usedCodes as $code) {
            if (preg_match('/^STU-(\d+)$/', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        if (preg_match('/^([A-Z]+)/i', $cellRef, $matches) !== 1) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }

    /**
     * @param array<int, string> $errors
     * @return array{
     *     ok: bool,
     *     summary: array{total_rows: int, valid_rows: int, failed_rows: int, imported_rows: int},
     *     errors: array<int, string>
     * }
     */
    private function failedResult(array $errors): array
    {
        return [
            'ok' => false,
            'summary' => [
                'total_rows' => 0,
                'valid_rows' => 0,
                'failed_rows' => 0,
                'imported_rows' => 0,
            ],
            'errors' => $errors,
        ];
    }

    private function buildTemplateFile(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('خادم PHP لا يدعم ZipArchive المطلوب لإنشاء قالب Excel. يرجى تفعيل إضافة php-zip.');
        }

        $path = tempnam(sys_get_temp_dir(), 'students-template-');
        if ($path === false) {
            throw new RuntimeException('تعذر إنشاء ملف مؤقت لقالب الطلاب.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف Excel لقالب الطلاب.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml([
            self::TEMPLATE_HEADERS,
        ]));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml([
            ['تعليمات'],
            ['لا تغير أسماء الأعمدة في الصف الأول.'],
            ['لا تضف school_id أو أي معرف مدرسة داخل الملف؛ النظام يربط الطلاب بمدرستك الحالية تلقائيًا.'],
            ['اكتب أسماء المرحلة والصف والفصل كما هي موجودة في الهيكل الطلابي داخل مدرستك.'],
            ['رقم الطالب اختياري، وإذا تركته فارغًا سيولده النظام تلقائيًا.'],
            ['القيم المقبولة لحالة الطالب: نشط، غير نشط.'],
            ['مثال: أحمد محمد العتيبي | 1090000001 | المرحلة الابتدائية | الأول الابتدائي | الأول الابتدائي - شعبة أ | نشط'],
        ]));

        $zip->close();

        return $path;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
        $xml .= '<cols>';
        for ($i = 1; $i <= 8; $i++) {
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="24" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $excelColumn = $this->columnName($columnIndex + 1);
                $style = $excelRow === 1 ? ' s="1"' : ' s="2"';
                $xml .= '<c r="' . $excelColumn . $excelRow . '" t="inlineStr"' . $style . '><is><t>' . $this->xmlEscape($value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView rightToLeft="1"/></bookViews>'
            . '<sheets>'
            . '<sheet name="قالب الطلاب" sheetId="1" r:id="rId1"/>'
            . '<sheet name="التعليمات" sheetId="2" r:id="rId2"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" readingOrder="2"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" readingOrder="2"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Edaratek</Application>'
            . '</Properties>';
    }

    private function coreXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>قالب استيراد الطلاب</dc:title>'
            . '<dc:creator>Edaratek</dc:creator>'
            . '<cp:lastModifiedBy>Edaratek</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . now()->toAtomString() . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}
