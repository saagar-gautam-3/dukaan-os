<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use BelongsToShop, HasFactory;

    /**
     * Class D: intentionally NOT immutable.
     *
     * The record is created before the operation starts (with result_id/type
     * NULL) and updated exactly once after the operation succeeds to attach the
     * result. This single controlled update is the whole point of the table.
     * No further updates are allowed after the result is attached, but we
     * enforce that at the application layer (see IdempotencyService) rather
     * than with a model event, because the model has no way to know whether the
     * update is the initial "attach result" update or a rogue second update.
     */
    protected $fillable = [
        'user_id',
        'key',
        'action',
        'result_id',
        'result_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
