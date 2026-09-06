<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Design ref: school_management_system_discovery_hierarchy.md §2.2
 * ("SCH + ADE + SS2 + 001"), §5/§6.1 (staff have a "Staff ID" field,
 * format unspecified). Decision ref: 16-schoolos-decisions-register.md
 * D12. Execution ref: 20-phase-6-8-execution-prompt.md §4.
 *
 * The one write path for users.student_id/users.staff_id and
 * schools.short_code — matches this bundle's established shape
 * (ClassSectionRepository::assignClassTeacher() for class_teacher_id,
 * IdentifierService here for these three columns) of a single method
 * owning a column no controller or mass-assignment call should set
 * directly, even though none of the three is excluded from $fillable
 * (see User's/School's own doc comments on why: no role-validation
 * invariant like D3's, just a "don't generate it twice" one, enforced
 * here by checking-then-writing under a row lock rather than a DB
 * constraint shaped to catch it).
 *
 * Discovery §2.2 only specifies the STUDENT format. No staff_id format
 * is given anywhere in the discovery doc (§5/§6.1 name "Staff ID" as a
 * field, never a shape) — generateStaffId()'s format below is this
 * class's own judgment call, deliberately mirroring the student format's
 * shape (school + person + category + sequence) for consistency rather
 * than inventing an unrelated one. Revisit if product specifies
 * otherwise; nothing downstream parses either string's structure, so
 * changing the format later doesn't require a migration, only re-running
 * generation for future users (already-issued IDs are stable, matching
 * why school_id's short_code is persisted rather than recomputed).
 */
class IdentifierService
{
    /**
     * The one write path for users.student_id. Idempotent — a student
     * who already has one is returned unchanged, matching
     * ClassTeacherAssignmentRepository::assign()'s "idempotent
     * re-statement of an already-true fact" precedent rather than
     * EnrollmentRepository::enroll()'s "second call is an error" one:
     * calling this twice for the same student is expected (e.g. a
     * caller that doesn't track whether generation already ran), not a
     * business-rule violation.
     *
     * Format: {school short_code}{first 3 letters of surname}
     * {standard name, alnum only}{section name}{3-digit sequence},
     * e.g. "GHSADESS2A001". "surname" is taken as the last
     * whitespace-separated token of `name` — 13 §2 has no separate
     * surname column, so this is a best-effort split, not a guarantee
     * (a single-word name uses that whole word). The sequence number is
     * scoped to (school, standard, section) — how many students already
     * have a student_id for that same class — matching discovery's
     * "SS2 + 001" example reading as "the Nth student assigned into this
     * class," and is computed under a row lock on $classSection so two
     * concurrent enrollments into the same class can't compute the same
     * sequence number, mirroring assignClassTeacher()'s lockForUpdate()
     * reasoning.
     *
     * @throws \InvalidArgumentException if $student's role doesn't
     *         resolve to 'student', or if $classSection doesn't belong
     *         to $student's school.
     */
    public function generateStudentId(User $student, ClassSection $classSection): string
    {
        if ($student->student_id !== null) {
            return $student->student_id;
        }

        if (($student->role->key ?? null) !== 'student') {
            throw new \InvalidArgumentException(
                "IdentifierService::generateStudentId(): user #{$student->id} is not a student."
            );
        }

        if ($classSection->school_id !== $student->school_id) {
            throw new \InvalidArgumentException(
                "IdentifierService::generateStudentId(): class_section #{$classSection->id} belongs to "
                . "school #{$classSection->school_id}, not student #{$student->id}'s school #{$student->school_id}."
            );
        }

        return DB::transaction(function () use ($student, $classSection) {
            // Lock the class_section row as the shared serialization
            // point for "how many students in this class already have a
            // student_id" — two concurrent calls for the same class
            // section block on this lock rather than both computing the
            // same next sequence number, same shape as
            // assignClassTeacher()'s lockForUpdate() on ClassSection.
            $classSection = ClassSection::lockForUpdate()->findOrFail($classSection->id);

            // Re-check after acquiring the lock: another concurrent call
            // may have already generated this student's id while this
            // one waited.
            $fresh = User::lockForUpdate()->findOrFail($student->id);
            if ($fresh->student_id !== null) {
                return $fresh->student_id;
            }

            $shortCode = $this->resolveShortCode($classSection->school);
            $classLabel = $this->classLabel($classSection);
            $prefix = $shortCode . $this->surnameFragment($fresh->name) . $classLabel;

            $sequence = User::where('school_id', $classSection->school_id)
                ->where('student_id', 'like', $prefix . '%')
                ->count() + 1;

            $studentId = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $fresh->student_id = $studentId;
            $fresh->save();

            return $studentId;
        });
    }

    /**
     * The one write path for users.staff_id. Idempotent, same reasoning
     * as generateStudentId(). Unlike the student case, staff have no
     * class/section to scope a sequence to, and no discovery-specified
     * format exists at all — see this class's doc comment. Format:
     * {school short_code}{first 3 letters of surname}
     * {3-letter role abbreviation}{3-digit sequence}, sequence scoped to
     * (school, role) under a row lock on $staff's School row (the
     * closest available shared lock target, mirroring
     * generateStudentId()'s lock-the-parent-row shape since there's no
     * per-role row to lock instead).
     *
     * @throws \InvalidArgumentException if $staff's role resolves to
     *         'student', 'parent', or 'super_admin' — none of those are
     *         staff-attendance-eligible roles (see
     *         StaffAttendanceRepository::STAFF_ROLE_KEYS), so none needs
     *         a staff_id.
     */
    public function generateStaffId(User $staff): string
    {
        if ($staff->staff_id !== null) {
            return $staff->staff_id;
        }

        $roleKey = $staff->role->key ?? null;

        if (in_array($roleKey, ['student', 'parent', 'super_admin'], true) || $roleKey === null) {
            throw new \InvalidArgumentException(
                "IdentifierService::generateStaffId(): role '{$roleKey}' is not eligible for a staff_id."
            );
        }

        if ($staff->school_id === null) {
            throw new \InvalidArgumentException(
                "IdentifierService::generateStaffId(): user #{$staff->id} has no school_id to generate a staff_id against."
            );
        }

        return DB::transaction(function () use ($staff, $roleKey) {
            $school = School::lockForUpdate()->findOrFail($staff->school_id);

            $fresh = User::lockForUpdate()->findOrFail($staff->id);
            if ($fresh->staff_id !== null) {
                return $fresh->staff_id;
            }

            $shortCode = $this->resolveShortCode($school);
            $roleAbbrev = $this->roleAbbreviation($roleKey);
            $prefix = $shortCode . $this->surnameFragment($fresh->name) . $roleAbbrev;

            $sequence = User::where('school_id', $school->id)
                ->where('staff_id', 'like', $prefix . '%')
                ->count() + 1;

            $staffId = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $fresh->staff_id = $staffId;
            $fresh->save();

            return $staffId;
        });
    }

    /**
     * Derives and persists a school's short_code the first time it's
     * needed, from the initials of each word in `name` (e.g. "Greenwood
     * High School" -> "GHS"), uppercased, alphanumeric-only. Persisted
     * rather than recomputed on every call — see the short_code
     * migration's doc comment for why a renamed school must not change
     * already-issued IDs.
     */
    public function resolveShortCode(School $school): string
    {
        if ($school->short_code !== null) {
            return $school->short_code;
        }

        $initials = collect(preg_split('/\s+/', trim($school->name)))
            ->filter()
            ->map(fn (string $word) => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1)))
            ->implode('');

        $shortCode = $initials !== '' ? $initials : 'SCH';

        $school->short_code = $shortCode;
        $school->save();

        return $shortCode;
    }

    private function surnameFragment(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $surname = end($parts) ?: $name;

        $alnum = preg_replace('/[^A-Za-z]/', '', $surname);

        return Str::upper(Str::substr($alnum !== '' ? $alnum : 'XXX', 0, 3));
    }

    private function classLabel(ClassSection $classSection): string
    {
        $standard = preg_replace('/[^A-Za-z0-9]/', '', $classSection->standard->name ?? '');
        $section = preg_replace('/[^A-Za-z0-9]/', '', $classSection->section->name ?? '');

        return Str::upper($standard . $section);
    }

    /**
     * Not discovery-specified (see class doc comment) — a 3-letter
     * abbreviation per seeded Role key (database/seeders/RoleSeeder.php),
     * covering every role generateStaffId() accepts.
     */
    private function roleAbbreviation(string $roleKey): string
    {
        return match ($roleKey) {
            'teacher' => 'TCH',
            'school_admin' => 'ADM',
            'accountant' => 'ACC',
            'librarian' => 'LIB',
            'receptionist' => 'REC',
            'staff' => 'STF',
            default => Str::upper(Str::substr($roleKey, 0, 3)),
        };
    }
}
