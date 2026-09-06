<?php

namespace Tests\Feature;

use App\Exceptions\Promotion\MissingPromotionReasonFailure;
use App\Models\Promotion;
use App\Repositories\PromotionRepository;
use App\Repositories\PromotionRuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8,
 * 20-phase-6-8-execution-prompt.md §6 (Track 7b), 23-schoolos-exam-
 * domain-map.md §4.
 * Decision ref: 16-schoolos-decisions-register.md D15.
 *
 * One test per item 20 §6's build prompt lists, in the same order:
 * (a) automatic promotion correctly applies the school's rule, (b)
 * manual override always records decided_by and a reason, (c) a
 * promotion never happens without one of the two paths being explicit
 * in the record. Matches Phase6aTestGateTest/Phase6dTestGateTest's own
 * "one test per gate row" shape.
 *
 * Row 8's route-table lint (Phase1TestGateTest::
 * test_every_show_by_id_route_has_a_registered_scope_check()) already
 * covers every parent/student promotions/{promotion} route this track
 * added — no dedicated test for that here, same reasoning
 * Phase6dTestGateTest's own doc comment gives for its row 7.
 */
class Phase7bTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * (a) — automatic promotion correctly applies the school's rule: a
     * student whose mean exam percentage in from_class_section clears
     * the rule's min_aggregate is promoted (to_class_section_id set to
     * the supplied destination); a student who doesn't is held back
     * (to_class_section_id null). Both students are evaluated in the
     * same runAutomatic() call against the same rule, so this exercises
     * the threshold boundary directly rather than two separate setups.
     */
    public function test_automatic_promotion_correctly_applies_the_schools_rule(): void
    {
        $ruleRepository = app(PromotionRuleRepository::class);
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);
        $toSection = $this->makeClassSection($school, $year, $teacher);
        $subject = $this->makeSubject($school);
        $exam = $this->makeExam($school, $year, $fromSection, $subject);

        $rule = $ruleRepository->setRule(
            $school->id,
            $year->id,
            $fromSection->standard_id,
            ['min_aggregate' => 40],
            $admin
        );

        $passingStudent = $this->makeRoleUser('student', $school);
        $passingEnrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $passingStudent);
        $this->makeExamMark($school, $exam, $passingStudent, $teacher, [
            'marks_obtained' => 80,
            'max_marks' => 100,
        ]);

        $failingStudent = $this->makeRoleUser('student', $school);
        $failingEnrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $failingStudent);
        $this->makeExamMark($school, $exam, $failingStudent, $teacher, [
            'marks_obtained' => 20,
            'max_marks' => 100,
        ]);

        $results = $promotionRepository->runAutomatic($school->id, $fromSection, $toSection, $rule, $admin);

        $this->assertCount(2, $results);

        $passingPromotion = Promotion::where('student_enrollment_id', $passingEnrollment->id)->first();
        $this->assertSame($toSection->id, $passingPromotion->to_class_section_id);
        $this->assertSame('automatic', $passingPromotion->method);
        $this->assertNull($passingPromotion->decided_by);

        $failingPromotion = Promotion::where('student_enrollment_id', $failingEnrollment->id)->first();
        $this->assertNull($failingPromotion->to_class_section_id);
        $this->assertSame('automatic', $failingPromotion->method);
    }

    /**
     * (a, continued) — a student with zero recorded ExamMarks in
     * from_class_section is skipped entirely by an automatic run (no
     * promotions row is written), rather than being scored a 0%
     * aggregate and held back by default. See PromotionRepository::
     * computeAggregate()'s own doc comment for why null, not 0, is the
     * "cannot evaluate" signal.
     */
    public function test_a_student_with_no_recorded_marks_is_skipped_by_automatic_promotion(): void
    {
        $ruleRepository = app(PromotionRuleRepository::class);
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);

        $rule = $ruleRepository->setRule(
            $school->id,
            $year->id,
            $fromSection->standard_id,
            ['min_aggregate' => 40],
            $admin
        );

        $student = $this->makeRoleUser('student', $school);
        $enrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $student);

        $results = $promotionRepository->runAutomatic($school->id, $fromSection, null, $rule, $admin);

        $this->assertCount(0, $results);
        $this->assertNull(Promotion::where('student_enrollment_id', $enrollment->id)->first());
    }

    /**
     * (b) — manual override always records decided_by and a reason: a
     * successful override() call sets method = manual, decided_by =
     * the acting admin, and the supplied reason verbatim.
     */
    public function test_manual_override_always_records_decided_by_and_a_reason(): void
    {
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);
        $toSection = $this->makeClassSection($school, $year, $teacher);

        $student = $this->makeRoleUser('student', $school);
        $enrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $student);

        $promotion = $promotionRepository->override(
            $school->id,
            $enrollment,
            $toSection,
            'Exceptional circumstances — promoted despite low aggregate per parent/admin meeting.',
            $admin
        );

        $this->assertSame('manual', $promotion->method);
        $this->assertSame($admin->id, $promotion->decided_by);
        $this->assertSame(
            'Exceptional circumstances — promoted despite low aggregate per parent/admin meeting.',
            $promotion->reason
        );
    }

    /**
     * (b, continued) — an override() call with an empty (or
     * whitespace-only) reason is rejected before any row is written,
     * per discovery §9.2's "exceptional cases" framing (see
     * MissingPromotionReasonFailure's own doc comment).
     */
    public function test_manual_override_with_an_empty_reason_is_rejected(): void
    {
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);
        $student = $this->makeRoleUser('student', $school);
        $enrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $student);

        $this->expectException(MissingPromotionReasonFailure::class);

        $promotionRepository->override($school->id, $enrollment, null, '   ', $admin);
    }

    /**
     * (c) — a promotion never happens without one of the two paths
     * being explicit in the record: every row this class's own fixtures
     * and the two write methods above produce has a non-null `method`
     * of exactly 'automatic' or 'manual', and the two are mutually
     * exclusive in what they set — automatic rows always have a null
     * decided_by/reason, manual rows always have both populated. This
     * is enforced by construction (PromotionRepository::runAutomatic()/
     * override() are the only two writers, and neither can produce a
     * row missing its own required fields) — this test exercises both
     * paths together and asserts the invariant holds across both.
     */
    public function test_a_promotion_never_happens_without_an_explicit_method(): void
    {
        $ruleRepository = app(PromotionRuleRepository::class);
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);
        $toSection = $this->makeClassSection($school, $year, $teacher);
        $subject = $this->makeSubject($school);
        $exam = $this->makeExam($school, $year, $fromSection, $subject);

        $rule = $ruleRepository->setRule(
            $school->id,
            $year->id,
            $fromSection->standard_id,
            ['min_aggregate' => 40],
            $admin
        );

        $autoStudent = $this->makeRoleUser('student', $school);
        $autoEnrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $autoStudent);
        $this->makeExamMark($school, $exam, $autoStudent, $teacher, [
            'marks_obtained' => 90,
            'max_marks' => 100,
        ]);

        $promotionRepository->runAutomatic($school->id, $fromSection, $toSection, $rule, $admin);

        $manualStudent = $this->makeRoleUser('student', $school);
        $manualEnrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $manualStudent);
        $promotionRepository->override($school->id, $manualEnrollment, $toSection, 'Committee decision.', $admin);

        $autoPromotion = Promotion::where('student_enrollment_id', $autoEnrollment->id)->first();
        $manualPromotion = Promotion::where('student_enrollment_id', $manualEnrollment->id)->first();

        foreach ([$autoPromotion, $manualPromotion] as $promotion) {
            $this->assertContains($promotion->method, ['automatic', 'manual']);
        }

        $this->assertNull($autoPromotion->decided_by);
        $this->assertNull($autoPromotion->reason);
        $this->assertNotNull($manualPromotion->decided_by);
        $this->assertNotNull($manualPromotion->reason);
    }

    /**
     * (a, continued) — an automatic re-run must never clobber a prior
     * *manual* decision for the same enrollment (19 §8's discovery
     * §9.2 requirement, enforced in PromotionRepository::
     * runAutomatic()'s own doc comment) — a manually-overridden student
     * is silently skipped on the next automatic pass, not overwritten.
     */
    public function test_automatic_rerun_never_overwrites_a_manual_decision(): void
    {
        $ruleRepository = app(PromotionRuleRepository::class);
        $promotionRepository = app(PromotionRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $fromSection = $this->makeClassSection($school, $year, $teacher);
        $toSection = $this->makeClassSection($school, $year, $teacher);
        $heldBackSection = $this->makeClassSection($school, $year, $teacher);
        $subject = $this->makeSubject($school);
        $exam = $this->makeExam($school, $year, $fromSection, $subject);

        $rule = $ruleRepository->setRule(
            $school->id,
            $year->id,
            $fromSection->standard_id,
            ['min_aggregate' => 40],
            $admin
        );

        $student = $this->makeRoleUser('student', $school);
        $enrollment = $this->makeStudentEnrollment($school, $year, $fromSection, $student);
        $this->makeExamMark($school, $exam, $student, $teacher, [
            'marks_obtained' => 10,
            'max_marks' => 100,
        ]);

        // Manually override to promote despite the low score.
        $promotionRepository->override($school->id, $enrollment, $toSection, 'Special circumstances.', $admin);

        // Re-run automatic — the low score would normally hold this
        // student back, but the manual decision must survive.
        $promotionRepository->runAutomatic($school->id, $fromSection, $heldBackSection, $rule, $admin);

        $promotion = Promotion::where('student_enrollment_id', $enrollment->id)->first();
        $this->assertSame('manual', $promotion->method);
        $this->assertSame($toSection->id, $promotion->to_class_section_id);
    }
}