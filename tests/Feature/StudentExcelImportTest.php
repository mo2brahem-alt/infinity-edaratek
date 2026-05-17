<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class StudentExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_download_student_import_template(): void
    {
        $this->skipIfZipArchiveIsMissing();

        [$manager] = $this->createManagerWithSchool('SCH-846001');

        $response = $this->actingAs($manager)
            ->get(route('school.student_structure.students.import_template'));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_valid_excel_import_creates_students_inside_current_school(): void
    {
        $this->skipIfZipArchiveIsMissing();

        [$manager, $school] = $this->createManagerWithSchool('SCH-846002');
        [$stage, $classroom] = $this->createSchoolStructure($school, 'Primary', 'Grade 1', 'A');

        $file = $this->xlsx([
            ['اسم الطالب', 'رقم الطالب', 'رقم الهوية / الإقامة', 'المرحلة', 'الصف', 'الفصل', 'حالة الطالب', 'ملاحظات'],
            ['سارة أحمد', '', '1090000001', $stage->name, $classroom->grade_name, $classroom->name, 'نشط', ''],
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.students.import'), [
                'students_file' => $file,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_students', [
            'school_id' => $school->id,
            'school_classroom_id' => $classroom->id,
            'full_name' => 'سارة أحمد',
            'national_id' => '1090000001',
            'student_code' => 'STU-001',
            'is_active' => true,
        ]);
    }

    public function test_excel_import_rejects_classroom_from_another_school(): void
    {
        $this->skipIfZipArchiveIsMissing();

        [$manager, $school] = $this->createManagerWithSchool('SCH-846003');
        [, $otherSchool] = $this->createManagerWithSchool('SCH-846004');
        $this->createSchoolStructure($school, 'Primary A', 'Grade 1', 'A');
        [$foreignStage, $foreignClassroom] = $this->createSchoolStructure($otherSchool, 'Primary B', 'Grade 1', 'B');

        $file = $this->xlsx([
            ['اسم الطالب', 'رقم الطالب', 'رقم الهوية / الإقامة', 'المرحلة', 'الصف', 'الفصل', 'حالة الطالب', 'ملاحظات'],
            ['طالب خارج النطاق', 'OUT-1', '2090000001', $foreignStage->name, $foreignClassroom->grade_name, $foreignClassroom->name, 'نشط', ''],
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.students.import'), [
                'students_file' => $file,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('students_file');

        $this->assertDatabaseMissing('school_students', [
            'school_id' => $school->id,
            'full_name' => 'طالب خارج النطاق',
        ]);
    }

    public function test_staff_without_student_structure_permission_cannot_import_students(): void
    {
        $this->skipIfZipArchiveIsMissing();

        [, $school] = $this->createManagerWithSchool('SCH-846005');
        $staff = $this->createStaffWithoutStructurePermission($school);

        $this->actingAs($staff)
            ->get(route('school.student_structure.students.import_template'))
            ->assertForbidden();
    }

    private function skipIfZipArchiveIsMissing(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is required for xlsx import tests.');
        }
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerWithSchool(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create([
            'role' => 'school_manager',
            'is_active' => true,
        ]);
        $manager->assignRole('school_manager');

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => '05' . substr(preg_replace('/\D+/', '', $schoolCode), -8),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager->fresh(), $school];
    }

    /**
     * @return array{0: SchoolStage, 1: SchoolClassroom}
     */
    private function createSchoolStructure(School $school, string $stageName, string $gradeName, string $classroomName): array
    {
        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => $stageName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolStageGrade::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => $gradeName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => $gradeName,
            'name' => $classroomName,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$stage, $classroom];
    }

    private function createStaffWithoutStructurePermission(School $school): User
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $department = Department::query()->create([
            'name' => 'Administrative Affairs',
            'staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'school_id' => null,
        ]);

        $departmentRole = DepartmentRole::query()->create([
            'department_id' => $department->id,
            'name' => 'Administrative Employee',
            'is_active' => true,
            'can_manage_student_structure' => false,
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department_role_id' => $departmentRole->id,
            'school_staff_type' => Department::STAFF_TYPE_ADMINISTRATIVE,
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        return $staff;
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'students-import-test-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="قالب الطلاب" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();

        return new UploadedFile(
            $path,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $xml .= '<c r="' . $this->columnName($columnIndex + 1) . $excelRow . '" t="inlineStr"><is><t>'
                    . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
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
}
