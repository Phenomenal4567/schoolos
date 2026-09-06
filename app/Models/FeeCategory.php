<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Design ref: 22-schoolos-finance-schema.md §1
 *
 * School-scoped, admin-authored (discovery §10.1) — not a fixed
 * platform enum. 'rolled_over_debt' is the one system-reserved key
 * (D12), seeded per school by FeeRolloverRepository the first time a
 * school rolls over debt, never admin-creatable directly.
 */
class FeeCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'key',
        'label',
        'is_system_reserved',
    ];

    protected function casts(): array
    {
        return [
            'is_system_reserved' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
