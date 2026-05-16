<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_FROZEN = 'FROZEN';
    public const STATUS_CANCELED = 'CANCELED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'deleted_at' => 'datetime',
            'base_price' => 'decimal:2',
            'included_users_count' => 'integer',
            'extra_user_monthly_price' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function isCurrentForSchool(int $schoolId, ?Carbon $now = null): bool
    {
        $now ??= now();

        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if (! $this->ends_at || $this->ends_at->lte($now)) {
            return false;
        }

        return $this->school_id === null || (int) $this->school_id === $schoolId;
    }

    public function scopeCurrentForSchool(Builder $query, int $schoolId, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $scope) use ($schoolId): void {
                $scope->where('school_id', $schoolId)
                    ->orWhereNull('school_id');
            })
            ->where(function (Builder $scope) use ($now): void {
                $scope->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);
    }

    public function userAddons()
    {
        return $this->hasMany(SubscriptionUserAddon::class, 'subscription_id');
    }
}
