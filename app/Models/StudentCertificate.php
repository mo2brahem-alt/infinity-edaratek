<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentCertificate extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';
    public const RECIPIENT_STUDENT = 'student';
    public const RECIPIENT_USER = 'user';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rendered_data_json' => 'array',
            'recipient_context_json' => 'array',
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'school_student_id');
    }

    public function recipientUser()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function signature()
    {
        return $this->belongsTo(SchoolCertificateSignature::class, 'school_certificate_signature_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function academicYear()
    {
        return $this->belongsTo(SchoolAcademicYear::class, 'school_academic_year_id');
    }

    public function term()
    {
        return $this->belongsTo(SchoolTerm::class, 'school_term_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClassroom::class, 'school_classroom_id');
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }
}
