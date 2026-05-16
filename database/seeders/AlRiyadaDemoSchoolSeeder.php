<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\EducationalDirectorate;
use App\Models\EducationStage;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolAcademicYear;
use App\Models\SchoolCalendarSetting;
use App\Models\SchoolClassroom;
use App\Models\SchoolClassSchedule;
use App\Models\SchoolCourseOffering;
use App\Models\SchoolExam;
use App\Models\SchoolExamSetting;
use App\Models\SchoolExamStudentScore;
use App\Models\SchoolExamTemplate;
use App\Models\SchoolHoliday;
use App\Models\SchoolLeaveType;
use App\Models\SchoolStage;
use App\Models\SchoolStageGrade;
use App\Models\SchoolStageGradeTerm;
use App\Models\SchoolStageTerm;
use App\Models\SchoolStudent;
use App\Models\SchoolStudentAttendance;
use App\Models\SchoolStudentLeaveRequest;
use App\Models\SchoolSubject;
use App\Models\SchoolSubjectTeacherAssignment;
use App\Models\SchoolTeachingAssignment;
use App\Models\SchoolTerm;
use App\Models\SchoolTimetableVersion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AlRiyadaDemoSchoolSeeder extends Seeder
{
    private const SCHOOL_CODE = 'SCH-RIYADAH-DEMO';
    private const DEFAULT_PASSWORD = 'password';

    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        DB::transaction(function (): void {
            $directorate = $this->ensureDirectorate();
            $school = $this->ensureSchool($directorate);

            $manager = $this->ensureUser(
                email: 'manager.alriyadah@edaratek.test',
                name: 'مدير مدرسة الريادة',
                legacyRole: 'school_manager',
                roles: ['school_manager'],
                schoolId: (int) $school->id,
                staffType: null,
                extra: [
                    'phone' => '0501001001',
                    'mobile' => '0501001001',
                    'onboarding_region_id' => (int) $directorate->id,
                    'onboarding_completed_at' => now(),
                ],
            );

            $supervisor = $this->ensureUser(
                email: 'supervisor.alriyadah@edaratek.test',
                name: 'مشرف مدرسة الريادة',
                legacyRole: 'supervisor',
                roles: ['supervisor'],
                schoolId: null,
                staffType: null,
                extra: [
                    'phone' => '0501001002',
                    'mobile' => '0501001002',
                    'onboarding_region_id' => (int) $directorate->id,
                    'onboarding_completed_at' => now(),
                ],
            );

            $this->saveModel($school, [
                'directorate_id' => (int) $directorate->id,
                'name' => 'مدرسة الريادة',
                'school_type' => School::TYPE_MIXED,
                'phone' => '0114455667',
                'email' => 'alriyadah.school@edaratek.test',
                'address' => 'الرياض - حي الريادة - شارع التعليم',
                'notes' => 'بيانات اختبار مكتملة تم إنشاؤها عبر AlRiyadaDemoSchoolSeeder.',
                'status' => School::STATUS_ACTIVE,
                'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
                'supervisor_id' => (int) $supervisor->id,
                'manager_user_id' => (int) $manager->id,
                'default_data_imported_at' => now(),
                'default_data_imported_by' => (int) $manager->id,
            ]);

            $this->ensureSupervisorLinks($school, $directorate, $manager, $supervisor);
            $this->ensureSubscription($school, $manager);
            $this->ensureEducationStageLinks($school);

            [$departmentRoles, $educationDepartmentRole] = $this->ensureDepartmentsAndRoles($school);
            $this->ensureAdministrativeStaff($school, $departmentRoles);

            $subjectCatalog = $this->subjectCatalog();
            $teachersBySubject = $this->ensureTeachers($school, $educationDepartmentRole, $subjectCatalog);

            [$academicYear, $terms, $currentTerm] = $this->ensureAcademicCalendar($school, $manager);
            $schoolStructure = $this->ensureSchoolStructure($school, $manager, $academicYear, $terms);
            $subjects = $this->ensureSubjects($school, $subjectCatalog);

            $this->ensureLeaveAndCalendarData($school, $manager);

            [$studentsByClassroom, $allStudents] = $this->ensureStudents($school, $schoolStructure['classrooms']);
            $courseContext = $this->ensureTeachingAndSchedules(
                school: $school,
                manager: $manager,
                currentTerm: $currentTerm,
                classrooms: $schoolStructure['classrooms'],
                subjects: $subjects,
                teachersBySubject: $teachersBySubject,
            );

            $this->ensureAttendance($school, $manager, $studentsByClassroom);
            $this->ensureStudentLeaves($school, $manager, $allStudents);
            $this->ensureExamsAndScores(
                school: $school,
                manager: $manager,
                currentTerm: $currentTerm,
                classrooms: $schoolStructure['classrooms'],
                subjects: $subjects,
                teachersBySubject: $teachersBySubject,
                studentsByClassroom: $studentsByClassroom,
            );

            $this->command?->info('تم تجهيز بيانات مدرسة الريادة للاختبار.');
            $this->command?->line('البريد التجريبي للمدير: manager.alriyadah@edaratek.test');
            $this->command?->line('كلمة المرور لكل الحسابات التجريبية: ' . self::DEFAULT_PASSWORD);
            $this->command?->line('عدد الفصول: ' . count($schoolStructure['classrooms']));
            $this->command?->line('عدد الطلاب: ' . count($allStudents));
            $this->command?->line('عدد المقررات المفتوحة: ' . count($courseContext['offerings']));
        });
    }

    private function ensureDirectorate(): EducationalDirectorate
    {
        $directorate = EducationalDirectorate::query()->firstOrNew([
            'name' => 'إدارة تعليم الرياض - بيانات اختبار',
        ]);

        return $this->saveModel($directorate, [
            'name' => 'إدارة تعليم الرياض - بيانات اختبار',
            'governorate' => 'الرياض',
        ]);
    }

    private function ensureSchool(EducationalDirectorate $directorate): School
    {
        $school = School::query()
            ->where('school_id', self::SCHOOL_CODE)
            ->orWhereIn('name', ['مدرسة الريادة', 'الريادة'])
            ->first();

        if (! $school) {
            $school = new School();
            $school->forceFill(['school_id' => self::SCHOOL_CODE]);
        }

        return $this->saveModel($school, [
            'directorate_id' => (int) $directorate->id,
            'name' => 'مدرسة الريادة',
            'school_id' => $school->school_id ?: self::SCHOOL_CODE,
            'school_type' => School::TYPE_MIXED,
            'status' => School::STATUS_ACTIVE,
            'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
        ]);
    }

    /**
     * @param array<int, string> $roles
     * @param array<string, mixed> $extra
     */
    private function ensureUser(
        string $email,
        string $name,
        string $legacyRole,
        array $roles,
        ?int $schoolId,
        ?string $staffType,
        array $extra = [],
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);

        $payload = array_merge([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'role' => $legacyRole,
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => now(),
            'school_id' => $schoolId,
            'school_staff_type' => $staffType,
        ], $extra);

        $this->saveModel($user, $payload);

        try {
            $user->syncRoles($roles);
        } catch (Throwable) {
            // Role assignment is best-effort so the seeder can still run before role tables are ready.
        }

        return $user->refresh();
    }

    private function ensureSupervisorLinks(School $school, EducationalDirectorate $directorate, User $manager, User $supervisor): void
    {
        if (Schema::hasTable('school_supervisor_assignments')) {
            DB::table('school_supervisor_assignments')->updateOrInsert(
                [
                    'supervisor_id' => (int) $supervisor->id,
                    'school_id' => (int) $school->id,
                ],
                [
                    'directorate_id' => (int) $directorate->id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('school_supervision_requests')) {
            DB::table('school_supervision_requests')->updateOrInsert(
                [
                    'school_id' => (int) $school->id,
                    'supervisor_id' => (int) $supervisor->id,
                    'manager_id' => (int) $manager->id,
                    'status' => 'ACTIVE_ASSOCIATION',
                ],
                [
                    'region_id' => (int) $directorate->id,
                    'requested_at' => now()->subDays(8),
                    'manager_action_at' => now()->subDays(7),
                    'supervisor_confirmed_at' => now()->subDays(6),
                    'notes' => 'ارتباط تجريبي مفعل لمدرسة الريادة.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function ensureSubscription(School $school, User $manager): void
    {
        $plan = Plan::query()->firstOrNew([
            'name' => 'باقة اختبار مدرسة الريادة',
        ]);

        $this->saveModel($plan, [
            'name' => 'باقة اختبار مدرسة الريادة',
            'role_type' => Plan::ROLE_SCHOOL_MANAGER,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'price' => 4800,
            'monthly_price' => 450,
            'yearly_price' => 4800,
            'included_users_count' => 60,
            'extra_user_monthly_price' => 20,
            'is_active' => true,
            'limits' => [
                'students' => 800,
                'classrooms' => 40,
                'reports' => true,
                'certificates' => true,
            ],
            'description' => 'باقة تجريبية كاملة لاختبار مدرسة الريادة.',
        ]);

        $subscription = Subscription::query()->firstOrNew([
            'user_id' => (int) $manager->id,
            'school_id' => (int) $school->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->saveModel($subscription, [
            'user_id' => (int) $manager->id,
            'school_id' => (int) $school->id,
            'plan_id' => (int) $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Plan::BILLING_YEARLY,
            'base_price' => 4800,
            'included_users_count' => 60,
            'extra_user_monthly_price' => 20,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addYear(),
            'meta' => [
                'seeded_for' => 'al_riyadah_demo_school',
                'note' => 'اشتراك نشط لاختبار وظائف المدرسة.',
            ],
        ]);
    }

    private function ensureEducationStageLinks(School $school): void
    {
        if (! Schema::hasTable('education_stages') || ! Schema::hasTable('education_stage_school')) {
            return;
        }

        foreach ([
            ['name' => 'المرحلة الابتدائية', 'sort_order' => 1],
            ['name' => 'المرحلة المتوسطة', 'sort_order' => 2],
            ['name' => 'المرحلة الثانوية', 'sort_order' => 3],
        ] as $row) {
            $stage = EducationStage::query()->firstOrNew(['name' => $row['name']]);
            $this->saveModel($stage, [
                'name' => $row['name'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
            ]);

            DB::table('education_stage_school')->updateOrInsert(
                [
                    'education_stage_id' => (int) $stage->id,
                    'school_id' => (int) $school->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array{0: array<string, DepartmentRole>, 1: DepartmentRole}
     */
    private function ensureDepartmentsAndRoles(School $school): array
    {
        $departments = [
            'school_admin' => ['name' => 'الإدارة المدرسية', 'type' => Department::STAFF_TYPE_ADMINISTRATIVE],
            'student_affairs' => ['name' => 'شؤون الطلاب', 'type' => Department::STAFF_TYPE_ADMINISTRATIVE],
            'teacher_affairs' => ['name' => 'شؤون المعلمين', 'type' => Department::STAFF_TYPE_ADMINISTRATIVE],
            'guidance' => ['name' => 'الإرشاد الطلابي', 'type' => Department::STAFF_TYPE_ADMINISTRATIVE],
            'activities' => ['name' => 'النشاط المدرسي', 'type' => Department::STAFF_TYPE_ADMINISTRATIVE],
            'education' => ['name' => 'التعليم العام', 'type' => Department::STAFF_TYPE_EDUCATIONAL],
        ];

        $roles = [];

        foreach ($departments as $key => $departmentData) {
            $department = Department::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'name' => $departmentData['name'],
            ]);

            $this->saveModel($department, [
                'school_id' => (int) $school->id,
                'name' => $departmentData['name'],
                'staff_type' => $departmentData['type'],
            ]);

            $roleName = $key === 'education' ? 'معلم' : 'مسؤول ' . $departmentData['name'];
            $role = DepartmentRole::query()->firstOrNew([
                'department_id' => (int) $department->id,
                'name' => $roleName,
            ]);

            $permissions = $key === 'education'
                ? [
                    'can_manage_student_structure' => false,
                    'can_manage_student_attendance' => true,
                    'can_manage_academic_planning' => false,
                    'can_manage_student_leaves' => false,
                    'can_manage_leave_types' => false,
                    'can_manage_school_calendar' => false,
                    'can_manage_school_holidays' => false,
                ]
                : [
                    'can_manage_student_structure' => true,
                    'can_manage_student_attendance' => true,
                    'can_manage_academic_planning' => true,
                    'can_manage_student_leaves' => true,
                    'can_manage_leave_types' => true,
                    'can_manage_school_calendar' => true,
                    'can_manage_school_holidays' => true,
                ];

            $this->saveModel($role, array_merge([
                'department_id' => (int) $department->id,
                'name' => $roleName,
                'is_active' => true,
            ], $permissions));

            $roles[$key] = $role->refresh();
        }

        return [$roles, $roles['education']];
    }

    /**
     * @param array<string, DepartmentRole> $departmentRoles
     */
    private function ensureAdministrativeStaff(School $school, array $departmentRoles): void
    {
        $staff = [
            ['name' => 'سارة القحطاني', 'email' => 'student.affairs.alriyadah@edaratek.test', 'role' => $departmentRoles['student_affairs']],
            ['name' => 'نورة الدوسري', 'email' => 'guidance.alriyadah@edaratek.test', 'role' => $departmentRoles['guidance']],
            ['name' => 'عبدالله الشهري', 'email' => 'admin.alriyadah@edaratek.test', 'role' => $departmentRoles['school_admin']],
            ['name' => 'هند المطيري', 'email' => 'activities.alriyadah@edaratek.test', 'role' => $departmentRoles['activities']],
            ['name' => 'ماجد الزهراني', 'email' => 'teacher.affairs.alriyadah@edaratek.test', 'role' => $departmentRoles['teacher_affairs']],
        ];

        foreach ($staff as $index => $member) {
            $role = $member['role'];
            $this->ensureUser(
                email: $member['email'],
                name: $member['name'],
                legacyRole: 'staff',
                roles: ['staff'],
                schoolId: (int) $school->id,
                staffType: User::SCHOOL_STAFF_ADMINISTRATIVE,
                extra: [
                    'phone' => '05020010' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'mobile' => '05020010' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'department_id' => (int) $role->department_id,
                    'department_role_id' => (int) $role->id,
                    'can_manage_student_structure' => true,
                    'can_manage_student_attendance' => true,
                    'can_manage_academic_planning' => true,
                    'can_manage_student_leaves' => true,
                    'can_manage_leave_types' => true,
                    'can_manage_school_calendar' => true,
                    'can_manage_school_holidays' => true,
                ],
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $subjectCatalog
     * @return array<string, User>
     */
    private function ensureTeachers(School $school, DepartmentRole $teacherRole, array $subjectCatalog): array
    {
        $teacherNames = [
            'quran' => 'أحمد الحربي',
            'islamic' => 'محمد السبيعي',
            'arabic' => 'خالد العتيبي',
            'math' => 'يوسف المالكي',
            'science' => 'فهد الغامدي',
            'english' => 'ريم العنزي',
            'social' => 'تركي الشمري',
            'computer' => 'ليان المطيري',
            'pe' => 'سلمان الدوسري',
            'art' => 'مها القحطاني',
            'life_skills' => 'نوف الحربي',
            'physics' => 'عبدالرحمن الزهراني',
            'chemistry' => 'عبدالعزيز القحطاني',
            'biology' => 'سعيد الشهري',
        ];

        $teachers = [];
        $index = 1;

        foreach ($subjectCatalog as $key => $subject) {
            $teacher = $this->ensureUser(
                email: 'teacher.' . $key . '.alriyadah@edaratek.test',
                name: $teacherNames[$key] ?? ('معلم ' . $subject['name']),
                legacyRole: 'staff',
                roles: ['staff', 'teacher'],
                schoolId: (int) $school->id,
                staffType: User::SCHOOL_STAFF_EDUCATIONAL,
                extra: [
                    'phone' => '050300' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'mobile' => '050300' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'department_id' => (int) $teacherRole->department_id,
                    'department_role_id' => (int) $teacherRole->id,
                    'can_manage_student_attendance' => true,
                    'can_manage_academic_planning' => false,
                    'can_manage_student_structure' => false,
                    'can_manage_student_leaves' => false,
                ],
            );

            $teachers[$key] = $teacher;
            $index++;
        }

        return $teachers;
    }

    /**
     * @return array{0: SchoolAcademicYear, 1: array<string, SchoolTerm>, 2: SchoolTerm}
     */
    private function ensureAcademicCalendar(School $school, User $manager): array
    {
        $now = now();
        $startYear = $now->month >= 8 ? $now->year : $now->year - 1;
        $endYear = $startYear + 1;

        $academicYear = SchoolAcademicYear::query()->firstOrNew([
            'school_id' => (int) $school->id,
            'name' => "العام الدراسي {$startYear}-{$endYear}",
        ]);

        $this->saveModel($academicYear, [
            'school_id' => (int) $school->id,
            'name' => "العام الدراسي {$startYear}-{$endYear}",
            'starts_on' => Carbon::create($startYear, 8, 24)->toDateString(),
            'ends_on' => Carbon::create($endYear, 6, 25)->toDateString(),
            'is_active' => true,
            'created_by' => (int) $manager->id,
            'updated_by' => (int) $manager->id,
        ]);

        $termRows = [
            'term_1' => ['name' => 'الفصل الدراسي الأول', 'start' => Carbon::create($startYear, 8, 24), 'end' => Carbon::create($startYear, 11, 20)],
            'term_2' => ['name' => 'الفصل الدراسي الثاني', 'start' => Carbon::create($startYear, 12, 1), 'end' => Carbon::create($endYear, 3, 12)],
            'term_3' => ['name' => 'الفصل الدراسي الثالث', 'start' => Carbon::create($endYear, 3, 22), 'end' => Carbon::create($endYear, 6, 18)],
        ];

        $terms = [];

        foreach ($termRows as $key => $row) {
            $term = SchoolTerm::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'name' => $row['name'],
            ]);

            $this->saveModel($term, [
                'school_id' => (int) $school->id,
                'school_academic_year_id' => (int) $academicYear->id,
                'name' => $row['name'],
                'start_date' => $row['start']->toDateString(),
                'end_date' => $row['end']->toDateString(),
                'is_active' => true,
            ]);

            $terms[$key] = $term->refresh();
        }

        $currentTerm = collect($terms)->first(function (SchoolTerm $term) use ($now): bool {
            return Carbon::parse($term->start_date)->lte($now) && Carbon::parse($term->end_date)->gte($now);
        }) ?: $terms['term_3'];

        return [$academicYear->refresh(), $terms, $currentTerm];
    }

    /**
     * @param array<string, SchoolTerm> $terms
     * @return array{classrooms: array<int, array{model: SchoolClassroom, stage_key: string, grade_name: string, subject_keys: array<int, string>}>}
     */
    private function ensureSchoolStructure(School $school, User $manager, SchoolAcademicYear $academicYear, array $terms): array
    {
        $stages = [
            'elementary' => [
                'name' => 'المرحلة الابتدائية',
                'code' => 'ELM',
                'sort' => 1,
                'grades' => ['الأول الابتدائي', 'الثاني الابتدائي', 'الثالث الابتدائي', 'الرابع الابتدائي', 'الخامس الابتدائي', 'السادس الابتدائي'],
                'subjects' => ['quran', 'islamic', 'arabic', 'math', 'science', 'english', 'social', 'computer', 'pe', 'art', 'life_skills'],
            ],
            'middle' => [
                'name' => 'المرحلة المتوسطة',
                'code' => 'MID',
                'sort' => 2,
                'grades' => ['الأول متوسط', 'الثاني متوسط', 'الثالث متوسط'],
                'subjects' => ['quran', 'islamic', 'arabic', 'math', 'science', 'english', 'social', 'computer', 'pe', 'art', 'life_skills'],
            ],
            'secondary' => [
                'name' => 'المرحلة الثانوية',
                'code' => 'SEC',
                'sort' => 3,
                'grades' => ['الأول ثانوي', 'الثاني ثانوي', 'الثالث ثانوي'],
                'subjects' => ['quran', 'islamic', 'arabic', 'math', 'physics', 'chemistry', 'biology', 'english', 'computer', 'life_skills', 'pe'],
            ],
        ];

        $classrooms = [];

        foreach ($stages as $stageKey => $stageData) {
            $stage = SchoolStage::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'name' => $stageData['name'],
            ]);

            $this->saveModel($stage, [
                'school_id' => (int) $school->id,
                'name' => $stageData['name'],
                'code' => $stageData['code'],
                'sort_order' => $stageData['sort'],
                'is_active' => true,
                'school_day_start_time' => '07:00:00',
                'school_day_end_time' => '13:30:00',
            ]);

            $this->ensureStageTerms($school, $stage, $terms);

            foreach ($stageData['grades'] as $gradeIndex => $gradeName) {
                $grade = SchoolStageGrade::query()->firstOrNew([
                    'school_id' => (int) $school->id,
                    'school_stage_id' => (int) $stage->id,
                    'name' => $gradeName,
                ]);

                $this->saveModel($grade, [
                    'school_id' => (int) $school->id,
                    'school_stage_id' => (int) $stage->id,
                    'name' => $gradeName,
                    'sort_order' => $gradeIndex + 1,
                    'is_active' => true,
                ]);

                $this->ensureGradeTerms($school, $grade);

                foreach (['أ', 'ب'] as $sectionIndex => $section) {
                    $classroomName = "{$gradeName} - شعبة {$section}";
                    $classroom = SchoolClassroom::query()->firstOrNew([
                        'school_id' => (int) $school->id,
                        'school_stage_id' => (int) $stage->id,
                        'name' => $classroomName,
                    ]);

                    $this->saveModel($classroom, [
                        'school_id' => (int) $school->id,
                        'school_stage_id' => (int) $stage->id,
                        'name' => $classroomName,
                        'grade_name' => $gradeName,
                        'code' => sprintf('%s-%02d-%s', $stageData['code'], $gradeIndex + 1, $section),
                        'sort_order' => ($gradeIndex + 1) * 10 + $sectionIndex,
                        'is_active' => true,
                    ]);

                    $classrooms[] = [
                        'model' => $classroom->refresh(),
                        'stage_key' => $stageKey,
                        'grade_name' => $gradeName,
                        'subject_keys' => $stageData['subjects'],
                    ];
                }
            }
        }

        return ['classrooms' => $classrooms];
    }

    /**
     * @param array<string, SchoolTerm> $terms
     */
    private function ensureStageTerms(School $school, SchoolStage $stage, array $terms): void
    {
        if (! Schema::hasTable('school_stage_terms')) {
            return;
        }

        $sort = 1;
        foreach ($terms as $term) {
            $stageTerm = SchoolStageTerm::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'school_stage_id' => (int) $stage->id,
                'name' => $term->name,
            ]);

            $this->saveModel($stageTerm, [
                'school_id' => (int) $school->id,
                'school_stage_id' => (int) $stage->id,
                'name' => $term->name,
                'start_date' => $term->start_date,
                'end_date' => $term->end_date,
                'source' => 'demo',
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
        }
    }

    private function ensureGradeTerms(School $school, SchoolStageGrade $grade): void
    {
        if (! Schema::hasTable('school_stage_grade_terms')) {
            return;
        }

        foreach (['الفصل الدراسي الأول', 'الفصل الدراسي الثاني', 'الفصل الدراسي الثالث'] as $index => $name) {
            $gradeTerm = SchoolStageGradeTerm::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'school_stage_grade_id' => (int) $grade->id,
                'name' => $name,
            ]);

            $this->saveModel($gradeTerm, [
                'school_id' => (int) $school->id,
                'school_stage_grade_id' => (int) $grade->id,
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<string, array{name: string, code: string, branches?: array<int, string>}>
     */
    private function subjectCatalog(): array
    {
        return [
            'quran' => ['name' => 'القرآن الكريم', 'code' => 'QUR'],
            'islamic' => ['name' => 'الدراسات الإسلامية', 'code' => 'ISL'],
            'arabic' => ['name' => 'اللغة العربية', 'code' => 'ARB'],
            'math' => ['name' => 'الرياضيات', 'code' => 'MAT'],
            'science' => ['name' => 'العلوم', 'code' => 'SCI'],
            'english' => ['name' => 'اللغة الإنجليزية', 'code' => 'ENG'],
            'social' => ['name' => 'الدراسات الاجتماعية', 'code' => 'SOC'],
            'computer' => ['name' => 'المهارات الرقمية', 'code' => 'ICT'],
            'pe' => ['name' => 'التربية البدنية', 'code' => 'PE'],
            'art' => ['name' => 'التربية الفنية', 'code' => 'ART'],
            'life_skills' => ['name' => 'المهارات الحياتية', 'code' => 'LIF'],
            'physics' => ['name' => 'الفيزياء', 'code' => 'PHY', 'branches' => ['المسار العام', 'مسار علوم الحاسب']],
            'chemistry' => ['name' => 'الكيمياء', 'code' => 'CHE', 'branches' => ['المسار العام', 'مسار الصحة والحياة']],
            'biology' => ['name' => 'الأحياء', 'code' => 'BIO', 'branches' => ['المسار العام', 'مسار الصحة والحياة']],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $subjectCatalog
     * @return array<string, SchoolSubject>
     */
    private function ensureSubjects(School $school, array $subjectCatalog): array
    {
        $subjects = [];

        foreach ($subjectCatalog as $key => $subjectData) {
            $subject = SchoolSubject::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'name' => $subjectData['name'],
            ]);

            $this->saveModel($subject, [
                'school_id' => (int) $school->id,
                'name' => $subjectData['name'],
                'code' => $subjectData['code'],
                'branches' => $subjectData['branches'] ?? null,
                'is_active' => true,
            ]);

            $subjects[$key] = $subject->refresh();
        }

        return $subjects;
    }

    private function ensureLeaveAndCalendarData(School $school, User $manager): void
    {
        if (Schema::hasTable('school_calendar_settings')) {
            $setting = SchoolCalendarSetting::query()->firstOrNew(['school_id' => (int) $school->id]);
            $this->saveModel($setting, [
                'school_id' => (int) $school->id,
                'week_start_day' => 0,
                'weekly_off_days' => [5, 6],
                'created_by' => (int) $manager->id,
                'updated_by' => (int) $manager->id,
            ]);
        }

        if (Schema::hasTable('school_holidays')) {
            foreach ([
                ['name' => 'إجازة مطولة للاختبار', 'start' => now()->addDays(18), 'end' => now()->addDays(19)],
                ['name' => 'إجازة نهاية الفصل التجريبية', 'start' => now()->addDays(32), 'end' => now()->addDays(36)],
            ] as $holidayRow) {
                $holiday = SchoolHoliday::query()->firstOrNew([
                    'school_id' => (int) $school->id,
                    'name' => $holidayRow['name'],
                    'start_date' => $holidayRow['start']->toDateString(),
                ]);

                $this->saveModel($holiday, [
                    'school_id' => (int) $school->id,
                    'name' => $holidayRow['name'],
                    'start_date' => $holidayRow['start']->toDateString(),
                    'end_date' => $holidayRow['end']->toDateString(),
                    'return_date' => $holidayRow['end']->copy()->addDay()->toDateString(),
                    'notes' => 'إجازة تجريبية ضمن بيانات مدرسة الريادة.',
                    'is_active' => true,
                    'created_by' => (int) $manager->id,
                    'updated_by' => (int) $manager->id,
                ]);
            }
        }

        foreach ([
            ['name' => 'إجازة مرضية', 'requires_attachment' => true],
            ['name' => 'إجازة عائلية', 'requires_attachment' => false],
            ['name' => 'مراجعة طبية', 'requires_attachment' => true],
            ['name' => 'إذن رسمي', 'requires_attachment' => false],
        ] as $leaveTypeRow) {
            $leaveType = SchoolLeaveType::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'name' => $leaveTypeRow['name'],
            ]);

            $this->saveModel($leaveType, [
                'school_id' => (int) $school->id,
                'name' => $leaveTypeRow['name'],
                'requires_attachment' => $leaveTypeRow['requires_attachment'],
                'is_active' => true,
                'created_by' => (int) $manager->id,
                'updated_by' => (int) $manager->id,
            ]);
        }
    }

    /**
     * @param array<int, array{model: SchoolClassroom, stage_key: string, grade_name: string, subject_keys: array<int, string>}> $classrooms
     * @return array{0: array<int, array<int, SchoolStudent>>, 1: array<int, SchoolStudent>}
     */
    private function ensureStudents(School $school, array $classrooms): array
    {
        $firstNames = ['محمد', 'عبدالله', 'سلمان', 'تركي', 'فيصل', 'سارة', 'ريم', 'ليان', 'نورة', 'هيا', 'خالد', 'مريم', 'يوسف', 'جود', 'راكان', 'عبدالعزيز'];
        $familyNames = ['العتيبي', 'القحطاني', 'الدوسري', 'الحربي', 'الشمري', 'المطيري', 'الغامدي', 'الزهراني', 'الشهري', 'المالكي', 'العنزي', 'السبيعي'];

        $studentsByClassroom = [];
        $allStudents = [];
        $counter = 1;

        foreach ($classrooms as $classroomContext) {
            $classroom = $classroomContext['model'];
            $studentsByClassroom[(int) $classroom->id] = [];

            for ($i = 1; $i <= 12; $i++) {
                $fullName = $firstNames[($counter + $i) % count($firstNames)]
                    . ' '
                    . $familyNames[($counter + $i + 3) % count($familyNames)]
                    . ' '
                    . $familyNames[($counter + $i + 7) % count($familyNames)];

                $studentCode = 'RYD-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT);
                $student = SchoolStudent::query()->firstOrNew([
                    'school_id' => (int) $school->id,
                    'student_code' => $studentCode,
                ]);

                $this->saveModel($student, [
                    'school_id' => (int) $school->id,
                    'school_classroom_id' => (int) $classroom->id,
                    'full_name' => $fullName,
                    'student_code' => $studentCode,
                    'national_id' => (string) (9000000000 + $counter),
                    'is_active' => $counter % 41 !== 0,
                ]);

                $student = $student->refresh();
                $studentsByClassroom[(int) $classroom->id][] = $student;
                $allStudents[] = $student;
                $counter++;
            }
        }

        return [$studentsByClassroom, $allStudents];
    }

    /**
     * @param array<int, array{model: SchoolClassroom, stage_key: string, grade_name: string, subject_keys: array<int, string>}> $classrooms
     * @param array<string, SchoolSubject> $subjects
     * @param array<string, User> $teachersBySubject
     * @return array{offerings: array<int, SchoolCourseOffering>}
     */
    private function ensureTeachingAndSchedules(
        School $school,
        User $manager,
        SchoolTerm $currentTerm,
        array $classrooms,
        array $subjects,
        array $teachersBySubject,
    ): array {
        $version = SchoolTimetableVersion::query()->firstOrNew([
            'school_id' => (int) $school->id,
            'school_term_id' => (int) $currentTerm->id,
            'name' => 'جدول الريادة الأسبوعي التجريبي',
        ]);

        $this->saveModel($version, [
            'school_id' => (int) $school->id,
            'school_term_id' => (int) $currentTerm->id,
            'name' => 'جدول الريادة الأسبوعي التجريبي',
            'is_published' => true,
            'published_at' => now()->subDays(3),
            'created_by' => (int) $manager->id,
            'updated_by' => (int) $manager->id,
        ]);

        $offerings = [];
        $sessions = [
            ['07:10:00', '07:55:00'],
            ['08:00:00', '08:45:00'],
            ['09:00:00', '09:45:00'],
            ['09:55:00', '10:40:00'],
            ['10:50:00', '11:35:00'],
            ['11:45:00', '12:30:00'],
        ];

        foreach ($subjects as $key => $subject) {
            $teacher = $teachersBySubject[$key] ?? null;
            if (! $teacher) {
                continue;
            }

            $assignment = SchoolSubjectTeacherAssignment::query()->firstOrNew([
                'school_subject_id' => (int) $subject->id,
                'teacher_user_id' => (int) $teacher->id,
            ]);

            $this->saveModel($assignment, [
                'school_id' => (int) $school->id,
                'school_subject_id' => (int) $subject->id,
                'teacher_user_id' => (int) $teacher->id,
            ]);
        }

        foreach ($classrooms as $classroomContext) {
            $classroom = $classroomContext['model'];
            $stage = $classroom->stage()->first();
            $grade = SchoolStageGrade::query()
                ->where('school_id', (int) $school->id)
                ->where('school_stage_id', (int) $classroom->school_stage_id)
                ->where('name', $classroomContext['grade_name'])
                ->first();

            foreach ($classroomContext['subject_keys'] as $subjectIndex => $subjectKey) {
                $subject = $subjects[$subjectKey] ?? null;
                $teacher = $teachersBySubject[$subjectKey] ?? null;
                if (! $subject || ! $teacher || ! $stage) {
                    continue;
                }

                $offering = SchoolCourseOffering::query()->firstOrNew([
                    'school_term_id' => (int) $currentTerm->id,
                    'school_classroom_id' => (int) $classroom->id,
                    'school_subject_id' => (int) $subject->id,
                ]);

                $this->saveModel($offering, [
                    'school_id' => (int) $school->id,
                    'school_term_id' => (int) $currentTerm->id,
                    'school_stage_id' => (int) $stage->id,
                    'school_stage_grade_id' => $grade ? (int) $grade->id : null,
                    'school_classroom_id' => (int) $classroom->id,
                    'school_subject_id' => (int) $subject->id,
                    'is_active' => true,
                    'usable_in_exams' => true,
                    'sort_order' => $subjectIndex + 1,
                    'alert_before_term_end_days' => 7,
                    'created_by' => (int) $manager->id,
                    'updated_by' => (int) $manager->id,
                ]);

                $teachingAssignment = SchoolTeachingAssignment::query()->firstOrNew([
                    'school_course_offering_id' => (int) $offering->id,
                ]);

                $this->saveModel($teachingAssignment, [
                    'school_id' => (int) $school->id,
                    'school_course_offering_id' => (int) $offering->id,
                    'teacher_user_id' => (int) $teacher->id,
                    'is_active' => true,
                    'can_create_exam' => true,
                    'can_update_exam' => true,
                    'can_delete_exam' => true,
                    'can_approve_exam' => false,
                    'can_enter_exam_scores' => true,
                    'can_edit_exam_scores' => true,
                    'can_use_question_bank' => true,
                    'created_by' => (int) $manager->id,
                    'updated_by' => (int) $manager->id,
                ]);

                if (Schema::hasTable('school_teaching_assignment_classrooms')) {
                    DB::table('school_teaching_assignment_classrooms')->updateOrInsert(
                        [
                            'school_teaching_assignment_id' => (int) $teachingAssignment->id,
                            'school_classroom_id' => (int) $classroom->id,
                        ],
                        [
                            'school_id' => (int) $school->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }

                $offerings[] = $offering->refresh();
            }

            $subjectCycle = array_values($classroomContext['subject_keys']);
            for ($day = 0; $day <= 4; $day++) {
                for ($sessionIndex = 1; $sessionIndex <= 6; $sessionIndex++) {
                    $subjectKey = $subjectCycle[($day + $sessionIndex - 1) % count($subjectCycle)];
                    $subject = $subjects[$subjectKey] ?? null;
                    $teacher = $teachersBySubject[$subjectKey] ?? null;
                    if (! $subject || ! $teacher || ! $stage) {
                        continue;
                    }

                    $schedule = SchoolClassSchedule::query()->firstOrNew([
                        'school_id' => (int) $school->id,
                        'school_term_id' => (int) $currentTerm->id,
                        'school_classroom_id' => (int) $classroom->id,
                        'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                        'day_of_week' => $day,
                        'session_index' => $sessionIndex,
                    ]);

                    $this->saveModel($schedule, [
                        'school_id' => (int) $school->id,
                        'school_term_id' => (int) $currentTerm->id,
                        'school_timetable_version_id' => (int) $version->id,
                        'school_stage_id' => (int) $stage->id,
                        'school_classroom_id' => (int) $classroom->id,
                        'school_subject_id' => (int) $subject->id,
                        'teacher_user_id' => (int) $teacher->id,
                        'schedule_scope' => SchoolClassSchedule::SCOPE_WEEKLY,
                        'day_of_week' => $day,
                        'session_index' => $sessionIndex,
                        'starts_at' => $sessions[$sessionIndex - 1][0],
                        'ends_at' => $sessions[$sessionIndex - 1][1],
                        'notes' => 'حصة تجريبية ضمن جدول مدرسة الريادة.',
                        'is_active' => true,
                        'created_by' => (int) $manager->id,
                        'updated_by' => (int) $manager->id,
                    ]);
                }
            }
        }

        return ['offerings' => $offerings];
    }

    /**
     * @param array<int, array<int, SchoolStudent>> $studentsByClassroom
     */
    private function ensureAttendance(School $school, User $manager, array $studentsByClassroom): void
    {
        $dates = $this->recentSchoolDates(12);

        foreach ($studentsByClassroom as $classroomId => $students) {
            foreach ($dates as $dateIndex => $date) {
                foreach ($students as $studentIndex => $student) {
                    $seed = $studentIndex + $dateIndex + (int) $student->id;
                    $status = SchoolStudentAttendance::STATUS_PRESENT;
                    $reason = null;

                    if ($seed % 29 === 0) {
                        $status = SchoolStudentAttendance::STATUS_LEAVE;
                        $reason = 'إجازة معتمدة ضمن بيانات الاختبار.';
                    } elseif ($seed % 17 === 0) {
                        $status = SchoolStudentAttendance::STATUS_EXCUSED;
                        $reason = 'إذن حضور متأخر ضمن بيانات الاختبار.';
                    } elseif ($seed % 13 === 0) {
                        $status = SchoolStudentAttendance::STATUS_ABSENT;
                    }

                    SchoolStudentAttendance::query()->updateOrCreate(
                        [
                            'school_id' => (int) $school->id,
                            'school_student_id' => (int) $student->id,
                            'attendance_date' => $date->toDateString(),
                        ],
                        $this->onlyColumns('school_student_attendances', [
                            'school_classroom_id' => (int) $classroomId,
                            'status' => $status,
                            'check_in_time' => $status === SchoolStudentAttendance::STATUS_PRESENT ? '07:05:00' : null,
                            'check_out_time' => $status === SchoolStudentAttendance::STATUS_PRESENT ? '12:30:00' : null,
                            'permission_reason' => $reason,
                            'notes' => $status === SchoolStudentAttendance::STATUS_PRESENT ? 'حضور منتظم.' : 'حالة تجريبية للتحليلات.',
                            'recorded_by' => (int) $manager->id,
                            'updated_by' => (int) $manager->id,
                        ]),
                    );
                }
            }
        }
    }

    /**
     * @return array<int, Carbon>
     */
    private function recentSchoolDates(int $count): array
    {
        $dates = [];
        $date = now()->copy();

        while (count($dates) < $count) {
            if (! in_array((int) $date->dayOfWeek, [5, 6], true)) {
                $dates[] = $date->copy();
            }

            $date->subDay();
        }

        return array_reverse($dates);
    }

    /**
     * @param array<int, SchoolStudent> $students
     */
    private function ensureStudentLeaves(School $school, User $manager, array $students): void
    {
        $leaveTypes = SchoolLeaveType::query()
            ->where('school_id', (int) $school->id)
            ->orderBy('id')
            ->get();

        if ($leaveTypes->isEmpty()) {
            return;
        }

        foreach (array_slice($students, 0, 12) as $index => $student) {
            $leaveType = $leaveTypes[$index % $leaveTypes->count()];
            $start = now()->copy()->subDays(10 - ($index % 6));
            $end = $start->copy()->addDay();

            $leave = SchoolStudentLeaveRequest::query()->firstOrNew([
                'school_id' => (int) $school->id,
                'school_student_id' => (int) $student->id,
                'school_leave_type_id' => (int) $leaveType->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

            $this->saveModel($leave, [
                'school_id' => (int) $school->id,
                'school_student_id' => (int) $student->id,
                'school_leave_type_id' => (int) $leaveType->id,
                'source' => 'PRE_APPROVED',
                'status' => $index % 5 === 0 ? 'PENDING' : 'APPROVED',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => 'طلب إجازة تجريبي لاختبار تقارير الإجازات.',
                'approved_by' => $index % 5 === 0 ? null : (int) $manager->id,
                'approved_at' => $index % 5 === 0 ? null : now()->subDays(2),
                'created_by' => (int) $manager->id,
                'updated_by' => (int) $manager->id,
            ]);
        }
    }

    /**
     * @param array<int, array{model: SchoolClassroom, stage_key: string, grade_name: string, subject_keys: array<int, string>}> $classrooms
     * @param array<string, SchoolSubject> $subjects
     * @param array<string, User> $teachersBySubject
     * @param array<int, array<int, SchoolStudent>> $studentsByClassroom
     */
    private function ensureExamsAndScores(
        School $school,
        User $manager,
        SchoolTerm $currentTerm,
        array $classrooms,
        array $subjects,
        array $teachersBySubject,
        array $studentsByClassroom,
    ): void {
        $settings = SchoolExamSetting::query()->firstOrNew(['school_id' => (int) $school->id]);
        $this->saveModel($settings, [
            'school_id' => (int) $school->id,
            'allow_subject_schedule_slot_overlap' => false,
            'exam_day_start_time' => '08:00:00',
            'exam_day_end_time' => '12:00:00',
            'created_by' => (int) $manager->id,
            'updated_by' => (int) $manager->id,
        ]);

        $monthlyTemplate = SchoolExamTemplate::query()->firstOrNew([
            'school_id' => (int) $school->id,
            'name' => 'اختبار شهري تجريبي',
        ]);

        $this->saveModel($monthlyTemplate, [
            'school_id' => (int) $school->id,
            'name' => 'اختبار شهري تجريبي',
            'exam_type' => SchoolExamTemplate::TYPE_MONTHLY,
            'default_max_score' => 100,
            'default_passing_score' => 50,
            'requires_approval' => false,
            'teacher_can_override_max_score' => true,
            'teacher_can_override_passing_score' => true,
            'affects_final_result' => true,
            'is_active' => true,
            'sort_order' => 1,
            'notes' => 'قالب تجريبي لاختبارات مدرسة الريادة.',
            'created_by' => (int) $manager->id,
            'updated_by' => (int) $manager->id,
        ]);

        foreach ($classrooms as $classroomIndex => $classroomContext) {
            $classroom = $classroomContext['model'];
            $stage = $classroom->stage()->first();
            $completedSubjectKeys = array_values(array_intersect(['arabic', 'math'], $classroomContext['subject_keys']));
            $upcomingSubjectKey = in_array('science', $classroomContext['subject_keys'], true) ? 'science' : 'english';

            foreach ($completedSubjectKeys as $subjectOffset => $subjectKey) {
                $subject = $subjects[$subjectKey] ?? null;
                $teacher = $teachersBySubject[$subjectKey] ?? null;
                if (! $subject || ! $teacher || ! $stage) {
                    continue;
                }

                $examDate = now()->copy()->subDays(18 - $subjectOffset);
                $exam = $this->ensureExam(
                    school: $school,
                    manager: $manager,
                    template: $monthlyTemplate,
                    term: $currentTerm,
                    stage: $stage,
                    classroom: $classroom,
                    subject: $subject,
                    teacher: $teacher,
                    title: $subject->name . ' - اختبار شهري - ' . $classroom->name,
                    examDate: $examDate,
                    status: SchoolExam::STATUS_GRADES_RECORDED,
                    completed: true,
                );

                foreach ($studentsByClassroom[(int) $classroom->id] ?? [] as $studentIndex => $student) {
                    $score = 52 + (($studentIndex * 7 + $classroomIndex + $subjectOffset) % 45);
                    if (($studentIndex + $classroomIndex) % 19 === 0) {
                        $score = null;
                    }

                    SchoolExamStudentScore::query()->updateOrCreate(
                        [
                            'school_exam_id' => (int) $exam->id,
                            'school_student_id' => (int) $student->id,
                        ],
                        $this->onlyColumns('school_exam_student_scores', [
                            'school_id' => (int) $school->id,
                            'score' => $score,
                            'attendance_status' => $score === null ? SchoolExamStudentScore::STATUS_ABSENT : SchoolExamStudentScore::STATUS_PRESENT,
                            'notes' => 'درجة تجريبية لاختبار التقارير والتحليلات.',
                            'recorded_by' => (int) $teacher->id,
                            'recorded_at' => now()->subDays(10),
                            'updated_by' => (int) $manager->id,
                            'is_finalized' => true,
                            'finalized_by' => (int) $manager->id,
                            'finalized_at' => now()->subDays(9),
                        ]),
                    );
                }
            }

            $subject = $subjects[$upcomingSubjectKey] ?? null;
            $teacher = $teachersBySubject[$upcomingSubjectKey] ?? null;
            if ($subject && $teacher && $stage) {
                $this->ensureExam(
                    school: $school,
                    manager: $manager,
                    template: $monthlyTemplate,
                    term: $currentTerm,
                    stage: $stage,
                    classroom: $classroom,
                    subject: $subject,
                    teacher: $teacher,
                    title: $subject->name . ' - اختبار قادم - ' . $classroom->name,
                    examDate: now()->copy()->addDays(7 + ($classroomIndex % 5)),
                    status: SchoolExam::STATUS_PUBLISHED,
                    completed: false,
                );
            }
        }
    }

    private function ensureExam(
        School $school,
        User $manager,
        SchoolExamTemplate $template,
        SchoolTerm $term,
        SchoolStage $stage,
        SchoolClassroom $classroom,
        SchoolSubject $subject,
        User $teacher,
        string $title,
        Carbon $examDate,
        string $status,
        bool $completed,
    ): SchoolExam {
        $exam = SchoolExam::query()->firstOrNew([
            'school_id' => (int) $school->id,
            'school_term_id' => (int) $term->id,
            'school_classroom_id' => (int) $classroom->id,
            'school_subject_id' => (int) $subject->id,
            'title' => $title,
        ]);

        $this->saveModel($exam, [
            'school_id' => (int) $school->id,
            'school_exam_template_id' => (int) $template->id,
            'school_term_id' => (int) $term->id,
            'school_stage_id' => (int) $stage->id,
            'school_classroom_id' => (int) $classroom->id,
            'school_subject_id' => (int) $subject->id,
            'teacher_user_id' => (int) $teacher->id,
            'title' => $title,
            'exam_date' => $examDate->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '09:30:00',
            'duration_minutes' => 90,
            'max_score' => 100,
            'passing_score' => 50,
            'status' => $status,
            'requires_approval' => false,
            'allow_subject_schedule_overlap' => false,
            'affects_final_result' => true,
            'room_label' => 'قاعة ' . $classroom->code,
            'notes' => 'اختبار تجريبي ضمن بيانات مدرسة الريادة.',
            'approved_by' => (int) $manager->id,
            'approved_at' => now()->subDays(20),
            'published_at' => $completed ? now()->subDays(19) : now()->subDay(),
            'completed_at' => $completed ? now()->subDays(9) : null,
            'closed_at' => $completed ? now()->subDays(8) : null,
            'is_active' => true,
            'created_by' => (int) $teacher->id,
            'updated_by' => (int) $manager->id,
        ]);

        return $exam->refresh();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function saveModel(Model $model, array $attributes): Model
    {
        $model->forceFill($this->onlyColumns($model->getTable(), $attributes));
        $model->save();

        return $model->refresh();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function onlyColumns(string $table, array $attributes): array
    {
        if (! isset($this->columnCache[$table])) {
            $this->columnCache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return array_intersect_key($attributes, array_flip($this->columnCache[$table]));
    }
}
