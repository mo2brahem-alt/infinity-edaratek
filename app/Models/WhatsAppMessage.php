<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNCERTAIN = 'uncertain';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'whatsapp_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'attempts' => 'integer',
            'accepted_at' => 'datetime',
            'dispatch_claimed_at' => 'datetime',
            'dispatch_uncertain_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function applyProviderStatus(string $status, ?string $failureCode = null, ?string $failureReason = null): bool
    {
        $status = strtolower(trim($status));

        if (! array_key_exists($status, self::statusRanks())) {
            return false;
        }

        if (! $this->canTransitionTo($status)) {
            return false;
        }

        $updates = ['status' => $status];
        $timestampColumn = $status . '_at';

        if (in_array($timestampColumn, ['accepted_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at'], true)) {
            $updates[$timestampColumn] = now();
        }

        if ($status === self::STATUS_FAILED) {
            $updates['failure_code'] = $failureCode;
            $updates['failure_reason'] = $failureReason;
        }

        return $this->forceFill($updates)->save();
    }

    private function canTransitionTo(string $status): bool
    {
        if ($status === (string) $this->status) {
            return false;
        }

        if ($status === self::STATUS_FAILED) {
            return ! in_array($this->status, [self::STATUS_READ, self::STATUS_CANCELLED, self::STATUS_UNCERTAIN], true);
        }

        if ($this->status === self::STATUS_UNCERTAIN) {
            return false;
        }

        return (self::statusRanks()[$status] ?? -1) >= (self::statusRanks()[(string) $this->status] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    private static function statusRanks(): array
    {
        return [
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 1,
            self::STATUS_ACCEPTED => 2,
            self::STATUS_SENT => 3,
            self::STATUS_DELIVERED => 4,
            self::STATUS_READ => 5,
            self::STATUS_FAILED => 6,
            self::STATUS_UNCERTAIN => 7,
            self::STATUS_CANCELLED => 8,
        ];
    }
}
