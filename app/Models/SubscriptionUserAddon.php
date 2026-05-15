<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionUserAddon extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CANCELED = 'CANCELED';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'added_seats_count' => 'integer',
            'extra_user_monthly_price' => 'decimal:2',
            'daily_price' => 'decimal:2',
            'remaining_days' => 'integer',
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
