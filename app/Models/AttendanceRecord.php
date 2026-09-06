<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §5
 *
 * The class_section_id and student_id columns are what let
 * ScopeService::relationshipScope() cover this model generically — a
 * teacher's assigned class sections gate class_section_id, a parent's
 * linked children (and a student, themselves) gate student_id. See that
 * class's doc comments; no change was needed there to support this model.
 *
 * Uniqueness is per (student_id, date, session) at the schema level — see
 * the 2026_08_26_000001 migration's doc comment for why session is part
 * of the key, not just date.
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'student_id',
        'recorded_by',
        'date',
        'session',
        'status',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d, not the bare 'date' cast: a bare 'date' cast
            // still serializes for storage using the connection grammar's
            // default *datetime* format ('Y-m-d H:i:s'), not a pure date —
            // Eloquent only trims that down to a date on the read side. A
            // real DATE column (MySQL, this table's production target)
            // silently truncates that on INSERT/UPDATE, so it's invisible
            // there, but SQLite has no column-type enforcement and stores
            // the full "Y-m-d 00:00:00" string verbatim. mark()'s
            // correction lookup queries `where('date', $date)` with a
            // plain "Y-m-d" string (matching what the date input/route
            // validation actually sends — see AttendanceController), which
            // then never matches the stored value under SQLite: every
            // correction attempt reads back null and tries to INSERT
            // again, hitting the (student_id, date, session) unique
            // constraint. Forcing Y-m-d here makes storage match query
            // input on every driver, not just ones that happen to
            // normalize it for us.
            'date' => 'date:Y-m-d',
            'session' => 'string',
            'status' => 'string',
            'recorded_at' => 'datetime',
        ];
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}