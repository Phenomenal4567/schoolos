<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 22-schoolos-finance-schema.md §3 (discovery §10.5).
 *
 * Admin\ScholarshipController's HTTP-layer coverage — the
 * enrollment/session-scoped policy write path ScholarshipRepository
 * owns. Also confirms the policy-vs-fact integration end to end: a
 * scholarship granted here is picked up by a subsequent fee-assessment
 * creation, matching FeeAssessmentRepository::assess()'s own
 * applicableScholarship() lookup.
 */
class ScholarshipControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/scholarships', []);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->post('/admin/scholarships', []);

        $response->assertForbidden();
    }

    public function test_admin_can_grant_a_full_scholarship(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);

        $response = $this->actingAs($admin)->post('/admin/scholarships', [
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'full',
            'reason' => 'Merit scholarship',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.index'));
        $this->assertDatabaseHas('scholarships', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'full',
            'granted_by' => $admin->id,
            'reason' => 'Merit scholarship',
        ]);
    }

    public function test_granting_a_scholarship_to_a_non_student_fails_with_a_validation_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($admin)->post('/admin/scholarships', [
            'student_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'full',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('scholarships', 0);
    }

    public function test_a_student_from_another_school_fails_request_validation(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $studentInOtherSchool = $this->makeRoleUser('student', $otherSchool);

        $response = $this->actingAs($admin)->post('/admin/scholarships', [
            'student_id' => $studentInOtherSchool->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'full',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('scholarships', 0);
    }

    /**
     * The policy-vs-fact distinction (21 §2 / 22 §3) exercised end to
     * end: a scholarship granted here is invisible on its own — it only
     * becomes a fact once a fee_assessment is created for that
     * student/year afterward.
     */
    public function test_a_granted_scholarship_reduces_a_subsequently_created_assessment(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $this->actingAs($admin)->post('/admin/scholarships', [
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'partial',
            'value' => '25',
        ]);

        $this->actingAs($admin)->post('/admin/fee-assessments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'fee_category_id' => $category->id,
            'base_amount' => '2000.00',
        ]);

        $this->assertDatabaseHas('fee_assessments', [
            'student_id' => $student->id,
            'scholarship_amount' => '500.00',
            'amount_due' => '1500.00',
        ]);
    }
}