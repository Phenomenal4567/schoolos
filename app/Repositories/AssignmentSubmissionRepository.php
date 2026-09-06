<?php

namespace App\Repositories;

use App\Exceptions\Academic\UnauthorizedGradingFailure;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5, §6 (F29)
 *
 * The write half of AssignmentSubmission that the prior Phase 4 passes
 * deliberately left out (see AssignmentRepository's own doc comment: "no
 * submissions()-touching method belongs on this class"). Two methods,
 * two different actors:
 *
 * submit() is the student's own write — a student may only submit
 * against an Assignment they can already read, i.e. one belonging to
 * their own current class_section (ScopeService::studentRelationshipScope()'s
 * class_section_id branch on Assignment, the same read-scope
 * StudentPortal\AssignmentController::show() already enforces). One row
 * per (assignment_id, student_id) — a resubmission updates submitted_at
 * on the existing row rather than creating a second one, matching the
 * unique constraint the assignment_submissions migration already
 * enforces.
 *
 * grade() is the teacher's write onto an existing submission —
 * AssignmentSubmission has no class_section_id of its own, so this is
 * exactly the join-through-parent-Assignment shape
 * ScopeService::teacherRelationshipScope()'s new branch (17 §3) exists
 * for. This method resolves the submission through that same
 * tenantScope()->relationshipScope() intersection, under a row lock,
 * before writing — the direct, structural fix for F29 (any teacher, any
 * school, could write obtained_marks/comments onto any student's
 * submission).
 */
class AssignmentSubmissionRepository
{
    /**
     * @throws \InvalidArgumentException if $assignmentId doesn't resolve
     *         within $student's own tenant/relationship scope (wrong
     *         school, or not the student's own class_section).
     */
    public function submit(User $student, int $assignmentId, ?string $comments = null): AssignmentSubmission
    {
        $scope = new ScopeService();

        $assignment = $scope
            ->relationshipScope($scope->tenantScope(Assignment::query(), $student), $student, Assignment::class)
            ->find($assignmentId);

        if ($assignment === null) {
            throw new \InvalidArgumentException(
                "AssignmentSubmissionRepository::submit(): assignment #{$assignmentId} does not "
                . "resolve within student #{$student->id}'s own scope."
            );
        }

        return AssignmentSubmission::updateOrCreate(
            [
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ],
            [
                'school_id' => $assignment->school_id,
                'submitted_at' => now(),
                'comments' => $comments,
            ]
        );
    }

    /**
     * $maxMarks is not itself validated against $marks here — unlike
     * exam_marks, assignment_submissions carries no max_marks column of
     * its own (see that model's fillable list); a caller wanting a bound
     * enforces it against the parent Assignment's own fields, out of
     * scope for this pass.
     *
     * @throws UnauthorizedGradingFailure if $submissionId does not
     *         resolve within $teacher's own tenant/relationship scope —
     *         another school's row, or a row whose parent assignment
     *         belongs to a section this teacher isn't assigned to
     *         (F29's exact regression).
     */
    public function grade(int $submissionId, User $teacher, float $marks, ?string $comments = null): AssignmentSubmission
    {
        return DB::transaction(function () use ($submissionId, $teacher, $marks, $comments) {
            $scope = new ScopeService();

            $submission = $scope
                ->relationshipScope(
                    $scope->tenantScope(AssignmentSubmission::query(), $teacher),
                    $teacher,
                    AssignmentSubmission::class
                )
                ->lockForUpdate()
                ->find($submissionId);

            if ($submission === null) {
                throw new UnauthorizedGradingFailure($teacher, $submissionId);
            }

            $submission->obtained_marks = $marks;
            $submission->comments = $comments;
            $submission->graded_by = $teacher->id;
            $submission->graded_at = now();
            $submission->save();

            return $submission;
        });
    }
}
