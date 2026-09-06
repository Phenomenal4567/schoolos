<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6,
 * 20-phase-6-8-execution-prompt.md §3.
 *
 * One test per item 20 §3 names, in the same order, matching
 * Phase6aTestGateTest's own "one test per gate row" shape: (1)
 * scheme_of_work's teacher-assignment scoping, (2) learning_materials'
 * school-wide-plus-class-scoped visibility for student/parent. Both go
 * through the real HTTP routes/controllers this track added (per 20
 * §3's own wording: "assert via
 * TeacherPortal\SchemeOfWorkController::index()/show()" etc.), not the
 * repository/ScopeService layer directly - unlike Phase4TestGateTest/
 * Phase6aTestGateTest, which predate these controllers and so assert
 * one layer down.
 *
 * A third test below covers this section's "also worth asserting"
 * cross-tenant note. That note names "a school_admin from a different
 * school" - but neither resource has a school_admin-facing index/show
 * (Admin\SchemeOfWorkController/Admin\LearningMaterialController are
 * store-only, per §1 of this track's own prompt), so there is no
 * school_admin read route to exercise that assertion against. This
 * test instead runs the same tenant-isolation check against the actual
 * read surfaces that exist - a teacher/student/parent in one school
 * reaching for another school's same-shaped rows - matching the spirit
 * of every other Phase 6 test gate's "(b) cross-tenant isolation" row
 * (see Phase6aTestGateTest::test_parent_and_student_only_see_their_own_school_events())
 * with the actors this track's controllers actually gate.
 *
 * No dedicated test for the route-table lint (Phase1TestGateTest::
 * test_every_show_by_id_route_has_a_registered_scope_check()) - every
 * scheme-of-work/{schemeOfWork} and learning-materials/{learningMaterial}
 * route this track added already carries 'scope.checked' and is picked
 * up by that existing walk, same reasoning Phase6aTestGateTest's own
 * doc comment gives for its own row 8.
 */
class Phase6cTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * (1) - a teacher assigned to class-section A can see scheme-of-work
     * rows for A via TeacherPortal\SchemeOfWorkController::index()/
     * show(), and cannot see rows for an unrelated class-section B in
     * the same school (a 404 on show(), and absent from index()).
     */
    public function test_teacher_sees_scheme_of_work_for_their_assigned_section_but_not_an_unrelated_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $academicTerm = $this->makeAcademicTerm($school, $academicYear);
        $subject = $this->makeSubject($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $homeroomA = $this->makeRoleUser('teacher', $school);
        $sectionA = $this->makeClassSection($school, $academicYear, $homeroomA);
        $homeroomB = $this->makeRoleUser('teacher', $school);
        $sectionB = $this->makeClassSection($school, $academicYear, $homeroomB);

        $assignedTeacher = $this->makeRoleUser('teacher', $school);
        $this->makeClassTeacherAssignment($school, $academicYear, $sectionA, $subject, $assignedTeacher);

        $schemeA = $this->makeSchemeOfWork($school, $academicTerm, $sectionA, $subject, $admin);
        $schemeB = $this->makeSchemeOfWork($school, $academicTerm, $sectionB, $subject, $admin);

        $indexResponse = $this->actingAs($assignedTeacher)->getJson('/teacher/scheme-of-work');
        $indexResponse->assertOk();
        $indexResponse->assertJsonPath('data.0.id', $schemeA->id);
        $indexResponse->assertJsonCount(1, 'data');

        $showOwn = $this->actingAs($assignedTeacher)->getJson("/teacher/scheme-of-work/{$schemeA->id}");
        $showOwn->assertOk();
        $showOwn->assertJsonPath('data.id', $schemeA->id);

        $showUnrelated = $this->actingAs($assignedTeacher)->getJson("/teacher/scheme-of-work/{$schemeB->id}");
        $showUnrelated->assertNotFound();
    }

    /**
     * (2) - a student/parent linked to class-section A sees materials
     * scoped to A and school-wide (null class_section_id) materials via
     * StudentPortal\LearningMaterialController and
     * ParentPortal\LearningMaterialController's index()/show(), but not
     * materials scoped to an unrelated class-section B.
     */
    public function test_student_and_parent_see_class_scoped_and_school_wide_materials_but_not_an_unrelated_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);

        $homeroomA = $this->makeRoleUser('teacher', $school);
        $sectionA = $this->makeClassSection($school, $academicYear, $homeroomA);
        $homeroomB = $this->makeRoleUser('teacher', $school);
        $sectionB = $this->makeClassSection($school, $academicYear, $homeroomB);

        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $academicYear, $sectionA, $student);

        $parent = $this->makeRoleUser('parent', $school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeStudentParentLink($school, $parent, $student);

        $materialA = $this->makeLearningMaterial($school, $sectionA, $subject);
        $materialSchoolWide = $this->makeLearningMaterial($school, null, null);
        $materialB = $this->makeLearningMaterial($school, $sectionB, $subject);

        $actorsAndPrefixes = [
            [$student, '/student'],
            [$parent, '/parent'],
        ];

        foreach ($actorsAndPrefixes as $pair) {
            $actor = $pair[0];
            $prefix = $pair[1];

            $indexResponse = $this->actingAs($actor)->getJson("{$prefix}/learning-materials");
            $indexResponse->assertOk();

            $visibleIds = collect($indexResponse->json('data'))->pluck('id')->all();
            $this->assertEqualsCanonicalizing([$materialA->id, $materialSchoolWide->id], $visibleIds);

            $this->actingAs($actor)->getJson("{$prefix}/learning-materials/{$materialA->id}")->assertOk();
            $this->actingAs($actor)->getJson("{$prefix}/learning-materials/{$materialSchoolWide->id}")->assertOk();
            $this->actingAs($actor)->getJson("{$prefix}/learning-materials/{$materialB->id}")->assertNotFound();
        }
    }

    /**
     * Cross-tenant isolation, cheaply - see this class's own doc comment
     * for why this exercises teacher/student/parent actors rather than
     * a school_admin (neither resource has a school_admin read route).
     * Two schools, same-shaped fixtures, same-numbered relationships:
     * a validly-assigned teacher in school A, and a validly-enrolled
     * student/linked parent in school A, must not see school B's
     * same-shaped rows even though nothing about the request shape
     * distinguishes them beyond the acting user's own tenant.
     */
    public function test_cross_tenant_rows_are_invisible_on_both_resources(): void
    {
        $schoolA = $this->makeSchool();
        $yearA = $this->makeAcademicYear($schoolA);
        $termA = $this->makeAcademicTerm($schoolA, $yearA);
        $subjectA = $this->makeSubject($schoolA);
        $adminA = $this->makeRoleUser('school_admin', $schoolA);
        $homeroomA = $this->makeRoleUser('teacher', $schoolA);
        $sectionA = $this->makeClassSection($schoolA, $yearA, $homeroomA);
        $teacherA = $this->makeRoleUser('teacher', $schoolA);
        $this->makeClassTeacherAssignment($schoolA, $yearA, $sectionA, $subjectA, $teacherA);
        $studentA = $this->makeRoleUser('student', $schoolA);
        $this->makeStudentEnrollment($schoolA, $yearA, $sectionA, $studentA);
        $parentA = $this->makeRoleUser('parent', $schoolA);
        $this->makeStudentParentLink($schoolA, $parentA, $studentA);

        $schoolB = $this->makeSchool();
        $yearB = $this->makeAcademicYear($schoolB);
        $termB = $this->makeAcademicTerm($schoolB, $yearB);
        $subjectB = $this->makeSubject($schoolB);
        $adminB = $this->makeRoleUser('school_admin', $schoolB);
        $homeroomB = $this->makeRoleUser('teacher', $schoolB);
        $sectionB = $this->makeClassSection($schoolB, $yearB, $homeroomB);

        $schemeB = $this->makeSchemeOfWork($schoolB, $termB, $sectionB, $subjectB, $adminB);
        $materialB = $this->makeLearningMaterial($schoolB, $sectionB, $subjectB);
        $materialBSchoolWide = $this->makeLearningMaterial($schoolB, null, null);

        $this->actingAs($teacherA)->getJson("/teacher/scheme-of-work/{$schemeB->id}")->assertNotFound();
        $this->actingAs($teacherA)->getJson('/teacher/scheme-of-work')
            ->assertOk()
            ->assertJsonMissing(['id' => $schemeB->id]);

        $actorsAndPrefixes = [
            [$studentA, '/student'],
            [$parentA, '/parent'],
        ];

        foreach ($actorsAndPrefixes as $pair) {
            $actor = $pair[0];
            $prefix = $pair[1];

            $this->actingAs($actor)->getJson("{$prefix}/learning-materials/{$materialB->id}")->assertNotFound();
            $this->actingAs($actor)->getJson("{$prefix}/learning-materials/{$materialBSchoolWide->id}")->assertNotFound();

            $indexResponse = $this->actingAs($actor)->getJson("{$prefix}/learning-materials");
            $indexResponse->assertOk();
            $visibleIds = collect($indexResponse->json('data'))->pluck('id')->all();
            $this->assertNotContains($materialB->id, $visibleIds);
            $this->assertNotContains($materialBSchoolWide->id, $visibleIds);
        }
    }
}
