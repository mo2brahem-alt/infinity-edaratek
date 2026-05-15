<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const SUPERVISION_STATUS_SUSPENDED = 'SUSPENDED';
    public const SUPERVISION_STATUS_WAITING_MANAGER_APPROVAL = 'WAITING_MANAGER_APPROVAL';
    public const SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM = 'WAITING_SUPERVISOR_CONFIRM';
    public const SUPERVISION_STATUS_ACTIVE_ASSOCIATION = 'ACTIVE_ASSOCIATION';
    public const TYPE_BOYS = 'boys';
    public const TYPE_GIRLS = 'girls';
    public const TYPE_MIXED = 'mixed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_data_imported_at' => 'datetime',
        ];
    }

    public static function availableSchoolTypes(): array
    {
        return [
            self::TYPE_BOYS,
            self::TYPE_GIRLS,
            self::TYPE_MIXED,
        ];
    }

    public function directorate()
    {
        return $this->belongsTo(EducationalDirectorate::class, 'directorate_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function defaultDataImporter()
    {
        return $this->belongsTo(User::class, 'default_data_imported_by');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'school_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'school_id');
    }

    public function subscriptionUserAddons()
    {
        return $this->hasMany(SubscriptionUserAddon::class, 'school_id');
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(SchoolSupervisorAssignment::class, 'school_id');
    }

    public function associationRequests()
    {
        return $this->hasMany(AssociationRequest::class, 'school_id');
    }

    public function supervisionRequests()
    {
        return $this->hasMany(SchoolSupervisionRequest::class, 'school_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'school_id');
    }

    public function stages()
    {
        return $this->hasMany(SchoolStage::class, 'school_id');
    }

    public function educationStages()
    {
        return $this->belongsToMany(EducationStage::class, 'education_stage_school')
            ->withTimestamps()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function classrooms()
    {
        return $this->hasMany(SchoolClassroom::class, 'school_id');
    }

    public function students()
    {
        return $this->hasMany(SchoolStudent::class, 'school_id');
    }

    public function studentAttendances()
    {
        return $this->hasMany(SchoolStudentAttendance::class, 'school_id');
    }

    public function leaveTypes()
    {
        return $this->hasMany(SchoolLeaveType::class, 'school_id');
    }

    public function studentLeaveRequests()
    {
        return $this->hasMany(SchoolStudentLeaveRequest::class, 'school_id');
    }

    public function studentLeaveAttachments()
    {
        return $this->hasMany(SchoolStudentLeaveAttachment::class, 'school_id');
    }

    public function calendarSetting()
    {
        return $this->hasOne(SchoolCalendarSetting::class, 'school_id');
    }

    public function holidays()
    {
        return $this->hasMany(SchoolHoliday::class, 'school_id');
    }

    public function terms()
    {
        return $this->hasMany(SchoolTerm::class, 'school_id');
    }

    public function timetableVersions()
    {
        return $this->hasMany(SchoolTimetableVersion::class, 'school_id');
    }

    public function academicYears()
    {
        return $this->hasMany(SchoolAcademicYear::class, 'school_id');
    }

    public function subjects()
    {
        return $this->hasMany(SchoolSubject::class, 'school_id');
    }

    public function subjectTeacherAssignments()
    {
        return $this->hasMany(SchoolSubjectTeacherAssignment::class, 'school_id');
    }

    public function classSchedules()
    {
        return $this->hasMany(SchoolClassSchedule::class, 'school_id');
    }

    public function teacherAvailabilities()
    {
        return $this->hasMany(SchoolTeacherAvailability::class, 'school_id');
    }

    public function courseOfferings()
    {
        return $this->hasMany(SchoolCourseOffering::class, 'school_id');
    }

    public function teachingAssignments()
    {
        return $this->hasMany(SchoolTeachingAssignment::class, 'school_id');
    }

    public function examSettings()
    {
        return $this->hasOne(SchoolExamSetting::class, 'school_id');
    }

    public function examTemplates()
    {
        return $this->hasMany(SchoolExamTemplate::class, 'school_id');
    }

    public function exams()
    {
        return $this->hasMany(SchoolExam::class, 'school_id');
    }

    public function questionBankItems()
    {
        return $this->hasMany(SchoolQuestionBankItem::class, 'school_id');
    }

    public function examScores()
    {
        return $this->hasMany(SchoolExamStudentScore::class, 'school_id');
    }

    public function certificateTemplates()
    {
        return $this->hasMany(CertificateTemplate::class, 'school_id');
    }

    public function studentCertificates()
    {
        return $this->hasMany(StudentCertificate::class, 'school_id');
    }

    public function certificateSignatures()
    {
        return $this->hasMany(SchoolCertificateSignature::class, 'school_id');
    }
}
