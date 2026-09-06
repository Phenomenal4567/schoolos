<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §2
 * Decision ref: 16-schoolos-decisions-register.md D4
 *
 * Replaces GegoK12's bare `usergroup_id` integer plus the parallel
 * Laratrust role system (F8/F9) with one real lookup table. `key` is the
 * stable, code-facing value ('teacher', 'student', ...) that
 * ScopeService::relationshipScope() and ClassSectionRepository match
 * against — `label` is display text only, never used in a conditional.
 *
 * D4 (resolved): global platform vocabulary, no `school_id` here — a role
 * means the same thing at every school.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
