<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'fiscal_year',
        'prefix',
        'last_number',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
