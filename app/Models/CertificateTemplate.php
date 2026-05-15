<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use SoftDeletes;

    public const TYPE_APPRECIATION = 'appreciation';
    public const TYPE_EXCELLENCE = 'excellence';
    public const TYPE_ATTENDANCE = 'attendance';
    public const TYPE_PARTICIPATION = 'participation';
    public const TYPE_COMPLETION = 'completion';
    public const TYPE_QURAN = 'quran';
    public const TYPE_DISCIPLINE = 'discipline';
    public const TYPE_ACTIVITY_EXCELLENCE = 'activity_excellence';
    public const TYPE_STAGE_COMPLETION = 'stage_completion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'layout_json' => 'array',
            'title_style_json' => 'array',
            'student_name_style_json' => 'array',
            'body_style_json' => 'array',
            'date_style_json' => 'array',
            'signature_style_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function certificates()
    {
        return $this->hasMany(StudentCertificate::class, 'certificate_template_id');
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
