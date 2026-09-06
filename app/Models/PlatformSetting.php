<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
 * billing addition.
 *
 * Singleton row (see migration's own doc comment) — current() is the
 * only accessor, firstOrCreate()ing id 1 so a fresh install (or a test
 * that never touches this table) still gets the documented 14-day
 * default rather than a missing-row error.
 */
class PlatformSetting extends Model
{
    protected $fillable = [
        'trial_days',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['trial_days' => 14]);
    }
}
