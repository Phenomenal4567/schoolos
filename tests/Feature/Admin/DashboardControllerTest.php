<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: this session's own task doc ("SchoolOS — Admin Setup Write
 * Paths + School Admin Dashboard"), extended in a later session
 * (15-academic-domain-map.md §2) to also cover subjects.
 *
 * Mirrors ParentDashboardTest / StudentDashboardTest's structure: guest
 * redirect, non-admin role forbidden, own-school visibility, and —
 * because this controller resolves several tenantScope()'d collections
 * rather than a single record by ID — a same-shape isolation test using
 * two schools instead of two students. subjects' own-school-only
 * visibility is checked the same way academic years already were,
 * rather than in a separate test method.
 *
 * A later session wired the read side of every admin.* write path that
 * already had a controller/route but no dashboard view calling it (see
 * Admin\DashboardController's own doc comment) — $teachers/$students/
 * $parents/$parentLinks/$pendingLessonPlans, plus the new Blade sections
 * that read them. Coverage for those additions is split into four
 * methods rather than one per variable, matching how the existing
 * own-school test above already covers two variables in one method:
 * one method for the three role-filtered User lists (same shape, same
 * assertion), one for $parentLinks (has an extra active/inactive axis
 * academicYears/subjects don't), one for $pendingLessonPlans (has an
 * extra status axis), and one for the assertSee()/assertDontSee()
 * markup + route-wiring checks, which don't fit the assertViewHas()
 * shape the other three share.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id]);

        $response = $this->actingAs($teacher)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_sees_only_their_own_schools_setup_data(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school, ['label' => 'My School Year']);
        $subject = $this->makeSubject($school, ['name' => 'My School Subject']);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $otherSchool = $this->makeSchool();
        $this->makeAcademicYear($otherSchool, ['label' => 'Other School Year']);
        $this->makeSubject($otherSchool, ['name' => 'Other School Subject']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('My School Year');
        $response->assertDontSee('Other School Year');
        $response->assertSee('My School Subject');
        $response->assertDontSee('Other School Subject');
        $response->assertViewHas(
            'academicYears',
            fn ($academicYears) => $academicYears->pluck('id')->all() === [$academicYear->id]
        );
        $response->assertViewHas(
            'subjects',
            fn ($subjects) => $subjects->pluck('id')->all() === [$subject->id]
        );
    }

    public function test_admin_sees_only_their_own_schools_role_filtered_user_lists(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeRoleUser('teacher', $school, ['name' => 'My School Teacher']);
        $student = $this->makeRoleUser('student', $school, ['name' => 'My School Student']);
        $parent = $this->makeRoleUser('parent', $school, ['name' => 'My School Parent']);

        $otherSchool = $this->makeSchool();
        $this->makeRoleUser('teacher', $otherSchool, ['name' => 'Other School Teacher']);
        $this->makeRoleUser('student', $otherSchool, ['name' => 'Other School Student']);
        $this->makeRoleUser('parent', $otherSchool, ['name' => 'Other School Parent']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas(
            'teachers',
            fn ($teachers) => $teachers->pluck('id')->all() === [$teacher->id]
        );
        $response->assertViewHas(
            'students',
            fn ($students) => $students->pluck('id')->all() === [$student->id]
        );
        $response->assertViewHas(
            'parents',
            fn ($parents) => $parents->pluck('id')->all() === [$parent->id]
        );
    }

    public function test_admin_sees_only_their_own_schools_active_parent_links(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $activeParent = $this->makeRoleUser('parent', $school);
        $activeStudent = $this->makeRoleUser('student', $school);
        $activeLink = $this->makeStudentParentLink($school, $activeParent, $activeStudent, ['status' => 'active']);

        $inactiveParent = $this->makeRoleUser('parent', $school);
        $inactiveStudent = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $inactiveParent, $inactiveStudent, ['status' => 'inactive']);

        $otherSchool = $this->makeSchool();
        $otherParent = $this->makeRoleUser('parent', $otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $this->makeStudentParentLink($otherSchool, $otherParent, $otherStudent, ['status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas(
            'parentLinks',
            fn ($parentLinks) => $parentLinks->pluck('id')->all() === [$activeLink->id]
        );
    }

    public function test_admin_sees_only_their_own_schools_submitted_lesson_plans(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);

        $submittedPlan = $this->makeLessonPlan(
            $school,
            $academicYear,
            $classSection,
            $subject,
            $teacher,
            ['title' => 'My Submitted Plan', 'status' => 'submitted']
        );
        $this->makeLessonPlan(
            $school,
            $academicYear,
            $classSection,
            $subject,
            $teacher,
            ['title' => 'My Approved Plan', 'status' => 'approved']
        );

        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $otherTeacher = $this->makeRoleUser('teacher', $otherSchool);
        $otherClassSection = $this->makeClassSection($otherSchool, $otherAcademicYear, $otherTeacher);
        $otherSubject = $this->makeSubject($otherSchool);
        $this->makeLessonPlan(
            $otherSchool,
            $otherAcademicYear,
            $otherClassSection,
            $otherSubject,
            $otherTeacher,
            ['title' => 'Other Submitted Plan', 'status' => 'submitted']
        );

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertViewHas(
            'pendingLessonPlans',
            fn ($pendingLessonPlans) => $pendingLessonPlans->pluck('id')->all() === [$submittedPlan->id]
        );
    }

    public function test_dashboard_renders_new_sections_wired_to_the_right_routes_without_leaking_other_schools_data(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school, ['name' => 'My School Teacher']);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $student = $this->makeRoleUser('student', $school, ['name' => 'My School Student']);
        $parent = $this->makeRoleUser('parent', $school, ['name' => 'My School Parent']);
        $this->makeStudentParentLink($school, $parent, $student, ['status' => 'active']);
        $this->makeLessonPlan($school, $academicYear, $classSection, $subject, $teacher, ['status' => 'submitted']);

        $otherSchool = $this->makeSchool();
        $this->makeRoleUser('teacher', $otherSchool, ['name' => 'Other School Teacher']);
        $this->makeRoleUser('student', $otherSchool, ['name' => 'Other School Student']);
        $this->makeRoleUser('parent', $otherSchool, ['name' => 'Other School Parent']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();

        // Distinguishing labels for each new section.
        $response->assertSee('Enroll a student');
        $response->assertSee('Parent links');
        $response->assertSee('Pending lesson plans');
        $response->assertSee('Post an announcement');

        // Forms post to the right place.
        $response->assertSee(route('admin.enrollments.store'), false);
        $response->assertSee(route('admin.parent-links.store'), false);
        $response->assertSee(route('admin.announcements.store'), false);

        // No other school's teacher/student/parent leaks into the markup.
        $response->assertDontSee('Other School Teacher');
        $response->assertDontSee('Other School Student');
        $response->assertDontSee('Other School Parent');
    }
}
