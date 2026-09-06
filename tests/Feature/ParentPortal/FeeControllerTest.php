<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §5,
 * 22-schoolos-finance-schema.md §6 (discovery §11).
 *
 * ParentPortal\FeeController's HTTP-layer coverage — read-only, scoped
 * through ResolvesScopedAcademicResource's tenantScope()->
 * relationshipScope() intersection, same "a parent must never view
 * another student's fee assessment" obligation 21 §5 names explicitly.
 * A cross-family id is a 404, never a 403 — matching every other
 * by-ID resolution in this codebase (ParentPortal\FeedbackControllerTest's
 * own doc comment).
 */
class FeeControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/fees');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/parent/fees');

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_index_and_show_their_childs_fees(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '900.00']);
        $this->makePayment($school, $assessment, ['amount' => '300.00', 'status' => 'confirmed']);

        $indexResponse = $this->actingAs($parent)->get('/parent/fees');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.fees.index');
        $indexResponse->assertViewHas('fees', function ($fees) use ($assessment) {
            return $fees->contains(fn ($row) => $row['assessment']->id === $assessment->id && $row['amount_remaining'] === '600.00');
        });

        $showResponse = $this->actingAs($parent)->get("/parent/fees/{$assessment->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.fees.show');
        $showResponse->assertViewHas('assessment', fn ($viewAssessment) => $viewAssessment->id === $assessment->id);
        $showResponse->assertViewHas('amountRemaining', '600.00');
    }

    /**
     * A fetch()/API caller (Accept: application/json) keeps the JSON
     * contract, same wantsJson()-branch shape as
     * ParentPortal\FeedbackControllerTest's own JSON-caller test.
     */
    public function test_a_json_caller_gets_the_fee_data_and_amount_remaining(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '500.00']);

        $response = $this->actingAs($parent)->getJson("/parent/fees/{$assessment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $assessment->id);
        $response->assertJsonPath('amount_remaining', '500.00');
    }

    /**
     * The core "own child only, 404-not-403" scope-enforcement case:
     * a fee assessment belonging to a student who is not this parent's
     * linked child must 404, never 403 and never a leaked row.
     */
    public function test_a_parent_cannot_view_a_fee_assessment_for_a_student_who_is_not_their_linked_child(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $unrelatedStudent = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $unrelatedStudent, $category);

        $response = $this->actingAs($parent)->get("/parent/fees/{$assessment->id}");

        $response->assertNotFound();
    }

    public function test_a_parents_index_never_includes_a_non_linked_students_assessment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $unrelatedStudent = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $ownAssessment = $this->makeFeeAssessment($school, $academicYear, $child, $category);
        $this->makeFeeAssessment($school, $academicYear, $unrelatedStudent, $category);

        $response = $this->actingAs($parent)->get('/parent/fees');

        $response->assertOk();
        $response->assertViewHas('fees', function ($fees) use ($ownAssessment) {
            return $fees->count() === 1 && $fees->first()['assessment']->id === $ownAssessment->id;
        });
    }

    /**
     * Cross-tenant, not just cross-family: a fee assessment in a
     * different school entirely must also 404, confirming tenantScope()
     * and relationshipScope() are both intersected, not either alone.
     */
    public function test_a_parent_cannot_view_a_fee_assessment_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($parent)->get("/parent/fees/{$assessmentInOtherSchool->id}");

        $response->assertNotFound();
    }
}