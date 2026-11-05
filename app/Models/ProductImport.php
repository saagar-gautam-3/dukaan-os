<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends Model
{
    use Auditable, BelongsToShop;

    protected $fillable = [
        'shop_id',
        'user_id',
        'original_name',
        'stored_path',
        'file_hash',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'skipped_rows',
        'imported_rows',
        'errors_json',
        'warnings_json',
        'valid_rows_json',
    ];

    protected function casts(): array
    {
        return [
            'errors_json' => 'array',
            'warnings_json' => 'array',
            'valid_rows_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
