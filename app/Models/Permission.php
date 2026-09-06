<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §2
 * Decision ref: 16-schoolos-decisions-register.md D4
 *
 * Absorbs GegoK12's Laratrust sub-roles (principal / leave_checker /
 * leave_applier — confirmed conceptually sound per
 * 07-teacher-domain-map.md §3) as permissions attached to a role, rather
 * than as a second parallel role system running beside the first.
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
