<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §5,
 * 22-schoolos-finance-schema.md §6 (discovery §11).
 *
 * StudentPortal\FeeController's HTTP-layer coverage — the self-only
 * mirror of ParentPortal\FeeControllerTest, read-only, scoped through
 * ScopeService::studentRelationshipScope()'s self-only branch (self,
 * never linked children — that's the parent-only surface). Same
 * 404-not-403 discipline on another student's assessment.
 */
class FeeControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/fees');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/student/fees');

        $response->assertForbidden();
    }

    public function test_a_student_can_index_and_show_their_own_fees(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '600.00']);
        $this->makePayment($school, $assessment, ['amount' => '200.00', 'status' => 'confirmed']);

        $indexResponse = $this->actingAs($student)->get('/student/fees');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.fees.index');
        $indexResponse->assertViewHas('fees', function ($fees) use ($assessment) {
            return $fees->contains(fn ($row) => $row['assessment']->id === $assessment->id && $row['amount_remaining'] === '400.00');
        });

        $showResponse = $this->actingAs($student)->get("/student/fees/{$assessment->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.fees.show');
        $showResponse->assertViewHas('assessment', fn ($viewAssessment) => $viewAssessment->id === $assessment->id);
        $showResponse->assertViewHas('amountRemaining', '400.00');
    }

    /**
     * The self-only variant of the "own child only" rule: a student
     * cannot view even another student's assessment in the same class,
     * same school. 404, not 403.
     */
    public function test_a_student_cannot_view_another_students_fee_assessment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $otherStudent = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $othersAssessment = $this->makeFeeAssessment($school, $academicYear, $otherStudent, $category);

        $response = $this->actingAs($student)->get("/student/fees/{$othersAssessment->id}");

        $response->assertNotFound();
    }

    public function test_a_students_index_never_includes_another_students_assessment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $otherStudent = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $ownAssessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);
        $this->makeFeeAssessment($school, $academicYear, $otherStudent, $category);

        $response = $this->actingAs($student)->get('/student/fees');

        $response->assertOk();
        $response->assertViewHas('fees', function ($fees) use ($ownAssessment) {
            return $fees->count() === 1 && $fees->first()['assessment']->id === $ownAssessment->id;
        });
    }

    public function test_a_student_cannot_view_an_assessment_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $student = $this->makeRoleUser('student', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($student)->get("/student/fees/{$assessmentInOtherSchool->id}");

        $response->assertNotFound();
    }

    /**
     * Students never initiate payments — 21 §5's parent-only write
     * path. No student.fees.payments.store route exists at all to hit.
     */
    public function test_no_student_facing_payment_route_exists(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);

        $response = $this->actingAs($student)->post("/student/fees/{$assessment->id}/payments", [
            'amount' => '10.00',
        ]);

        $response->assertNotFound();
    }
}