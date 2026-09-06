<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 *
 * 'A' / 'B' — pooled per-school, not per-standard, matching
 * 05-database-map.md §2's finding that this is how GegoK12 already models
 * it (kept as-is; a reasonable normalization, not a defect).
 */
class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classSections(): HasMany
    {
        return $this->hasMany(ClassSection::class);
    }
}
