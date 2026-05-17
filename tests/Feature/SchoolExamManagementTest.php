<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolClassroom;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolCoursePlanLesson;
use App\Models\SchoolCoursePlanTopic;
use App\Models\SchoolCoursePlanUnit;
use App\Models\SchoolExam;
use App\Models\SchoolExamSetting;
use App\Models\SchoolHoliday;
use App\Models\SchoolQuestionBankItem;
use App\Models\SchoolStage;
use App\Models\SchoolStudent;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolExamManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_exam_template_within_school_scope(): void
    {
        $context = $this->createExamContext('SCH-991001');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.templates.store'), [
                'name' => 'اختبار منتصف الترم',
                'exam_type' => 'midterm',
                'default_max_score' => 20,
                'default_passing_score' => 10,
                'requires_approval' => true,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));

        $this->assertDatabaseHas('school_exam_templates', [
            'school_id' => $context['school']->id,
            'name' => 'اختبار منتصف الترم',
            'exam_type' => 'midterm',
        ]);
    }

    public function test_manager_cannot_modify_exam_template_from_other_school(): void
    {
        $contextA = $this->createExamContext('SCH-991002');
        $contextB = $this->createExamContext('SCH-991003');

        $template = \App\Models\SchoolExamTemplate::query()->create([
            'school_id' => $contextB['school']->id,
            'name' => 'اختبار شهري',
            'exam_type' => 'monthly',
            'default_max_score' => 30,
            'default_passing_score' => 15,
            'is_active' => true,
        ]);

        $this->actingAs($contextA['manager'])
            ->put(route('school.exams.templates.update', $template), [
                'name' => 'اختبار شهري معدل',
                'exam_type' => 'monthly',
                'default_max_score' => 30,
                'default_passing_score' => 15,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_teacher_can_create_exam_for_assigned_subject(): void
    {
        $context = $this->createExamContext('SCH-991004');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context));

        $response->assertRedirect(route('school.exams.index', absolute: false));

        $this->assertDatabaseHas('school_exams', [
            'school_id' => $context['school']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'school_classroom_id' => $context['classroomA']->id,
        ]);
    }

    public function test_teacher_sees_only_assignment_scoped_exam_data(): void
    {
        $context = $this->createExamContext('SCH-991004SCOPE');

        SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار داخل النطاق',
            'exam_date' => '2026-09-14',
            'starts_at' => '08:00:00',
            'ends_at' => '08:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomB']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار خارج النطاق',
            'exam_date' => '2026-09-15',
            'starts_at' => '09:00:00',
            'ends_at' => '09:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $this->actingAs($context['teacher'])
            ->get(route('school.exams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/Exams')
                ->where('permissions.can_create_exam', true)
                ->where('subjects', fn ($subjects) => collect($subjects)->pluck('id')->map(fn ($id) => (int) $id)->contains((int) $context['subjectA']->id)
                    && !collect($subjects)->pluck('id')->map(fn ($id) => (int) $id)->contains((int) $context['subjectB']->id))
                ->where('exams', fn ($exams) => collect($exams)->contains(fn ($exam) => (string) data_get($exam, 'title', '') === 'اختبار داخل النطاق')
                    && !collect($exams)->contains(fn ($exam) => (string) data_get($exam, 'title', '') === 'اختبار خارج النطاق'))
            );
    }

    public function test_exam_index_handles_legacy_attachments_table_without_polymorphic_columns(): void
    {
        $context = $this->createExamContext('SCH-991004LEGACYATT');

        SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار جدول مرفقات قديم',
            'exam_date' => '2026-09-14',
            'starts_at' => '08:00:00',
            'ends_at' => '08:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropMorphs('attachable');
        });

        $this->actingAs($context['manager'])
            ->get(route('school.exams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/Exams')
                ->where('exams', fn ($exams) => collect($exams)->contains(
                    fn ($exam) => (string) data_get($exam, 'title') === 'اختبار جدول مرفقات قديم'
                        && (int) data_get($exam, 'attachments_count') === 0
                ))
            );
    }

    public function test_teacher_without_teaching_assignment_cannot_create_exam(): void
    {
        $context = $this->createExamContext('SCH-991004NOA');

        SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('teacher_user_id', $context['teacher']->id)
            ->delete();

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'title' => 'اختبار بدون إسناد',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('school_classroom_id');

        $this->assertDatabaseMissing('school_exams', [
            'school_id' => $context['school']->id,
            'title' => 'اختبار بدون إسناد',
            'teacher_user_id' => $context['teacher']->id,
        ]);
    }

    public function test_manager_can_sync_course_offering_assignment_with_exam_permissions(): void
    {
        $context = $this->createExamContext('SCH-991004ASSIGN');

        $response = $this->actingAs($context['manager'])
            ->post(route('school.academic_planning.offerings.assignment.sync', $context['courseOffering']), [
                'teacher_user_id' => $context['teacher']->id,
                'can_create_exam' => true,
                'can_update_exam' => false,
                'can_delete_exam' => false,
                'can_approve_exam' => false,
                'can_enter_exam_scores' => true,
                'can_edit_exam_scores' => false,
                'can_use_question_bank' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('school_teaching_assignments', [
            'school_id' => $context['school']->id,
            'school_course_offering_id' => $context['courseOffering']->id,
            'teacher_user_id' => $context['teacher']->id,
            'can_create_exam' => 1,
            'can_update_exam' => 0,
            'can_delete_exam' => 0,
            'can_approve_exam' => 0,
            'can_enter_exam_scores' => 1,
            'can_edit_exam_scores' => 0,
            'can_use_question_bank' => 0,
        ]);
    }

    public function test_exam_page_teacher_options_include_course_offering_assignment_even_without_subject_teacher_link(): void
    {
        $context = $this->createExamContext('SCH-991004ASSIGNOPT');

        SchoolSubjectTeacherAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('school_subject_id', $context['subjectA']->id)
            ->where('teacher_user_id', $context['teacher']->id)
            ->delete();

        $this->actingAs($context['manager'])
            ->get(route('school.exams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/Exams')
                ->where('subjectTeacherOptions', function ($options) use ($context): bool {
                    $rows = data_get($options, (string) $context['subjectA']->id, []);
                    return collect($rows)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->contains((int) $context['teacher']->id);
                })
            );
    }

    public function test_manager_can_create_question_bank_item_with_course_unit_chapter_and_lesson_scope(): void
    {
        $context = $this->createExamContext('SCH-991004QBSTRUCT');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.question_bank.store'), [
                'school_course_offering_id' => $context['courseOffering']->id,
                'branch_name' => 'الفرع الرئيسي',
                'unit_name' => 'الوحدة الأولى',
                'lesson_name' => 'الدرس الأول',
                'chapter_name' => 'الموضوع الأول',
                'question_text' => 'ما تعريف العدد الأولي؟',
                'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
                'question_score' => 5,
                'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
                'difficulty' => SchoolQuestionBankItem::DIFFICULTY_MEDIUM,
                'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));

        $this->assertDatabaseHas('school_question_bank_items', [
            'school_id' => $context['school']->id,
            'school_course_offering_id' => $context['courseOffering']->id,
            'school_subject_id' => $context['subjectA']->id,
            'school_stage_id' => $context['stage']->id,
            'school_term_id' => $context['term']->id,
            'unit_name' => 'الوحدة الأولى',
            'lesson_name' => 'الدرس الأول',
            'chapter_name' => 'الموضوع الأول',
            'question_text' => 'ما تعريف العدد الأولي؟',
        ]);
    }

    public function test_question_bank_index_returns_school_scoped_hierarchical_tree(): void
    {
        $contextA = $this->createExamContext('SCH-991004QBTREEA');
        $contextB = $this->createExamContext('SCH-991004QBTREEB');

        $localQuestion = SchoolQuestionBankItem::query()->create([
            'school_id' => $contextA['school']->id,
            'school_course_offering_id' => $contextA['courseOffering']->id,
            'school_subject_id' => $contextA['subjectA']->id,
            'school_stage_id' => $contextA['stage']->id,
            'school_term_id' => $contextA['term']->id,
            'unit_name' => 'الوحدة الأولى',
            'lesson_name' => 'الدرس الأول',
            'chapter_name' => 'الموضوع الأول',
            'question_text' => 'سؤال شجرة بنك الأسئلة المحلي',
            'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
            'question_score' => 5,
            'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
            'difficulty' => SchoolQuestionBankItem::DIFFICULTY_MEDIUM,
            'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
        ]);

        $foreignQuestion = SchoolQuestionBankItem::query()->create([
            'school_id' => $contextB['school']->id,
            'school_course_offering_id' => $contextB['courseOffering']->id,
            'school_subject_id' => $contextB['subjectA']->id,
            'school_stage_id' => $contextB['stage']->id,
            'school_term_id' => $contextB['term']->id,
            'unit_name' => 'الوحدة الأولى',
            'lesson_name' => 'الدرس الأول',
            'chapter_name' => 'الموضوع الأول',
            'question_text' => 'سؤال من مدرسة أخرى لا يظهر',
            'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
            'question_score' => 5,
            'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
            'difficulty' => SchoolQuestionBankItem::DIFFICULTY_MEDIUM,
            'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($contextA['manager'])
            ->get(route('school.exams.index', ['section' => 'question-bank']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('School/Exams')
                ->where('questionBankTree', function ($tree) use ($contextA, $localQuestion, $foreignQuestion): bool {
                    $stage = collect($tree)->firstWhere('id', $contextA['stage']->id);
                    $grade = collect(data_get($stage, 'grades', []))->firstWhere('name', 'الصف الأول');
                    $subject = collect(data_get($grade, 'subjects', []))->firstWhere('id', $contextA['subjectA']->id);
                    $questions = collect(data_get($subject, 'groups', []))
                        ->flatMap(fn ($group) => data_get($group, 'questions', []));

                    return is_array($tree)
                        && is_array(data_get($stage, 'grades'))
                        && is_array(data_get($grade, 'subjects'))
                        && is_array(data_get($subject, 'groups'))
                        && (int) data_get($subject, 'questions_count') === 1
                        && $questions->contains(fn ($question) => (int) data_get($question, 'id') === (int) $localQuestion->id)
                        && ! $questions->contains(fn ($question) => (int) data_get($question, 'id') === (int) $foreignQuestion->id);
                })
            );
    }

    public function test_question_bank_item_requires_unit_chapter_and_lesson_when_course_offering_is_selected(): void
    {
        $context = $this->createExamContext('SCH-991004QBSTRUCTREQ');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.question_bank.store'), [
                'school_course_offering_id' => $context['courseOffering']->id,
                'branch_name' => 'الفرع الرئيسي',
                'question_text' => 'سؤال بدون تصنيف منهجي',
                'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
                'question_score' => 3,
                'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
                'difficulty' => SchoolQuestionBankItem::DIFFICULTY_EASY,
                'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors([
            'unit_name',
            'chapter_name',
            'lesson_name',
        ]);
        $response->assertSessionHasErrors([
            'unit_name' => 'يرجى اختيار الوحدة للمقرر المحدد قبل حفظ السؤال.',
            'chapter_name' => 'يرجى اختيار الموضوع للمقرر المحدد قبل حفظ السؤال.',
            'lesson_name' => 'يرجى اختيار الدرس للمقرر المحدد قبل حفظ السؤال.',
        ]);

        $this->assertDatabaseMissing('school_question_bank_items', [
            'school_id' => $context['school']->id,
            'question_text' => 'سؤال بدون تصنيف منهجي',
        ]);
    }

    public function test_question_bank_item_rejects_topic_that_is_not_linked_to_selected_lesson(): void
    {
        $context = $this->createExamContext('SCH-991004QBSTRUCTTOPIC');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.question_bank.store'), [
                'school_course_offering_id' => $context['courseOffering']->id,
                'branch_name' => 'الفرع الرئيسي',
                'unit_name' => 'الوحدة الأولى',
                'lesson_name' => 'الدرس الأول',
                'chapter_name' => 'موضوع غير موجود',
                'question_text' => 'سؤال بموضوع غير مطابق للخطة',
                'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
                'question_score' => 3,
                'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
                'difficulty' => SchoolQuestionBankItem::DIFFICULTY_EASY,
                'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors([
            'chapter_name' => 'لا يمكن حفظ السؤال لأن الموضوع المحدد لا يتبع الدرس المختار في خطة المقرر.',
        ]);
    }

    public function test_question_bank_item_requires_branch_when_course_offering_is_selected(): void
    {
        $context = $this->createExamContext('SCH-991004QBSTRUCTBRANCHREQ');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['manager'])
            ->post(route('school.exams.question_bank.store'), [
                'school_course_offering_id' => $context['courseOffering']->id,
                'unit_name' => 'الوحدة الأولى',
                'lesson_name' => 'الدرس الأول',
                'chapter_name' => 'الموضوع الأول',
                'question_text' => 'سؤال بدون فرع',
                'question_type' => SchoolQuestionBankItem::TYPE_SHORT_ANSWER,
                'question_score' => 3,
                'selection_mode' => SchoolQuestionBankItem::SELECTION_REQUIRED,
                'difficulty' => SchoolQuestionBankItem::DIFFICULTY_EASY,
                'status' => SchoolQuestionBankItem::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors([
            'branch_name' => 'يرجى اختيار الفرع للمقرر المحدد قبل حفظ السؤال.',
        ]);
    }

    public function test_teacher_cannot_create_exam_when_assignment_disables_create_exam(): void
    {
        $context = $this->createExamContext('SCH-991004B');
        $this->updateTeacherAssignmentPermissions($context, [
            'can_create_exam' => false,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'title' => 'اختبار إنشاء مقيّد',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('exam');

        $this->assertDatabaseMissing('school_exams', [
            'school_id' => $context['school']->id,
            'title' => 'اختبار إنشاء مقيّد',
            'teacher_user_id' => $context['teacher']->id,
        ]);
    }

    public function test_teacher_cannot_create_exam_for_unassigned_subject(): void
    {
        $context = $this->createExamContext('SCH-991005');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'school_subject_id' => $context['subjectB']->id,
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('school_subject_id');
    }

    public function test_teacher_cannot_create_exam_for_unassigned_classroom(): void
    {
        $context = $this->createExamContext('SCH-991005A');

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'school_classroom_id' => $context['classroomB']->id,
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('school_classroom_id');
    }

    public function test_exam_creation_fails_on_weekly_off_day(): void
    {
        $context = $this->createExamContext('SCH-991006');

        SchoolCalendarSetting::query()->updateOrCreate(
            ['school_id' => $context['school']->id],
            ['week_start_day' => 0, 'weekly_off_days' => [6]]
        );

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'exam_date' => '2026-09-12', // Saturday
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('exam_date');
        $response->assertSessionHasErrors([
            'exam_date' => 'لا يمكن حفظ الاختبار لأن التاريخ المحدد يوافق عطلة أسبوعية.',
        ]);
    }

    public function test_exam_creation_fails_on_holiday_date(): void
    {
        $context = $this->createExamContext('SCH-991007');

        SchoolHoliday::query()->create([
            'school_id' => $context['school']->id,
            'name' => 'اليوم الوطني',
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
            'return_date' => '2026-09-16',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'exam_date' => '2026-09-15',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('exam_date');
    }

    public function test_exam_creation_fails_when_course_offering_is_not_usable_in_exams(): void
    {
        $context = $this->createExamContext('SCH-991007B');

        $context['courseOffering']->update([
            'usable_in_exams' => false,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'title' => 'اختبار بمقرر غير مفعّل',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('school_subject_id');
        $response->assertSessionHasErrors([
            'school_subject_id' => 'لا يمكن استخدام هذا المقرر في الاختبارات لأنه غير مفعّل للاختبارات.',
        ]);
    }

    public function test_exam_creation_fails_when_classroom_has_overlapping_exam(): void
    {
        $context = $this->createExamContext('SCH-991008');

        SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار سابق',
            'exam_date' => '2026-09-20',
            'starts_at' => '09:00:00',
            'ends_at' => '10:00:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'exam_date' => '2026-09-20',
                'starts_at' => '09:30',
                'ends_at' => '10:15',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('school_classroom_id');
    }

    public function test_exam_creation_fails_on_schedule_conflict_with_non_subject_session(): void
    {
        $context = $this->createExamContext('SCH-991009');

        SchoolExamSetting::query()->create([
            'school_id' => $context['school']->id,
            'allow_subject_schedule_slot_overlap' => true,
        ]);

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectB']->id,
            'teacher_user_id' => $context['teacher']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '09:45:00',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'exam_date' => '2026-09-14', // Monday
                'starts_at' => '09:10',
                'ends_at' => '09:30',
                'allow_subject_schedule_overlap' => true,
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('exam_date');
    }

    public function test_exam_creation_allows_same_subject_schedule_overlap_when_enabled(): void
    {
        $context = $this->createExamContext('SCH-991010');

        SchoolExamSetting::query()->create([
            'school_id' => $context['school']->id,
            'allow_subject_schedule_slot_overlap' => true,
        ]);

        SchoolClassSchedule::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
            'day_of_week' => 1,
            'session_index' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '09:45:00',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($context, [
                'exam_date' => '2026-09-14',
                'starts_at' => '09:05',
                'ends_at' => '09:30',
                'allow_subject_schedule_overlap' => true,
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $this->assertDatabaseHas('school_exams', [
            'school_id' => $context['school']->id,
            'exam_date' => '2026-09-14',
            'school_subject_id' => $context['subjectA']->id,
        ]);
    }

    public function test_exam_creation_same_slot_in_another_school_is_allowed(): void
    {
        $contextA = $this->createExamContext('SCH-991010A');
        $contextB = $this->createExamContext('SCH-991010B');

        SchoolExam::query()->create([
            'school_id' => $contextA['school']->id,
            'school_term_id' => $contextA['term']->id,
            'school_stage_id' => $contextA['stage']->id,
            'school_classroom_id' => $contextA['classroomA']->id,
            'school_subject_id' => $contextA['subjectA']->id,
            'teacher_user_id' => $contextA['teacher']->id,
            'title' => 'اختبار في مدرسة أخرى',
            'exam_date' => '2026-09-20',
            'starts_at' => '09:00:00',
            'ends_at' => '10:00:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($contextB['teacher'])
            ->post(route('school.exams.store'), $this->examPayload($contextB, [
                'exam_date' => '2026-09-20',
                'starts_at' => '09:05',
                'ends_at' => '09:45',
            ]));

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('school_exams', [
            'school_id' => $contextB['school']->id,
            'exam_date' => '2026-09-20',
            'school_classroom_id' => $contextB['classroomA']->id,
            'teacher_user_id' => $contextB['teacher']->id,
        ]);
    }

    public function test_sync_exam_questions_fails_with_question_from_other_subject(): void
    {
        $context = $this->createExamContext('SCH-991011');

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار الرياضيات',
            'exam_date' => '2026-09-22',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $foreignQuestion = SchoolQuestionBankItem::query()->create([
            'school_id' => $context['school']->id,
            'school_subject_id' => $context['subjectB']->id,
            'question_text' => 'سؤال علوم',
            'question_type' => 'short_answer',
            'question_score' => 5,
            'selection_mode' => 'required',
            'difficulty' => 'medium',
            'status' => 'active',
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.questions.sync', $exam), [
                'questions' => [
                    [
                        'school_question_bank_item_id' => $foreignQuestion->id,
                        'score' => 5,
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('questions');
    }

    public function test_sync_exam_questions_fails_with_question_from_other_school(): void
    {
        $contextA = $this->createExamContext('SCH-991011A');
        $contextB = $this->createExamContext('SCH-991011B');

        $exam = SchoolExam::query()->create([
            'school_id' => $contextA['school']->id,
            'school_term_id' => $contextA['term']->id,
            'school_stage_id' => $contextA['stage']->id,
            'school_classroom_id' => $contextA['classroomA']->id,
            'school_subject_id' => $contextA['subjectA']->id,
            'teacher_user_id' => $contextA['teacher']->id,
            'title' => 'اختبار المدرسة أ',
            'exam_date' => '2026-09-22',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $foreignSchoolQuestion = SchoolQuestionBankItem::query()->create([
            'school_id' => $contextB['school']->id,
            'school_subject_id' => $contextB['subjectA']->id,
            'question_text' => 'سؤال من مدرسة أخرى',
            'question_type' => 'short_answer',
            'question_score' => 5,
            'selection_mode' => 'required',
            'difficulty' => 'medium',
            'status' => 'active',
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($contextA['teacher'])
            ->post(route('school.exams.questions.sync', $exam), [
                'questions' => [
                    [
                        'school_question_bank_item_id' => $foreignSchoolQuestion->id,
                        'score' => 5,
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('questions');
    }

    public function test_teacher_cannot_sync_exam_questions_without_question_bank_permission(): void
    {
        $context = $this->createExamContext('SCH-991011C');
        $this->updateTeacherAssignmentPermissions($context, [
            'can_use_question_bank' => false,
        ]);

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار بنك الأسئلة',
            'exam_date' => '2026-09-25',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $question = SchoolQuestionBankItem::query()->create([
            'school_id' => $context['school']->id,
            'school_subject_id' => $context['subjectA']->id,
            'question_text' => 'سؤال ضمن نفس المادة',
            'question_type' => 'short_answer',
            'question_score' => 5,
            'selection_mode' => 'required',
            'difficulty' => 'medium',
            'status' => 'active',
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.questions.sync', $exam), [
                'questions' => [
                    [
                        'school_question_bank_item_id' => $question->id,
                        'score' => 5,
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('exam');
    }

    public function test_score_above_exam_max_is_rejected(): void
    {
        $context = $this->createExamContext('SCH-991012');

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار الدرجات',
            'exam_date' => '2026-09-22',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $context['school']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'full_name' => 'طالب اختبار',
            'student_code' => 'STU-TST-1',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.scores.upsert', $exam), [
                'scores' => [
                    [
                        'school_student_id' => $student->id,
                        'score' => 25,
                        'attendance_status' => 'present',
                        'notes' => '',
                        'is_finalized' => false,
                    ],
                ],
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionHasErrors('scores');
    }

    public function test_teacher_can_record_scores_for_targeted_students_within_school_scope(): void
    {
        $context = $this->createExamContext('SCH-991013');

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار الرصد',
            'exam_date' => '2026-09-23',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $context['school']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'full_name' => 'طالب ضمن نفس الصف',
            'student_code' => 'STU-TST-2',
            'is_active' => true,
        ]);

        $response = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.scores.upsert', $exam), [
                'scores' => [
                    [
                        'school_student_id' => $student->id,
                        'score' => 18,
                        'attendance_status' => 'present',
                        'notes' => 'أداء جيد',
                        'is_finalized' => true,
                    ],
                ],
            ]);

        $response->assertRedirect(route('school.exams.index', absolute: false));
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('school_exam_student_scores', [
            'school_id' => $context['school']->id,
            'school_exam_id' => $exam->id,
            'school_student_id' => $student->id,
            'attendance_status' => 'present',
            'is_finalized' => true,
        ]);
    }

    public function test_teacher_cannot_edit_scores_when_assignment_disables_edit_scores(): void
    {
        $context = $this->createExamContext('SCH-991013B');
        $this->updateTeacherAssignmentPermissions($context, [
            'can_enter_exam_scores' => true,
            'can_edit_exam_scores' => false,
        ]);

        $exam = SchoolExam::query()->create([
            'school_id' => $context['school']->id,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار رصد مقيّد',
            'exam_date' => '2026-09-26',
            'starts_at' => '10:00:00',
            'ends_at' => '10:45:00',
            'max_score' => 20,
            'passing_score' => 10,
            'status' => SchoolExam::STATUS_APPROVED,
            'is_active' => true,
        ]);

        $student = SchoolStudent::query()->create([
            'school_id' => $context['school']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'full_name' => 'طالب رصد مقيّد',
            'student_code' => 'STU-TST-3',
            'is_active' => true,
        ]);

        $firstWrite = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.scores.upsert', $exam), [
                'scores' => [
                    [
                        'school_student_id' => $student->id,
                        'score' => 14,
                        'attendance_status' => 'present',
                        'notes' => '',
                        'is_finalized' => false,
                    ],
                ],
            ]);

        $firstWrite->assertRedirect(route('school.exams.index', absolute: false));
        $firstWrite->assertSessionDoesntHaveErrors();

        $secondWrite = $this->from(route('school.exams.index'))
            ->actingAs($context['teacher'])
            ->post(route('school.exams.scores.upsert', $exam), [
                'scores' => [
                    [
                        'school_student_id' => $student->id,
                        'score' => 16,
                        'attendance_status' => 'present',
                        'notes' => 'محاولة تعديل',
                        'is_finalized' => false,
                    ],
                ],
            ]);

        $secondWrite->assertRedirect(route('school.exams.index', absolute: false));
        $secondWrite->assertSessionHasErrors('exam');
    }

    /**
     * @return array{
     *     school:School,
     *     manager:User,
     *     teacher:User,
     *     term:SchoolTerm,
     *     stage:SchoolStage,
     *     classroomA:SchoolClassroom,
     *     classroomB:SchoolClassroom,
     *     subjectA:SchoolSubject,
     *     subjectB:SchoolSubject,
     *     courseOffering:SchoolCourseOffering
     * }
     */
    private function createExamContext(string $schoolCode): array
    {
        Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $region = EducationalDirectorate::query()->create([
            'name' => 'Region ' . $schoolCode,
            'governorate' => 'Riyadh',
        ]);

        $manager = User::factory()->create(['role' => 'school_manager']);
        $manager->assignRole('school_manager');

        $department = Department::query()->create([
            'name' => 'قسم المعلمين ' . $schoolCode,
            'staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'school_id' => null,
        ]);

        $school = School::query()->create([
            'directorate_id' => $region->id,
            'name' => 'School ' . $schoolCode,
            'school_id' => $schoolCode,
            'phone' => '05' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
            'manager_user_id' => $manager->id,
        ]);

        $manager->update(['school_id' => $school->id]);

        $teacher = User::factory()->create([
            'role' => 'staff',
            'school_id' => $school->id,
            'department_id' => $department->id,
            'school_staff_type' => Department::STAFF_TYPE_EDUCATIONAL,
            'is_active' => true,
        ]);
        $teacher->assignRole('staff');

        $term = SchoolTerm::query()->create([
            'school_id' => $school->id,
            'name' => 'Term 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $stage = SchoolStage::query()->create([
            'school_id' => $school->id,
            'name' => 'Primary',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomA = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $classroomB = SchoolClassroom::query()->create([
            'school_id' => $school->id,
            'school_stage_id' => $stage->id,
            'grade_name' => 'الصف الأول',
            'name' => 'ب',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $subjectA = SchoolSubject::query()->create([
            'school_id' => $school->id,
            'name' => 'الرياضيات',
            'code' => 'MTH-' . substr($schoolCode, -3),
            'is_active' => true,
        ]);

        $subjectB = SchoolSubject::query()->create([
            'school_id' => $school->id,
            'name' => 'العلوم',
            'code' => 'SCI-' . substr($schoolCode, -3),
            'is_active' => true,
        ]);

        SchoolSubjectTeacherAssignment::query()->create([
            'school_id' => $school->id,
            'school_subject_id' => $subjectA->id,
            'teacher_user_id' => $teacher->id,
        ]);

        $courseOffering = SchoolCourseOffering::query()->create([
            'school_id' => $school->id,
            'school_term_id' => $term->id,
            'school_stage_id' => $stage->id,
            'school_classroom_id' => $classroomA->id,
            'school_subject_id' => $subjectA->id,
            'is_active' => true,
        ]);

        $planUnit = SchoolCoursePlanUnit::query()->create([
            'school_id' => $school->id,
            'school_course_offering_id' => $courseOffering->id,
            'branch_name' => 'الفرع الرئيسي',
            'name' => 'الوحدة الأولى',
            'sort_order' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $planLesson = SchoolCoursePlanLesson::query()->create([
            'school_id' => $school->id,
            'school_course_plan_unit_id' => $planUnit->id,
            'name' => 'الدرس الأول',
            'sort_order' => 1,
        ]);

        SchoolCoursePlanTopic::query()->create([
            'school_id' => $school->id,
            'school_course_plan_lesson_id' => $planLesson->id,
            'name' => 'الموضوع الأول',
            'sort_order' => 1,
        ]);

        SchoolTeachingAssignment::query()->create([
            'school_id' => $school->id,
            'school_course_offering_id' => $courseOffering->id,
            'teacher_user_id' => $teacher->id,
            'is_active' => true,
            'can_create_exam' => true,
            'can_update_exam' => true,
            'can_delete_exam' => true,
            'can_approve_exam' => false,
            'can_enter_exam_scores' => true,
            'can_edit_exam_scores' => true,
            'can_use_question_bank' => true,
        ]);

        return [
            'school' => $school,
            'manager' => $manager,
            'teacher' => $teacher,
            'term' => $term,
            'stage' => $stage,
            'classroomA' => $classroomA,
            'classroomB' => $classroomB,
            'subjectA' => $subjectA,
            'subjectB' => $subjectB,
            'courseOffering' => $courseOffering,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function examPayload(array $context, array $overrides = []): array
    {
        return array_merge([
            'school_exam_template_id' => null,
            'school_term_id' => $context['term']->id,
            'school_stage_id' => $context['stage']->id,
            'school_classroom_id' => $context['classroomA']->id,
            'school_subject_id' => $context['subjectA']->id,
            'teacher_user_id' => $context['teacher']->id,
            'title' => 'اختبار أسبوعي 1',
            'exam_date' => '2026-09-14', // Monday
            'starts_at' => '09:00',
            'ends_at' => '09:45',
            'max_score' => 20,
            'passing_score' => 10,
            'allow_subject_schedule_overlap' => false,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, bool> $permissions
     */
    private function updateTeacherAssignmentPermissions(array $context, array $permissions): void
    {
        $assignment = SchoolTeachingAssignment::query()
            ->where('school_id', $context['school']->id)
            ->where('teacher_user_id', $context['teacher']->id)
            ->firstOrFail();

        $assignment->update($permissions);
    }
}
