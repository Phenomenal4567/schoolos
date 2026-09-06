<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: this session's own task doc ("SchoolOS — Student Portal
 * (read-only: own profile + own attendance)").
 *
 * Mirrors ParentDashboardTest's structure (guest redirect, non-student
 * role forbidden, own-record visibility), plus the isolation test that
 * doc explicitly calls for: since neither student route takes a route
 * parameter at all, there is no {student} id to manipulate the way
 * ParentDashboardTest::test_parent_cannot_view_another_parents_child()
 * manipulates {student} — a second student's data is never reachable
 * through this controller in the first place, and this file checks that
 * directly by having two students each see only their own attendance.
 */
class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id]);

        $response = $this->actingAs($teacher)->get('/student/dashboard');

        $response->assertForbidden();
    }

    public function test_student_sees_own_profile(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $school->id,
            'name' => 'Self Student',
            'registration_number' => 'REG-99',
        ]);

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertOk();
        $response->assertSee('Self Student');
        $response->assertSee('REG-99');
    }

    public function test_student_sees_own_attendance(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-08-20',
            'session' => 'morning',
            'status' => 'present',
            'recorded_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($student)->get('/student/attendance');

        $response->assertOk();
        $response->assertSee('Present');
    }

    /**
     * Structural isolation check the task doc calls for: with no route
     * parameter to manipulate, a student hitting another student's
     * attendance is not a 404-vs-403 question the way it is for
     * ParentPortal — it's simply not expressible as a request. This test
     * confirms that by exercise: two students, each their own record
     * count, never the other's.
     */
    public function test_two_students_each_see_only_their_own_attendance(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $studentRole = $this->makeRole('student');

        $studentA = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'name' => 'Student A']);
        $studentB = $this->makeUser(['role_id' => $studentRole->id, 'school_id' => $school->id, 'name' => 'Student B']);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $studentA->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-08-20',
            'session' => 'morning',
            'status' => 'present',
            'recorded_at' => Carbon::now(),
        ]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $studentB->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-08-20',
            'session' => 'morning',
            'status' => 'absent',
            'recorded_at' => Carbon::now(),
        ]);
        AttendanceRecord::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $studentB->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-08-21',
            'session' => 'morning',
            'status' => 'absent',
            'recorded_at' => Carbon::now(),
        ]);

        $responseA = $this->actingAs($studentA)->get('/student/attendance');
        $responseB = $this->actingAs($studentB)->get('/student/attendance');

        $responseA->assertOk();
        $responseA->assertViewHas('records', fn ($records) => $records->count() === 1);

        $responseB->assertOk();
        $responseB->assertViewHas('records', fn ($records) => $records->count() === 2);
    }
}
