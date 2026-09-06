<?php

namespace App\Repositories;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Exceptions\LessonPlan\UnauthorizedLessonPlanReviewFailure;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;
use App\Services\ScopeService;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §4, §5
 *
 * approve()/reject() are the direct fix for F30/F31: the reference
 * codebase's LessonPlanController::approve()/reject() had no tenant
 * scoping *and* no role check at all, despite the same controller's
 * index() correctly branching on hasRole('principal') two methods away.
 * This is the one write path for a LessonPlan's status/reviewed_*
 * columns, matching this bundle's established "one repository method
 * owns a sensitive column" shape (ClassSectionRepository::
 * assignClassTeacher() for class_teacher_id, D3) — no controller writes
 * these columns directly.
 *
 * 17 §4 already settled that school_admin is the SchoolOS stand-in for
 * GegoK12's principal role: no new Role row, no ScopeService
 * $wideVisibilityRoles call needed here. assertIsSchoolAdmin (inlined in
 * review() below) is the role half of F30/F31's fix; ScopeService::
 * tenantScope() is the tenant half — the finding was that BOTH checks
 * were missing, not just the role check, so both are enforced here
 * before any write happens, not just one.
 *
 * Audit-logs, unlike ClassTeacherAssignmentRepository::assign() and
 * EnrollmentRepository::enroll() (both explicitly non-audit-logging by
 * their own doc comments, scoped to D2/D3's specific mechanisms): an
 * approval/rejection is exactly the kind of workflow-integrity-sensitive
 * state transition D2 (school suspension) and D3 (class_teacher_id)
 * were written for — F30/F31 call this out specifically as a
 * workflow-integrity bypass, not just a data-exposure gap, which is the
 * same severity class those two decisions already treat as
 * audit-worthy.
 */
class LessonPlanRepository
{
    use AssertsTeacherAssignedToSection;

    /**
     * The create path this class had none of until this pass — approve()/
     * reject() are the only methods that existed before (see this class's
     * own doc comment on the F30/F31 fix they close), and neither of them
     * is a way to *produce* a lesson_plans row, only to transition one
     * that already exists. Design ref: 17 §5's write-path table — a
     * teacher may only author a lesson plan against a class_section_id
     * they're actually assigned to, the same generalization of F18 every
     * one of this pass's four new create() methods applies.
     *
     * $status starts at 'draft', matching the lesson_plans migration's own
     * column default — a freshly-authored plan is not yet under review
     * until some future submit-for-review action moves it to 'submitted'
     * (out of scope for this pass, which is read + teacher-create only;
     * see this task's own scoping note). review()'s approve()/reject()
     * don't gate on the lesson plan's current status before transitioning
     * it, so a school_admin can still review a 'draft' plan directly if a
     * submit step never ships — this method doesn't need to anticipate
     * that workflow to be correct today.
     *
     * $schoolId is taken from the caller's own scoped context, never
     * client input directly, per Ground Rule 0. Unlike
     * AttendanceRepository::mark()/EnrollmentRepository::enroll(),
     * $academicYearId is not a separate caller-supplied parameter to
     * cross-check — it's read directly off the resolved $classSection
     * itself, which is the single source of truth for "what year is this
     * section in" and leaves no room for a caller to pass a mismatched
     * value in the first place.
     *
     * @throws TeacherNotAssignedToSectionFailure if $actor has no
     *         class_teacher_assignments row for $classSectionId and is
     *         not its class_teacher_id. Thrown before anything else runs,
     *         matching every other repository in this bundle's "reject
     *         loudly before any transaction opens" posture.
     * @throws \InvalidArgumentException if $classSectionId or $subjectId
     *         doesn't belong to $schoolId.
     */
    public function create(
        int $schoolId,
        int $classSectionId,
        int $subjectId,
        string $title,
        string $content,
        User $actor,
        ?string $documentPath = null
    ): LessonPlan {
        $this->assertTeacherAssignedToSection($actor, $classSectionId);

        [$classSection] = $this->resolveAcademicContext($schoolId, $classSectionId, $subjectId, 'create');

        // No audit log here, deliberately — matching
        // ClassTeacherAssignmentRepository::assign()'s own reasoning:
        // audit_logs is scoped to the workflow-integrity-sensitive
        // mechanisms D2/D3 (and, in this file, review()'s status
        // transitions) specifically depend on. Authoring a new lesson
        // plan is ordinary content creation, not a state transition on an
        // existing one — it's the same class of write EnrollmentRepository::
        // enroll() and this repository's own sibling create() methods
        // (TimetableRepository, AssignmentRepository, ExamRepository)
        // also leave un-audit-logged, for the same reason.
        return LessonPlan::create([
            'school_id' => $schoolId,
            'academic_year_id' => $classSection->academic_year_id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subjectId,
            'teacher_id' => $actor->id,
            'title' => $title,
            'content' => $content,
            'document_path' => $documentPath,
            'status' => 'draft',
        ]);
    }

    public function createByAdmin(
        int $schoolId,
        int $classSectionId,
        int $subjectId,
        string $title,
        string $content,
        string $documentPath,
        User $actor
    ): LessonPlan {
        if (($actor->role->key ?? null) !== 'school_admin') {
            throw new \InvalidArgumentException(
                "LessonPlanRepository::createByAdmin(): role '" . ($actor->role->key ?? 'none') . "' may not upload lesson documents."
            );
        }

        [$classSection] = $this->resolveAcademicContext($schoolId, $classSectionId, $subjectId, 'createByAdmin');

        return LessonPlan::create([
            'school_id' => $schoolId,
            'academic_year_id' => $classSection->academic_year_id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subjectId,
            'teacher_id' => $actor->id,
            'title' => $title,
            'content' => $content,
            'document_path' => $documentPath,
            'status' => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => 'Admin uploaded lesson document.',
        ]);
    }

    /**
     * @throws UnauthorizedLessonPlanReviewFailure see review() below.
     */
    public function approve(int $lessonPlanId, User $actor, ?string $note = null): LessonPlan
    {
        return $this->review($lessonPlanId, $actor, 'approved', $note);
    }

    /**
     * $reason is required — mirrors AttendanceRepository::mark()'s
     * requirement that a correction states why, so a rejected lesson
     * plan always carries an explanation the authoring teacher can act
     * on, not a bare status flip.
     *
     * @throws UnauthorizedLessonPlanReviewFailure see review() below.
     * @throws \InvalidArgumentException if $reason is blank.
     */
    public function reject(int $lessonPlanId, User $actor, string $reason): LessonPlan
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException(
                'LessonPlanRepository::reject(): a reason is required.'
            );
        }

        return $this->review($lessonPlanId, $actor, 'rejected', $reason);
    }

    /**
     * The single write path behind both approve() and reject(). Checks
     * the actor's role before opening a transaction (same "reject loudly
     * before any transaction opens" posture as ClassSectionRepository's
     * assertIsTeacher()), then resolves the target LessonPlan through
     * ScopeService::tenantScope() rather than a bare findOrFail() — a
     * lesson plan belonging to another school must not resolve here at
     * all, the direct fix for F30/F31's missing tenant check.
     *
     * @throws UnauthorizedLessonPlanReviewFailure if $actor's role isn't
     *         school_admin, or if $lessonPlanId doesn't resolve within
     *         $actor's own tenant scope. One exception type covers both
     *         — see that class's doc comment for why a caller shouldn't
     *         be able to distinguish the two cases.
     */
    private function review(int $lessonPlanId, User $actor, string $status, ?string $note): LessonPlan
    {
        if (($actor->role->key ?? null) !== 'school_admin') {
            throw new UnauthorizedLessonPlanReviewFailure($actor, $lessonPlanId);
        }

        return DB::transaction(function () use ($lessonPlanId, $actor, $status, $note) {
            // lockForUpdate: two concurrent review() calls for the same
            // lesson plan (e.g. a double-submitted approval click) must
            // not both read the same "before" state and race each
            // other's audit_logs row past the actual final value — same
            // reasoning as ClassSectionRepository::assignClassTeacher().
            $lessonPlan = (new ScopeService())
                ->tenantScope(LessonPlan::query(), $actor)
                ->lockForUpdate()
                ->find($lessonPlanId);

            if ($lessonPlan === null) {
                // Either it doesn't exist, or it belongs to another
                // school — tenantScope() makes those indistinguishable
                // here, which is the point (F30/F31's fix must not leak
                // "yes, it exists, just not in your school").
                throw new UnauthorizedLessonPlanReviewFailure($actor, $lessonPlanId);
            }

            $beforeState = ['status' => $lessonPlan->status];

            $lessonPlan->status = $status;
            $lessonPlan->reviewed_by = $actor->id;
            $lessonPlan->reviewed_at = now();
            $lessonPlan->review_note = $note;
            $lessonPlan->save();

            AuditLog::create([
                'school_id' => $lessonPlan->school_id,
                'actor_id' => $actor->id,
                'action' => "lesson_plan.{$status}",
                'entity_type' => 'LessonPlan',
                'entity_id' => $lessonPlan->id,
                'before_state' => $beforeState,
                'after_state' => ['status' => $status, 'review_note' => $note],
            ]);

            return $lessonPlan;
        });
    }

    /**
     * @return array{0: ClassSection, 1: Subject}
     */
    private function resolveAcademicContext(int $schoolId, int $classSectionId, int $subjectId, string $caller): array
    {
        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "LessonPlanRepository::{$caller}(): class_section #{$classSectionId} belongs to "
                . "school #{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "LessonPlanRepository::{$caller}(): subject #{$subjectId} belongs to school "
                . "#{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        return [$classSection, $subject];
    }
}
