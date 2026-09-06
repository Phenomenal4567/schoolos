<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §2
 * Decision refs: 16-schoolos-decisions-register.md D1, D5
 *
 * One `users` table for every role (super_admin/school_admin/teacher/
 * student/parent/...), distinguished by `role_id` — not GegoK12's
 * specialized `StudentUser`/`TeacherUser`/`ParentUser` subclasses, which
 * F9 found don't actually enforce their own type. No subclasses exist
 * here for that reason; `role()` below is the one place "what kind of
 * user is this" gets answered.
 *
 * `email` / `mobile_no` / `registration_number` are all unique and all
 * nullable, per F5/F6's fix — `name` is intentionally not a lookup field
 * at all (see AuthenticationService::resolveIdentifier()).
 *
 * D1: `school_id` is a single NOT NULL FK — no multi-school membership.
 * D5: `school_id` is nullable only for role = 'super_admin', enforced at
 * the application layer (AuthenticationService / ScopeService), not a
 * conditional DB constraint — this model does not itself enforce that;
 * see ScopeService::tenantScope() for the one place null school_id is
 * treated as an exemption.
 *
 * `student_id` / `staff_id` (Track 6b, 16 D12): the discovery §2.2/§5
 * ID-card identifiers, distinct from the free-form login identifier
 * `registration_number` above — see the migration that added these two
 * columns for why they're kept separate. Left in $fillable like
 * `registration_number` (no invariant beyond "assigned once," unlike
 * `class_teacher_id`'s role check below), but IdentifierService's
 * generateStudentId()/generateStaffId() are the only application code
 * that should ever write them — see that class's doc comment.
 *
 * `photo_path` (Track 6b): the ID-card photo, doubling as D13's
 * face-verification reference image. See the same migration's doc
 * comment for why one column serves both.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    // TODO(Phase 1): once Sanctum is installed (composer require
    // laravel/sanctum), add `use Laravel\Sanctum\HasApiTokens;` and the
    // `HasApiTokens` trait here — AuthenticationService::issueSession()
    // needs it for API token issuance. Not added yet since the package
    // isn't confirmed installed in this scaffolding pass.

    protected $fillable = [
        'school_id',
        'role_id',
        'name',
        'email',
        'mobile_no',
        'registration_number',
        'student_id',
        'staff_id',
        'photo_path',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'string',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * class_sections this user teacher-owns as class_teacher_id. Read-only
     * relationship — never used to write class_teacher_id; that column's
     * only writer is ClassSectionRepository::assignClassTeacher() (D3).
     */
    public function classSectionsAsClassTeacher(): HasMany
    {
        return $this->hasMany(ClassSection::class, 'class_teacher_id');
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class, 'teacher_id');
    }

    public function auditLogsAsActor(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function staffAttendanceRecords(): HasMany
    {
        return $this->hasMany(StaffAttendanceRecord::class);
    }

    /**
     * One row per staff user_id (see StaffProfile's own doc comment).
     * Added for EnsureCanManageTimetable, which needs
     * $user->staffProfile?->can_manage_timetable without every call site
     * re-querying StaffProfile::where('user_id', ...) by hand.
     */
    public function staffProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }
}
