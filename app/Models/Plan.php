<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public const ROLE_SUPERVISOR = 'SUPERVISOR';
    public const ROLE_SCHOOL_MANAGER = 'SCHOOL_MANAGER';
    public const ROLE_MANAGER_LEGACY = 'MANAGER';

    public const BILLING_MONTHLY = 'MONTHLY';
    public const BILLING_YEARLY = 'YEARLY';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'included_users_count' => 'integer',
            'extra_user_monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
            'limits' => 'array',
        ];
    }

    public static function normalizeRoleType(?string $roleType): ?string
    {
        if ($roleType === null) {
            return null;
        }

        $normalized = strtoupper(trim($roleType));
        if ($normalized === '') {
            return null;
        }

        if ($normalized === self::ROLE_MANAGER_LEGACY) {
            return self::ROLE_SCHOOL_MANAGER;
        }

        return $normalized;
    }

    public static function roleAliases(string $roleType): array
    {
        $normalized = self::normalizeRoleType($roleType);

        return match ($normalized) {
            self::ROLE_SCHOOL_MANAGER => [self::ROLE_SCHOOL_MANAGER, self::ROLE_MANAGER_LEGACY],
            self::ROLE_SUPERVISOR => [self::ROLE_SUPERVISOR],
            default => array_values(array_filter([$normalized])),
        };
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function priceForBillingCycle(?string $billingCycle): string
    {
        $cycle = strtoupper((string) ($billingCycle ?: $this->billing_cycle ?: self::BILLING_MONTHLY));

        if ($cycle === self::BILLING_YEARLY) {
            return (string) ($this->yearly_price ?? $this->price ?? 0);
        }

        return (string) ($this->monthly_price ?? $this->price ?? 0);
    }
}
