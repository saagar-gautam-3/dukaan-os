<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use Auditable, BelongsToShop, HasFactory;

    protected $fillable = [
        'branch_id',
        'opened_by',
        'closed_by',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'status' => CashSessionStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    /**
     * Compute real-time expected cash in drawer:
     * opening_cash + cash_inflows - cash_outflows.
     */
    public function computeExpectedCash(): string
    {
        $expected = (string) $this->opening_cash;

        $movements = $this->movements()->get();

        foreach ($movements as $m) {
            $type = $m->type instanceof CashMovementType ? $m->type : CashMovementType::from($m->type);
            if ($type === CashMovementType::Opening) {
                continue;
            }

            if ($type->isInflow()) {
                $expected = bcadd($expected, (string) $m->amount, 2);
            } else {
                $expected = bcsub($expected, (string) $m->amount, 2);
            }
        }

        return $expected;
    }
}
