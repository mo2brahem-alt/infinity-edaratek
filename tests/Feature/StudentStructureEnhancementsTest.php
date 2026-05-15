<?php

namespace Tests\Feature;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolClassroom;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentStructureEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_stage_with_school_day_times_and_auto_generated_code(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835001');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Primary Stage',
                'sort_order' => 1,
                'school_day_start_time' => '07:30',
                'school_day_end_time' => '13:30',
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $stage = SchoolStage::query()
            ->where('school_id', $school->id)
            ->where('name', 'Primary Stage')
            ->firstOrFail();

        $this->assertSame('STG-001', $stage->code);
        $this->assertSame('07:30:00', $stage->school_day_start_time);
        $this->assertSame('13:30:00', $stage->school_day_end_time);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.stage.created',
            'entity_type' => 'school_stage',
            'entity_id' => $stage->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_stage_creation_rejects_invalid_school_day_range(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835002');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Invalid Stage',
                'sort_order' => 1,
                'school_day_start_time' => '14:00',
                'school_day_end_time' => '09:00',
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('school_day_start_time');

        $this->assertDatabaseMissing('school_stages', [
            'school_id' => $school->id,
            'name' => 'Invalid Stage',
        ]);
    }

    public function test_classroom_and_student_codes_are_auto_generated_per_school(): void
    {
        [$managerA, $schoolA] = $this->createManagerWithSchool('SCH-835003');
        [$managerB, $schoolB] = $this->createManagerWithSchool('SCH-835004');

        $stageA = SchoolStage::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'Stage A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Stage B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stageA->id,
                'name' => 'Class A1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerB)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stageB->id,
                'name' => 'Class B1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $classroomA = SchoolClassroom::query()
            ->where('school_id', $schoolA->id)
            ->where('name', 'Class A1')
            ->firstOrFail();
        $classroomB = SchoolClassroom::query()
            ->where('school_id', $schoolB->id)
            ->where('name', 'Class B1')
            ->firstOrFail();

        $this->assertSame('CLS-001', $classroomA->code);
        $this->assertSame('CLS-001', $classroomB->code);

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->post(route('school.student_structure.students.store'), [
                'school_classroom_id' => $classroomA->id,
                'full_name' => 'Student A One',
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $studentA = SchoolStudent::query()
            ->where('school_id', $schoolA->id)
            ->where('full_name', 'Student A One')
            ->firstOrFail();

        $this->assertSame('STU-001', $studentA->student_code);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.classroom.created',
            'entity_type' => 'school_classroom',
            'entity_id' => $classroomA->id,
            'user_id' => $managerA->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.student.created',
            'entity_type' => 'school_student',
            'entity_id' => $studentA->id,
            'user_id' => $managerA->id,
        ]);
    }

    public function test_stage_code_generation_is_sequential_and_scoped_per_school(): void
    {
        [$managerA, $schoolA] = $this->createManagerWithSchool('SCH-835005');
        [$managerB, $schoolB] = $this->createManagerWithSchool('SCH-835006');

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Stage A1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Stage A2',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerB)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Stage B1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolA->id,
            'name' => 'Stage A1',
            'code' => 'STG-001',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolA->id,
            'name' => 'Stage A2',
            'code' => 'STG-002',
        ]);
        $this->assertDatabaseHas('school_stages', [
            'school_id' => $schoolB->id,
            'name' => 'Stage B1',
            'code' => 'STG-001',
        ]);
    }

    public function test_stage_time_update_is_tenant_scoped(): void
    {
        [$managerA] = $this->createManagerWithSchool('SCH-835007');
        [, $schoolB] = $this->createManagerWithSchool('SCH-835008');

        $foreignStage = SchoolStage::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Foreign Stage',
            'code' => 'STG-050',
            'sort_order' => 1,
            'is_active' => true,
            'school_day_start_time' => '07:00:00',
            'school_day_end_time' => '12:00:00',
        ]);

        $this->actingAs($managerA)
            ->put(route('school.student_structure.stages.update', $foreignStage->id), [
                'name' => 'Hijacked Stage',
                'sort_order' => 3,
                'is_active' => true,
                'school_day_start_time' => '08:00',
                'school_day_end_time' => '13:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_stages', [
            'id' => $foreignStage->id,
            'school_id' => $schoolB->id,
            'name' => 'Foreign Stage',
            'school_day_start_time' => '07:00:00',
            'school_day_end_time' => '12:00:00',
        ]);
    }

    public function test_stage_code_must_be_unique_within_same_school(): void
    {
        [$manager] = $this->createManagerWithSchool('SCH-835009');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Stage One',
                'code' => 'STG-CUSTOM-1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.stages.store'), [
                'name' => 'Stage Two',
                'code' => 'STG-CUSTOM-1',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('code');
    }

    public function test_classroom_name_can_repeat_in_same_stage_with_different_grades(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835010');

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stage->id,
                'grade_name' => 'الصف الأول',
                'name' => 'أ',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stage->id,
                'grade_name' => 'الصف الثاني',
                'name' => 'أ',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
        ]);

        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الثاني',
            'name' => 'أ',
        ]);
    }

    public function test_student_create_rejects_stage_or_grade_mismatch_with_selected_classroom(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835011');

        $stageOne = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Stage One',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageTwo = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Stage Two',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $classroom = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stageOne->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.students.store'), [
                'school_stage_id' => $stageTwo->id,
                'classroom_grade_name' => 'الصف الأول',
                'school_classroom_id' => $classroom->id,
                'full_name' => 'Mismatch Stage Student',
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('school_classroom_id');

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.students.store'), [
                'school_stage_id' => $stageOne->id,
                'classroom_grade_name' => 'الصف الثاني',
                'school_classroom_id' => $classroom->id,
                'full_name' => 'Mismatch Grade Student',
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('classroom_grade_name');

        $this->assertDatabaseMissing('school_students', [
            'school_id' => $school->id,
            'full_name' => 'Mismatch Stage Student',
        ]);

        $this->assertDatabaseMissing('school_students', [
            'school_id' => $school->id,
            'full_name' => 'Mismatch Grade Student',
        ]);
    }

    public function test_manager_can_create_stage_grade_and_action_is_tenant_scoped(): void
    {
        [$managerA, $schoolA] = $this->createManagerWithSchool('SCH-835012');
        [$managerB, $schoolB] = $this->createManagerWithSchool('SCH-835013');

        $stageA = SchoolStage::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'Primary A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $stageB = SchoolStage::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Primary B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($managerA)
            ->post(route('school.student_structure.stage_grades.store'), [
                'school_stage_id' => $stageA->id,
                'name' => 'الصف الأول',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $grade = SchoolStageGrade::query()
            ->where('school_id', $schoolA->id)
            ->where('school_stage_id', $stageA->id)
            ->where('name', 'الصف الأول')
            ->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.stage_grade.created',
            'entity_type' => 'school_stage_grade',
            'entity_id' => $grade->id,
            'user_id' => $managerA->id,
        ]);

        $foreignGrade = SchoolStageGrade::query()->create([
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'الصف الثاني',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($managerA)
            ->put(route('school.student_structure.stage_grades.update', $foreignGrade->id), [
                'school_stage_id' => $stageA->id,
                'name' => 'اختراق',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('school_stage_grades', [
            'id' => $foreignGrade->id,
            'school_id' => $schoolB->id,
            'school_stage_id' => $stageB->id,
            'name' => 'الصف الثاني',
        ]);
    }

    public function test_stage_grade_delete_is_blocked_when_related_classroom_exists(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835014');

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $grade = SchoolStageGrade::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الأول',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->delete(route('school.student_structure.stage_grades.destroy', $grade->id))
            ->assertRedirect(route('school.student_structure.index', absolute: false))
            ->assertSessionHasErrors('stage_grade');

        $this->assertDatabaseHas('school_stage_grades', [
            'id' => $grade->id,
            'school_id' => $school->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_structure.stage_grade.delete_blocked',
            'entity_type' => 'school_stage_grade',
            'entity_id' => $grade->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_classroom_creation_without_grade_uses_first_configured_stage_grade(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835015');

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolStageGrade::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الأول',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        SchoolStageGrade::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الثاني',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stage->id,
                'name' => 'أ',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_classrooms', [
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
        ]);
    }

    public function test_classroom_creation_with_manual_grade_auto_registers_stage_grade_for_backward_compatibility(): void
    {
        [$manager, $school] = $this->createManagerWithSchool('SCH-835016');

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->from(route('school.student_structure.index'))
            ->actingAs($manager)
            ->post(route('school.student_structure.classrooms.store'), [
                'school_stage_id' => $stage->id,
                'grade_name' => 'الصف الخامس',
                'name' => 'ب',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('school.student_structure.index', absolute: false));

        $this->assertDatabaseHas('school_stage_grades', [
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'name' => 'الصف الخامس',
        ]);
    }

    /**
     * @return array{0: User, 1: School}
     */
    private function createManagerWithSchool(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);

        $digits = preg_replace('/\D+/', '', $schoolCode) ?: '0';
        $phone = '05' . str_pad(substr($digits, -8), 8, '0', STR_PAD_LEFT);

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
            'phone' => $phone,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        return [$manager->fresh(), $school];
    }
}

