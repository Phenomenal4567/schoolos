<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Repositories\ClassTeacherAssignmentRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\ParentLinkRepository;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 12-schoolos-architecture.md §3a
 * Decision ref: 14-schoolos-implementation-plan.md §2's Phase 2 test gate
 * ("Parent-child scope tests from Phase 1's gate re-run against the
 * now-populated real data shapes (empty-table tests aren't sufficient
 * evidence the join works under real cardinality).")
 *
 * relationshipScope(Builder $query, User $user, string $modelClass): Builder
 * is the real contract — confirmed against Phase1TestGateTest.php's rows
 * 6-7 and DashboardController's tenantScope()->relationshipScope() usage.
 * Every test below intersects into a real query and asserts on the rows
 * that come back, never on a standalone boolean, and goes through the
 * real repositories this bundle built to produce the rows it joins
 * against — closing the exact gap this test-gate row calls out.
 */
class ScopeServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    // --- tenantScope() ---

    public function test_tenant_scope_restricts_a_query_to_the_users_own_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeClassSection($otherSchool, $this->makeAcademicYear($otherSchool), $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]));
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $visible = (new ScopeService())->tenantScope(ClassSection::query(), $admin)->get();

        $this->assertCount(1, $visible);
        $this->assertSame($school->id, $visible->first()->school_id);
    }

    public function test_super_admin_is_exempt_from_tenant_scope(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeClassSection($school, $academicYear, $teacher);
        $this->makeClassSection($otherSchool, $this->makeAcademicYear($otherSchool), $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]));
        $superAdmin = $this->makeUser(['role_id' => $this->makeRole('super_admin')->id, 'school_id' => null]);

        $visible = (new ScopeService())->tenantScope(ClassSection::query(), $superAdmin)->get();

        $this->assertCount(2, $visible);
    }

    // --- relationshipScope(): teacher, F18 ---

    public function test_teacher_relationship_scope_admits_assigned_teacher_and_denies_unassigned(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $homeroomTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $homeroomTeacher);
        $subjectTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        (new ClassTeacherAssignmentRepository())->assign(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $subject->id,
            $subjectTeacher->id,
            $admin
        );

        $enrollment = $this->app->make(EnrollmentRepository::class)->enroll(
            $school->id,
            $academicYear->id,
            $student->id,
            $classSection->id,
            'R1',
            $admin
        );

        $scopeService = new ScopeService();

        // Both the subject-assignment relationship and the homeroom
        // relationship grant access to the ClassSection itself.
        $this->assertSame(
            [$classSection->id],
            $scopeService->relationshipScope(ClassSection::query(), $subjectTeacher, ClassSection::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$classSection->id],
            $scopeService->relationshipScope(ClassSection::query(), $homeroomTeacher, ClassSection::class)->pluck('id')->all()
        );

        // The same relationship grants access to the student's enrollment
        // row, via its class_section_id column.
        $this->assertSame(
            [$enrollment->id],
            $scopeService->relationshipScope(StudentEnrollment::query(), $subjectTeacher, StudentEnrollment::class)->pluck('id')->all()
        );

        // A teacher with no class_teacher_assignments row and no homeroom
        // relationship sees neither the section nor its roster.
        $this->assertSame(
            [],
            $scopeService->relationshipScope(ClassSection::query(), $unassignedTeacher, ClassSection::class)->pluck('id')->all()
        );
        $this->assertSame(
            [],
            $scopeService->relationshipScope(StudentEnrollment::query(), $unassignedTeacher, StudentEnrollment::class)->pluck('id')->all()
        );
    }

    // --- relationshipScope(): parent, F20 ---

    public function test_parent_relationship_scope_admits_linked_child_and_denies_unlinked_against_student_id_column(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $linkedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $unlinkedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $linkRepository = new ParentLinkRepository();
        $link = $linkRepository->link($school->id, $parent->id, $linkedStudent->id, $admin);

        $enrollmentRepository = $this->app->make(EnrollmentRepository::class);
        $linkedEnrollment = $enrollmentRepository->enroll($school->id, $academicYear->id, $linkedStudent->id, $classSection->id, 'R1', $admin);
        $enrollmentRepository->enroll($school->id, $academicYear->id, $unlinkedStudent->id, $classSection->id, 'R2', $admin);

        $scopeService = new ScopeService();

        // User model: self, or a linked child — never an unlinked student.
        $visibleUsers = $scopeService->relationshipScope(User::query(), $parent, User::class)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$parent->id, $linkedStudent->id], $visibleUsers);

        // StudentEnrollment: joined generically via its student_id column.
        $this->assertSame(
            [$linkedEnrollment->id],
            $scopeService->relationshipScope(StudentEnrollment::query(), $parent, StudentEnrollment::class)->pluck('id')->all()
        );

        // After unlink(), the child drops out of both.
        $linkRepository->unlink($link->id);

        $this->assertSame(
            [$parent->id],
            $scopeService->relationshipScope(User::query(), $parent, User::class)->pluck('id')->all()
        );
        $this->assertSame(
            [],
            $scopeService->relationshipScope(StudentEnrollment::query(), $parent, StudentEnrollment::class)->pluck('id')->all()
        );
    }

    // --- relationshipScope(): student, self-only ---

    public function test_student_relationship_scope_admits_own_enrollment_and_denies_others(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $anotherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $enrollmentRepository = $this->app->make(EnrollmentRepository::class);
        $ownEnrollment = $enrollmentRepository->enroll($school->id, $academicYear->id, $student->id, $classSection->id, 'R1', $admin);
        $enrollmentRepository->enroll($school->id, $academicYear->id, $anotherStudent->id, $classSection->id, 'R2', $admin);

        $scopeService = new ScopeService();

        $this->assertSame(
            [$student->id],
            $scopeService->relationshipScope(User::query(), $student, User::class)->pluck('id')->all()
        );
        $this->assertSame(
            [$ownEnrollment->id],
            $scopeService->relationshipScope(StudentEnrollment::query(), $student, StudentEnrollment::class)->pluck('id')->all()
        );
    }

    // --- relationshipScope(): default branch (school_admin), F21/F22-shaped tier-3 check ---

    public function test_school_admin_relationship_scope_is_tenant_scope_only_by_design(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        // Documents the intended default branch: school_admin relies on
        // tenantScope() alone (per every admin controller's own doc
        // comments in this bundle), so relationshipScope() is a no-op
        // pass-through here rather than a false-negative F21/F22-shaped gap.
        $visible = (new ScopeService())->relationshipScope(User::query(), $admin, User::class)->pluck('id')->all();

        $this->assertContains($student->id, $visible);
    }

    // --- relationshipScope(): $wideVisibilityRoles, 15 §4/§5 role-based scope widening ---

    /**
     * Reuses the existing 'teacher' role for both sides of this test
     * rather than adding a 'principal' role: the mechanism under test is
     * "does passing a role key in $wideVisibilityRoles skip the
     * narrowing that role would otherwise get", and 'teacher' already
     * has a real narrowing branch (teacherRelationshipScope()) to skip.
     * Introducing a new role would test the same mechanism through an
     * extra, unnecessary migration/seeder surface — see the report for
     * why 'principal' itself was judged unnecessary for this task.
     */
    public function test_role_not_in_wide_visibility_list_keeps_its_existing_narrowing(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $homeroomTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $homeroomTeacher);
        $unassignedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeClassSection($school, $academicYear, $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]));

        $scopeService = new ScopeService();

        // No $wideVisibilityRoles argument at all — the pre-existing
        // 3-arg call shape every controller in this bundle already uses
        // — behaves exactly as it did before this task (regression
        // guard on Phase 1's teacher-branch behavior).
        $this->assertSame(
            [$classSection->id],
            $scopeService->relationshipScope(ClassSection::query(), $homeroomTeacher, ClassSection::class)->pluck('id')->all()
        );

        // Explicitly passing a $wideVisibilityRoles list that does NOT
        // contain 'teacher' has the same effect as omitting it.
        $this->assertSame(
            [],
            $scopeService->relationshipScope(ClassSection::query(), $unassignedTeacher, ClassSection::class, ['school_admin'])->pluck('id')->all()
        );
    }

    public function test_wide_visibility_role_sees_every_row_in_own_school_but_not_another_schools(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);

        $wideTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        // Deliberately NOT assigned to either section below — a plain
        // teacherRelationshipScope() call would see neither. The
        // wide-visibility path is what's expected to admit both.
        $ownSchoolSectionA = $this->makeClassSection($school, $academicYear, $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]));
        $ownSchoolSectionB = $this->makeClassSection($school, $academicYear, $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]));
        $otherSchoolSection = $this->makeClassSection($otherSchool, $otherAcademicYear, $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $otherSchool->id]));

        $scopeService = new ScopeService();

        // Simulates the real call shape (tenantScope() intersected
        // first, exactly as every controller in this bundle already
        // does) — proves tenant scoping still applies underneath the
        // widened relationship check, not just that the widening
        // parameter exists.
        $query = $scopeService->tenantScope(ClassSection::query(), $wideTeacher);
        $visible = $scopeService->relationshipScope($query, $wideTeacher, ClassSection::class, ['teacher'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$ownSchoolSectionA->id, $ownSchoolSectionB->id], $visible);
        $this->assertNotContains($otherSchoolSection->id, $visible);
    }
}
