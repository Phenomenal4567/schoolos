<?php

namespace App\Repositories;

use App\Exceptions\Admission\MissingDecisionReasonFailure;
use App\Models\AdmissionApplication;
use App\Models\FeeCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12's 11-step admission/enrollment workflow.
 * Decision ref: 16-schoolos-decisions-register.md D16.
 *
 * submit() is the one genuinely unauthenticated write path in this
 * schema — a prospective applicant has no session, so there is no
 * $actor to scope from the way every other repository's write methods
 * have (PromotionRepository::assertActorMayDecide(),
 * EnrollmentRepository's "tenant scoping has already happened
 * upstream" assumption). $schoolId here is resolved server-side from
 * the target school's short_code by the calling controller (never a
 * client-supplied numeric school_id), and every fee_category_id the
 * applicant acknowledges is independently re-checked against that same
 * $schoolId inside this method — the one cross-tenant write path in
 * this codebase with no authenticated actor to lean on for tenant
 * scoping, so the check has to be explicit and self-contained rather
 * than inherited from an upstream scope() call.
 *
 * markUnderReview()/reject()/withdraw()/accept() are all
 * school_admin-only, standard status transitions — none of them re-run
 * against an application that's already left the submitted/
 * under_review states via reject/withdraw/accept (see each method's own
 * guard), matching this bundle's "each write path separately verifies
 * its own foreign keys and state" posture.
 */
class AdmissionApplicationRepository
{
    /**
     * @param array<int, int> $feeCategoryAcknowledgments fee_categories.id
     *        values the applicant is acknowledging, unvalidated input —
     *        every id is checked against $schoolId's own fee_categories
     *        before the row is written.
     * @param array<string, mixed> $applicantData name/DOB/parent-guardian/
     *        medical info — structured, not modeled further here; see
     *        AdmissionApplication's own doc comment for why this is JSON,
     *        not a users FK.
     * @param array<int, mixed>|null $documents file references.
     *
     * @throws \InvalidArgumentException if any acknowledged
     *         fee_category_id doesn't belong to $schoolId.
     */
    public function submit(
        int $schoolId,
        array $applicantData,
        array $feeCategoryAcknowledgments,
        ?array $documents = null
    ): AdmissionApplication {
        $validCount = FeeCategory::where('school_id', $schoolId)
            ->whereIn('id', $feeCategoryAcknowledgments)
            ->count();

        if ($validCount !== count(array_unique($feeCategoryAcknowledgments))) {
            throw new \InvalidArgumentException(
                'AdmissionApplicationRepository::submit(): one or more fee_category_acknowledgments '
                . "do not belong to school #{$schoolId}."
            );
        }

        return AdmissionApplication::create([
            'school_id' => $schoolId,
            'status' => 'submitted',
            'applicant_data' => $applicantData,
            'fee_category_acknowledgments' => array_values($feeCategoryAcknowledgments),
            'documents' => $documents,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $application doesn't belong to $schoolId, or if
     *         $application isn't currently 'submitted'.
     */
    public function markUnderReview(int $schoolId, AdmissionApplication $application, User $actor): AdmissionApplication
    {
        $this->assertActorMayDecide($actor);
        $this->assertBelongsToSchool($application, $schoolId);
        $this->assertStatusIn($application, ['submitted']);

        $application->update(['status' => 'under_review']);

        return $application->refresh();
    }

    /**
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $application doesn't belong to $schoolId, or if
     *         $application is already decided (accepted/rejected/
     *         withdrawn).
     * @throws MissingDecisionReasonFailure if $reason is empty.
     */
    public function reject(int $schoolId, AdmissionApplication $application, string $reason, User $actor): AdmissionApplication
    {
        return $this->decideTerminal($schoolId, $application, 'rejected', $reason, $actor);
    }

    /**
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $application doesn't belong to $schoolId, or if
     *         $application is already decided (accepted/rejected/
     *         withdrawn).
     * @throws MissingDecisionReasonFailure if $reason is empty.
     */
    public function withdraw(int $schoolId, AdmissionApplication $application, string $reason, User $actor): AdmissionApplication
    {
        return $this->decideTerminal($schoolId, $application, 'withdrawn', $reason, $actor);
    }

    /**
     * The conversion path: not-yet-a-user becomes a user. Inside one
     * transaction: creates a new User (role 'student', a random
     * generated password — User.password's `hashed` cast handles
     * hashing, the raw value is never logged or returned), then calls
     * the existing EnrollmentRepository::enroll() with that new user's
     * id — enroll() is what actually creates the student_enrollments
     * row and triggers IdentifierService::generateStudentId() via its
     * own transaction; neither is duplicated here. Records
     * resulting_user_id/resulting_student_enrollment_id back onto this
     * row and sets status = accepted.
     *
     * $academicYearId/$classSectionId/$rollNumber are supplied by the
     * caller (the deciding admin), the same way Admin\
     * EnrollmentController::store() takes them from a form — the
     * application's own applicant_data has no class-section concept,
     * placement is an admission-time admin decision, not something the
     * applicant selects.
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $application doesn't belong to $schoolId, or if
     *         $application is already decided (accepted/rejected/
     *         withdrawn) — an already-decided application is rejected,
     *         not silently re-run.
     * @throws \App\Exceptions\Enrollment\DuplicateEnrollmentFailure
     *         propagated from EnrollmentRepository::enroll() if the
     *         resulting user somehow already has an enrollment for
     *         $academicYearId (not expected for a freshly created user,
     *         guarded here defensively rather than assumed impossible).
     */
    public function accept(
        int $schoolId,
        AdmissionApplication $application,
        int $academicYearId,
        int $classSectionId,
        string $rollNumber,
        User $actor,
        EnrollmentRepository $enrollmentRepository
    ): AdmissionApplication {
        $this->assertActorMayDecide($actor);
        $this->assertBelongsToSchool($application, $schoolId);
        $this->assertStatusIn($application, ['submitted', 'under_review']);

        return DB::transaction(function () use (
            $schoolId,
            $application,
            $academicYearId,
            $classSectionId,
            $rollNumber,
            $actor,
            $enrollmentRepository
        ) {
            $data = $application->applicant_data;

            $student = new User();
            $student->forceFill([
                'school_id' => $schoolId,
                'role_id' => Role::where('key', 'student')->value('id'),
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'mobile_no' => $data['mobile_no'] ?? null,
                'password' => Str::random(32),
                'status' => 'active',
            ]);
            $student->save();

            $enrollment = $enrollmentRepository->enroll(
                $schoolId,
                $academicYearId,
                $student->id,
                $classSectionId,
                $rollNumber,
                $actor
            );

            $application->update([
                'status' => 'accepted',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'resulting_user_id' => $student->id,
                'resulting_student_enrollment_id' => $enrollment->id,
            ]);

            return $application->refresh();
        });
    }

    private function decideTerminal(
        int $schoolId,
        AdmissionApplication $application,
        string $status,
        string $reason,
        User $actor
    ): AdmissionApplication {
        $this->assertActorMayDecide($actor);
        $this->assertBelongsToSchool($application, $schoolId);
        $this->assertStatusIn($application, ['submitted', 'under_review']);

        if (trim($reason) === '') {
            throw new MissingDecisionReasonFailure($application);
        }

        $application->update([
            'status' => $status,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_reason' => $reason,
        ]);

        return $application->refresh();
    }

    private function assertActorMayDecide(User $actor): void
    {
        $roleKey = $actor->role->key ?? null;

        if ($roleKey !== 'school_admin') {
            throw new \InvalidArgumentException(
                "AdmissionApplicationRepository: role '{$roleKey}' may not decide admission applications."
            );
        }
    }

    private function assertBelongsToSchool(AdmissionApplication $application, int $schoolId): void
    {
        if ($application->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "AdmissionApplicationRepository: admission_application #{$application->id} belongs to "
                . "school #{$application->school_id}, not the requested school #{$schoolId}."
            );
        }
    }

    /**
     * @param array<int, string> $allowed
     */
    private function assertStatusIn(AdmissionApplication $application, array $allowed): void
    {
        if (! in_array($application->status, $allowed, true)) {
            throw new \InvalidArgumentException(
                "AdmissionApplicationRepository: admission_application #{$application->id} is already "
                . "'{$application->status}' — cannot re-run this transition."
            );
        }
    }
}
