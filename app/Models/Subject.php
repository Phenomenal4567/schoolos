<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 *
 * NEW in v2 — v1's `class_teacher_assignments.subject_id` referenced this
 * table without ever defining it (audit §11/§12, a genuine dangling FK,
 * not a documentation oversight). Defining it here is what makes that FK
 * resolve.
 */
class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'code',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }
}
