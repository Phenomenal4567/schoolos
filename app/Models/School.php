<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §1
 * Decision ref: 16-schoolos-decisions-register.md D2
 *
 * `status` is a real enum('active', 'suspended'), not a boolean — GegoK12
 * used a boolean here (05-database-map.md §1); D2 needs a named third
 * state to be addable later without a breaking column-type change, and a
 * boolean can't express "why" a school is down the way an enum label can.
 *
 * `status` is never written directly by application code outside
 * AuthenticationService::setSchoolStatus() (D2) — that method is what
 * pairs the write with revoking sessions/tokens and an audit_logs row.
 * Nothing else should touch this column.
 *
 * `setup_status`/`setup_step` (SchoolOS Account Creation & Onboarding
 * plan, §1): first-run setup-wizard progress, not a security boundary —
 * excluded from $fillable anyway, matching `status`'s own pattern, since
 * Admin\SetupWizardController is the one intended writer and using
 * forceFill()/direct assignment there (like AuthenticationService does
 * for `status`) keeps that explicit rather than incidental.
 *
 * `short_code` (Track 6b, 16 D12): the stable abbreviation
 * IdentifierService::resolveShortCode() derives from `name` and persists
 * the first time this school needs a student_id/staff_id generated —
 * see that method's doc comment for why it's write-once-lazily rather
 * than backfilled at migration time.
 *
 * `education_levels`/`grading_system`/`allow_manual_promotion`/
 * `enabled_modules` (SchoolOS Onboarding & Authentication UI): collected
 * by the public self-service onboarding flow
 * (Public\SchoolOnboardingController). `grading_system` is a stored
 * preference only — it is not wired into ExamMark/ExamResultService,
 * which compute in raw marks_obtained/max_marks regardless of this
 * label; do not assume changing it changes grading behavior anywhere.
 * `allow_manual_promotion` is a real gate — see
 * Admin\PromotionController::override(). `enabled_modules` should
 * always be read via enabledModules() below, never the raw attribute,
 * so a null value (every school created before this column existed, or
 * via the still-supported SuperAdmin\SchoolController path) resolves to
 * "everything enabled" rather than silently losing features.
 *
 * `billing_plan`/`trial_ends_at` (demo/trial billing addition — "school
 * admin account creation will be a demo for 14 days... school admin can
 * decide account should be demo or they wanna pay at once"): a school
 * chooses 'trial' (time-limited, length taken from
 * PlatformSetting::current()->trial_days at signup) or 'paid' (no
 * expiry) in Public\SchoolOnboardingController. Nothing here actually
 * collects payment — see isTrialExpired()'s own doc comment.
 */
class School extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The fixed vocabulary for `enabled_modules` — matches the "Modules
     * & Features" onboarding screen's cards exactly. 'library' and
     * 'inventory' are stored and displayed like the other five, but gate
     * nothing anywhere in the app: no library or inventory domain exists
     * in SchoolOS yet (confirmed against the full discovery-hierarchy
     * status doc), so there is nothing real for them to enable/disable.
     */
    public const MODULE_KEYS = [
        'student_information',
        'attendance',
        'academics',
        'fees',
        'reports',
        'library',
        'inventory',
    ];

    protected $fillable = [
        'name',
        'initials',
        'logo_path',
        'short_code',
        'email',
        'phone',
        'location',
        'google_maps_url',
        'school_type',
        'fee_overdue_reminder_days',
        'working_days',
        'education_levels',
        'grading_system',
        'allow_manual_promotion',
        'enabled_modules',
        'billing_plan',
        'trial_ends_at',
    ];

    // 'status' deliberately excluded from $fillable — see class doc block.
    // AuthenticationService::setSchoolStatus() writes it directly on the
    // model instance, not via mass assignment.

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'setup_status' => 'string',
            'setup_step' => 'integer',
            'fee_overdue_reminder_days' => 'integer',
            'working_days' => 'array',
            'education_levels' => 'array',
            'allow_manual_promotion' => 'boolean',
            'enabled_modules' => 'array',
            'billing_plan' => 'string',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * True only for a 'trial' school whose trial_ends_at has passed.
     * A 'paid' school, or a 'trial' school with no trial_ends_at set at
     * all (every school that existed before this column, since the
     * migration leaves it null by default) is never expired —
     * AuthenticationService::authenticate() is this method's one caller,
     * and treats an expired trial like SchoolSuspendedFailure (D2):
     * blocked before the password is even checked, told plainly why.
     * This does not delete or hide any school data, matching D2's own
     * "suspension is read-preserving" posture — it only blocks login.
     */
    public function isTrialExpired(): bool
    {
        return $this->billing_plan === 'trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    /**
     * @return list<string> the enabled module keys — every key in
     *         MODULE_KEYS when `enabled_modules` was never set, so a
     *         school with no recorded preference is never treated as
     *         having disabled anything.
     */
    public function enabledModules(): array
    {
        return $this->enabled_modules ?? self::MODULE_KEYS;
    }

    public function moduleEnabled(string $key): bool
    {
        return in_array($key, $this->enabledModules(), true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
