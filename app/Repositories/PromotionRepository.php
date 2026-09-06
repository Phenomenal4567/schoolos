<?php

namespace App\Repositories;

use App\Exceptions\Promotion\MissingPromotionReasonFailure;
use App\Models\ClassSection;
use App\Models\ExamMark;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
 * 23-schoolos-exam-domain-map.md §4
 * Decision ref: 16-schoolos-decisions-register.md D15
 *
 * The two write paths for promotions — one per discovery §9's two
 * mechanisms (§9.1 automatic, §9.2 manual) — deliberately never a
 * single shared writer, so "a promotion never happens without one of
 * the two paths being explicit in the record" (20 §6's test-gate
 * wording) is true by construction: every call site is already
 * committed to a `method` value before either method's body runs.
 *
 * Neither method resolves its target class_section's "next" standard —
 * there is no next/previous ordering between Standard rows anywhere in
 * this schema (Standard is just a named grade, 13 §3), so this bundle
 * doesn't invent one. The destination class_section (if any) is
 * supplied explicitly by the caller (the admin already knows which of
 * next year's sections a given section's students flow into — a UI/
 * data-entry concern, not a schema concept this pass has a requirement
 * to model), and runAutomatic() applies the same destination to every
 * promoted student in one call rather than resolving it per-student.
 *
 * Aggregate computation (23 §4 flagged this as open, unresolved by the
 * exam domain-map pass since no `aggregate` column exists anywhere):
 * this repository defines it as the mean of each ExamMark's percentage
 * (marks_obtained / max_marks * 100) across every exam recorded against
 * the student's $fromSection. This is a v1 engineering default, not a
 * product decision from 19/20 — worth a school-facing confirmation
 * later the same way D15 confirmed the rule shape itself, but not a
 * blocker for this build since it's an internal computation, not a
 * schema commitment (changing it later doesn't require a migration).
 */
class PromotionRepository
{
    /**
     * Evaluates every active enrollment in $fromSection against $rule
     * and writes one promotions row per evaluated student. A student
     * with zero recorded ExamMarks in $fromSection is skipped entirely
     * (no row written) — there's nothing to evaluate automatically, and
     * forcing a held-back verdict on a student the system has no marks
     * for would be indistinguishable from a real failing result; that
     * student is left for PromotionRepository::override() instead.
     *
     * A student who already has a *manual* promotion decision for this
     * enrollment is also skipped — an automatic re-run must never
     * clobber a human override (19 §8's discovery §9.2 requirement),
     * unlike a prior *automatic* decision, which this method treats as
     * safe to overwrite (a re-run before the batch is finalized is a
     * correction, the same "upsert, don't reject" posture
     * PromotionRuleRepository::setRule() uses for its own resource).
     *
     * @return Collection<int, Promotion> every promotion row this call
     *         created or updated — does not include students skipped
     *         for either reason above.
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $fromSection/$toSection/$rule don't all belong to
     *         $schoolId, if $rule doesn't match $fromSection's
     *         academic_year_id/standard_id, or if $toSection is
     *         supplied and is the same row as $fromSection.
     */
    public function runAutomatic(
        int $schoolId,
        ClassSection $fromSection,
        ?ClassSection $toSection,
        PromotionRule $rule,
        User $actor
    ): Collection {
        $this->assertActorMayDecide($actor);
        $this->assertBelongsToSchool($fromSection, $schoolId, 'from_class_section');

        if ($toSection !== null) {
            $this->assertBelongsToSchool($toSection, $schoolId, 'to_class_section');

            if ($toSection->id === $fromSection->id) {
                throw new \InvalidArgumentException(
                    'PromotionRepository::runAutomatic(): to_class_section cannot be the same row '
                    . 'as from_class_section.'
                );
            }
        }

        $this->assertBelongsToSchool($rule, $schoolId, 'promotion_rule');

        if ($rule->academic_year_id !== $fromSection->academic_year_id
            || $rule->standard_id !== $fromSection->standard_id) {
            throw new \InvalidArgumentException(
                "PromotionRepository::runAutomatic(): promotion_rule #{$rule->id} does not match "
                . "from_class_section #{$fromSection->id}'s academic_year/standard."
            );
        }

        $minAggregate = (float) $rule->criteria['min_aggregate'];

        $enrollments = StudentEnrollment::where('class_section_id', $fromSection->id)
            ->where('status', 'active')
            ->get();

        $results = collect();

        foreach ($enrollments as $enrollment) {
            $existing = Promotion::where('student_enrollment_id', $enrollment->id)->first();

            if ($existing !== null && $existing->method === 'manual') {
                continue;
            }

            $aggregate = $this->computeAggregate($enrollment->student_id, $fromSection->id);

            if ($aggregate === null) {
                continue;
            }

            $promoted = $aggregate >= $minAggregate;

            $results->push(Promotion::updateOrCreate(
                ['student_enrollment_id' => $enrollment->id],
                [
                    'school_id' => $schoolId,
                    'student_id' => $enrollment->student_id,
                    'from_class_section_id' => $fromSection->id,
                    'to_class_section_id' => $promoted ? $toSection?->id : null,
                    'method' => 'automatic',
                    'decided_by' => null,
                    'reason' => null,
                    'decided_at' => now(),
                ]
            ));
        }

        return $results;
    }

    /**
     * Discovery §9.2's "admins can override automatic promotion for
     * special circumstances" path. Always sets decided_by and reason —
     * see MissingPromotionReasonFailure's own doc comment for why an
     * empty reason is rejected rather than defaulted. Overwrites
     * whatever the enrollment's existing promotions row says
     * (automatic or a prior manual decision alike) — unlike
     * runAutomatic()'s manual-decision skip, a human explicitly
     * choosing to override always wins, that's the entire point of the
     * path existing.
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if $enrollment/$toSection don't belong to $schoolId.
     * @throws MissingPromotionReasonFailure if $reason is empty.
     */
    public function override(
        int $schoolId,
        StudentEnrollment $enrollment,
        ?ClassSection $toSection,
        string $reason,
        User $actor
    ): Promotion {
        $this->assertActorMayDecide($actor);
        $this->assertBelongsToSchool($enrollment, $schoolId, 'student_enrollment');

        if ($toSection !== null) {
            $this->assertBelongsToSchool($toSection, $schoolId, 'to_class_section');
        }

        if (trim($reason) === '') {
            throw new MissingPromotionReasonFailure($enrollment);
        }

        return Promotion::updateOrCreate(
            ['student_enrollment_id' => $enrollment->id],
            [
                'school_id' => $schoolId,
                'student_id' => $enrollment->student_id,
                'from_class_section_id' => $enrollment->class_section_id,
                'to_class_section_id' => $toSection?->id,
                'method' => 'manual',
                'decided_by' => $actor->id,
                'reason' => $reason,
                'decided_at' => now(),
            ]
        );
    }

    /**
     * Mean exam percentage across every ExamMark recorded for
     * $studentId on an exam belonging to $classSectionId. Returns null
     * (not 0) when no marks exist at all — runAutomatic() reads a null
     * as "cannot evaluate," never as a 0% aggregate, so a student with
     * no recorded marks is never accidentally scored as a fail. See
     * this class's own doc comment for why this specific aggregation
     * method is a v1 default, not a confirmed product decision.
     */
    private function computeAggregate(int $studentId, int $classSectionId): ?float
    {
        $marks = ExamMark::where('student_id', $studentId)
            ->whereIn('exam_id', function ($query) use ($classSectionId) {
                $query->select('id')->from('exams')->where('class_section_id', $classSectionId);
            })
            ->get(['marks_obtained', 'max_marks']);

        if ($marks->isEmpty()) {
            return null;
        }

        $percentages = $marks->map(function (ExamMark $mark) {
            $max = (float) $mark->max_marks;

            return $max > 0 ? ((float) $mark->marks_obtained / $max) * 100 : 0.0;
        });

        return (float) $percentages->average();
    }

    private function assertActorMayDecide(User $actor): void
    {
        $roleKey = $actor->role->key ?? null;

        if ($roleKey !== 'school_admin') {
            throw new \InvalidArgumentException(
                "PromotionRepository: role '{$roleKey}' may not decide promotions."
            );
        }
    }

    private function assertBelongsToSchool(object $model, int $schoolId, string $label): void
    {
        if ($model->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                sprintf(
                    'PromotionRepository: %s #%d belongs to school #%d, not the requested school #%d.',
                    $label,
                    $model->id,
                    $model->school_id,
                    $schoolId,
                )
            );
        }
    }
}