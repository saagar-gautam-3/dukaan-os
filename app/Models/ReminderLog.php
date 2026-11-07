<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records each reminder sent to a customer.
 * Phone numbers are always stored masked (e.g. 98****1234).
 */
class ReminderLog extends Model
{
    use Auditable, BelongsToShop, HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'channel',
        'status',
        'amount',
        'phone_masked',
        'message',
        'message_preview',
        'reference_type',
        'reference_id',
        'failure_reason',
        'sent_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Mask a phone number: keep first 2 and last 4 digits.
     * e.g. 9841234567 → 98****4567
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 6) {
            return $phone;
        }

        $length = strlen($phone);
        $keep = 2; // keep first 2 chars
        $keepEnd = 4; // keep last 4 chars
        $maskLength = $length - $keep - $keepEnd;

        if ($maskLength <= 0) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, $keep).str_repeat('*', $maskLength).substr($phone, -$keepEnd);
    }
}
