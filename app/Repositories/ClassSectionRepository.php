<?php

namespace App\Repositories;

use App\Exceptions\ClassSection\InvalidClassTeacherFailure;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\Section;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3, §7a
 * Decision ref: 16-schoolos-decisions-register.md D3
 *
 * D3 (resolved): the invariant "class_sections.class_teacher_id must
 * reference a role='teacher' user" is enforced at the write path, not a
 * DB trigger. assignClassTeacher() below is the only method in SchoolOS
 * permitted to write that column — no controller, form request, or mass
 * assignment writes it directly anywhere else. A trigger was rejected
 * (13 §3, 14 §1) because it would duplicate logic the repository layer
 * should own and would be invisible to anyone reading the application
 * code, which the decisions register calls out as a smaller instance of
 * the exact "authorization logic hidden from the obvious place" problem
 * the whole audit is about.
 */
class ClassSectionRepository
{
    /**
     * The single permitted write path for class_sections.class_teacher_id
     * (D3). Verifies the target user's role_id resolves to 'teacher'
     * before writing — this check lives here and nowhere else, per D3's
     * rationale (13 §3): no DB trigger duplicates it.
     *
     * The update and the resulting audit_logs row (13 §7a — action:
     * 'class_section.class_teacher_assigned', entity_type: 'ClassSection')
     * are written inside a single DB transaction, so a write to
     * class_teacher_id can never land without its audit row, and vice
     * versa.
     *
     * @param  int   $classSectionId  the class_sections row being updated
     * @param  int   $teacherUserId   the user being assigned as class
     *         teacher — verified against role_id before anything is
     *         written
     * @param  User  $actor           the authenticated user performing the
     *         assignment, recorded as audit_logs.actor_id. Always taken
     *         from the caller's authenticated session/token, never
     *         inferred from $teacherUserId or any client-supplied value
     *         (12 §4 / 14 §0's "no client-supplied school_id, ever" and
     *         "no authorization logic that exists only in the UI" ground
     *         rules apply equally to who gets credited with the action).
     *
     * @throws InvalidClassTeacherFailure if the target user's role does
     *         not resolve to 'teacher'. Thrown before the transaction
     *         opens — an invalid target never reaches the write path at
     *         all, let alone gets rolled back from inside it.
     */
    public function assignClassTeacher(int $classSectionId, int $teacherUserId, User $actor): ClassSection
    {
        $targetUser = $this->assertIsTeacher($teacherUserId);

        return DB::transaction(function () use ($classSectionId, $targetUser, $actor) {
            // lockForUpdate: two concurrent assignments to the same class
            // section must not both read the same "before" state and race
            // each other's audit_logs row past the actual final value.
            $classSection = ClassSection::lockForUpdate()->findOrFail($classSectionId);

            $beforeState = ['class_teacher_id' => $classSection->class_teacher_id];

            $classSection->class_teacher_id = $targetUser->id;
            $classSection->save();

            AuditLog::create([
                'school_id' => $classSection->school_id,
                'actor_id' => $actor->id,
                'action' => 'class_section.class_teacher_assigned',
                'entity_type' => 'ClassSection',
                'entity_id' => $classSection->id,
                'before_state' => $beforeState,
                'after_state' => ['class_teacher_id' => $targetUser->id],
            ]);

            return $classSection;
        });
    }

    /**
     * The write path that creates a class_sections row — the "creates"
     * half of the single write path 14 §1 originally scoped this
     * repository to cover ("the write path that creates/updates a
     * class_sections row"), left unbuilt until now. Before this method
     * existed, the only code that could produce a ClassSection row at all
     * was the test fixture's forceFill() bypass — there was no way to
     * satisfy class_sections.class_teacher_id's NOT NULL constraint in
     * production, since assignClassTeacher() only ever updates a row that
     * already exists.
     *
     * Runs through the exact same role gate as assignClassTeacher()
     * (assertIsTeacher() below), so D3's invariant — "class_teacher_id
     * only ever gets to point at a role='teacher' user" — holds for both
     * ways that column can be written, not just the update path. The
     * value is written via forceFill(), the same bypass the test fixture
     * uses and for the same reason: class_teacher_id is deliberately
     * excluded from $fillable so a bare ClassSection::create() can never
     * set it, and this method is one of the two places (with
     * assignClassTeacher()) that's allowed to.
     *
     * $schoolId is taken from the caller's own scoped context, never from
     * client input directly, per Ground Rule 0. $academicYearId,
     * $standardId, and $sectionId are each checked against it — a
     * caller-side consistency bug (e.g. a standard picked from the wrong
     * school's dropdown), not end-user input validation, since tenant
     * scoping has already happened upstream of this call — matching the
     * shape of EnrollmentRepository::enroll()'s cross-tenant guards.
     *
     * @throws InvalidClassTeacherFailure if the target user's role does
     *         not resolve to 'teacher'. Thrown before the transaction
     *         opens, same as assignClassTeacher().
     * @throws \InvalidArgumentException if $academicYearId, $standardId,
     *         or $sectionId doesn't belong to $schoolId.
     */
    public function create(
        int $schoolId,
        int $academicYearId,
        int $standardId,
        int $sectionId,
        int $teacherUserId,
        User $actor
    ): ClassSection {
        $targetUser = $this->assertIsTeacher($teacherUserId);

        $academicYear = AcademicYear::findOrFail($academicYearId);

        if ($academicYear->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ClassSectionRepository::create(): academic_year #{$academicYearId} belongs to "
                . "school #{$academicYear->school_id}, not the requested school #{$schoolId}."
            );
        }

        $standard = Standard::findOrFail($standardId);

        if ($standard->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ClassSectionRepository::create(): standard #{$standardId} belongs to school "
                . "#{$standard->school_id}, not the requested school #{$schoolId}."
            );
        }

        $section = Section::findOrFail($sectionId);

        if ($section->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ClassSectionRepository::create(): section #{$sectionId} belongs to school "
                . "#{$section->school_id}, not the requested school #{$schoolId}."
            );
        }

        return DB::transaction(function () use (
            $schoolId,
            $academicYearId,
            $standardId,
            $sectionId,
            $targetUser,
            $actor
        ) {
            $classSection = new ClassSection();

            // forceFill(), not create(): class_teacher_id is deliberately
            // absent from $fillable (D3) so no mass-assignment call can
            // set it. This method is one of the two write paths D3
            // permits to bypass that guard directly — the other being
            // assignClassTeacher()'s attribute-assignment + save().
            $classSection->forceFill([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'standard_id' => $standardId,
                'section_id' => $sectionId,
                'class_teacher_id' => $targetUser->id,
            ]);
            $classSection->save();

            AuditLog::create([
                'school_id' => $schoolId,
                'actor_id' => $actor->id,
                'action' => 'class_section.created',
                'entity_type' => 'ClassSection',
                'entity_id' => $classSection->id,
                'before_state' => null,
                'after_state' => [
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'standard_id' => $standardId,
                    'section_id' => $sectionId,
                    'class_teacher_id' => $targetUser->id,
                ],
            ]);

            return $classSection;
        });
    }

    /**
     * The one gate D3 requires: verifies $teacherUserId's role_id
     * resolves to 'teacher', and returns the loaded User so callers don't
     * have to re-fetch it. Shared by assignClassTeacher() and create() so
     * there remains exactly one place in the codebase that decides
     * whether a user is eligible to become a class_teacher_id — adding a
     * second write path (create()) must not mean writing this check
     * twice.
     *
     * @throws InvalidClassTeacherFailure if the target user's role does
     *         not resolve to 'teacher'.
     */
    private function assertIsTeacher(int $teacherUserId): User
    {
        $targetUser = User::with('role')->findOrFail($teacherUserId);

        if (($targetUser->role->key ?? null) !== 'teacher') {
            // D3's entire reason for existing: this is the one and only
            // gate that keeps class_teacher_id from ever pointing at a
            // non-teacher, so it rejects loudly and typed rather than
            // silently coercing or letting a bad FK land.
            throw new InvalidClassTeacherFailure($targetUser);
        }

        return $targetUser;
    }
}
