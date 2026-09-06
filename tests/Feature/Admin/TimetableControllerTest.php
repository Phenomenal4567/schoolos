<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: Timetable Management module (School Admin build/edit/
 * delete, conflict detection, settings, publish/unpublish, delegation).
 */
class TimetableControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/timetable');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_non_delegated_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->get('/admin/timetable');

        $response->assertForbidden();
    }

    public function test_admin_can_view_all_timetables_for_their_school(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacherA = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $teacherB = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionA = $this->makeClassSection($school, $year, $teacherA);
        $sectionB = $this->makeClassSection($school, $year, $teacherB);
        $slotA = $this->makeTimetableSlot($school, $year, $sectionA, $subject, $teacherA);
        $slotB = $this->makeTimetableSlot($school, $year, $sectionB, $subject, $teacherB);

        $indexResponse = $this->actingAs($admin)->get('/admin/timetable');

        $indexResponse->assertOk();
        $indexResponse->assertViewIs('admin.timetable.index');
        $indexResponse->assertViewHas('slots', fn ($slots) => $slots->contains('id', $slotA->id) && $slots->contains('id', $slotB->id));

        $showResponse = $this->actingAs($admin)->get("/admin/timetable/{$slotA->id}");

        $showResponse->assertOk();
        $showResponse->assertViewIs('admin.timetable.show');
        $showResponse->assertViewHas('slot', fn ($slot) => $slot->id === $slotA->id);
    }

    public function test_admin_cannot_view_another_schools_timetable_slot(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherSubject = $this->makeSubject($otherSchool);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $otherSchool->id]);
        $otherSection = $this->makeClassSection($otherSchool, $otherYear, $otherTeacher);
        $otherSlot = $this->makeTimetableSlot($otherSchool, $otherYear, $otherSection, $otherSubject, $otherTeacher);

        $indexResponse = $this->actingAs($admin)->get('/admin/timetable');

        $indexResponse->assertOk();
        $indexResponse->assertViewHas('slots', fn ($slots) => ! $slots->contains('id', $otherSlot->id));

        $this->actingAs($admin)
            ->get("/admin/timetable/{$otherSlot->id}")
            ->assertNotFound();
    }

    public function test_admin_can_create_a_slot_for_any_teacher(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $year, $teacher);

        $response = $this->actingAs($admin)->post('/admin/timetable', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
            'room' => 'Room 4',
        ]);

        $response->assertRedirect(route('admin.timetable.index'));
        $this->assertDatabaseHas('timetable_slots', [
            'school_id' => $school->id,
            'class_section_id' => $classSection->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
            'room' => 'Room 4',
        ]);
    }

    public function test_creating_a_slot_notifies_the_assigned_teacher(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $year, $teacher);

        $this->actingAs($admin)->post('/admin/timetable', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $teacher->id,
            'notifiable_type' => \App\Models\User::class,
        ]);
    }

    public function test_double_booking_the_same_class_section_slot_is_rejected(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subjectOne = $this->makeSubject($school);
        $subjectTwo = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacherA = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $teacherB = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $year, $teacherA);
        $this->makeTimetableSlot($school, $year, $classSection, $subjectOne, $teacherA, [
            'day_of_week' => 'tuesday',
            'period_number' => 3,
        ]);

        $response = $this->actingAs($admin)->post('/admin/timetable', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subjectTwo->id,
            'teacher_id' => $teacherB->id,
            'day_of_week' => 'tuesday',
            'period_number' => 3,
        ]);

        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('timetable_slots', 1);
    }

    public function test_double_booking_the_same_teacher_across_sections_is_rejected(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionA = $this->makeClassSection($school, $year, $teacher);
        $sectionB = $this->makeClassSection($school, $year, $teacher);
        $this->makeTimetableSlot($school, $year, $sectionA, $subject, $teacher, [
            'day_of_week' => 'wednesday',
            'period_number' => 2,
        ]);

        $response = $this->actingAs($admin)->post('/admin/timetable', [
            'class_section_id' => $sectionB->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'wednesday',
            'period_number' => 2,
        ]);

        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('timetable_slots', 1);
    }

    public function test_double_booking_the_same_room_is_rejected(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacherA = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $teacherB = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $sectionA = $this->makeClassSection($school, $year, $teacherA);
        $sectionB = $this->makeClassSection($school, $year, $teacherB);
        $this->makeTimetableSlot($school, $year, $sectionA, $subject, $teacherA, [
            'day_of_week' => 'thursday',
            'period_number' => 5,
            'room' => 'Lab 1',
        ]);

        $response = $this->actingAs($admin)->post('/admin/timetable', [
            'class_section_id' => $sectionB->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
            'day_of_week' => 'thursday',
            'period_number' => 5,
            'room' => 'Lab 1',
        ]);

        $response->assertSessionHasErrors('class_section_id');
        $this->assertDatabaseCount('timetable_slots', 1);
    }

    public function test_admin_can_update_and_delete_a_slot(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $year, $teacher);
        $slot = $this->makeTimetableSlot($school, $year, $classSection, $subject, $teacher);

        $updateResponse = $this->actingAs($admin)->put("/admin/timetable/{$slot->id}", [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'friday',
            'period_number' => 6,
        ]);

        $updateResponse->assertRedirect(route('admin.timetable.index'));
        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id, 'day_of_week' => 'friday', 'period_number' => 6]);

        $destroyResponse = $this->actingAs($admin)->delete("/admin/timetable/{$slot->id}");

        $destroyResponse->assertRedirect(route('admin.timetable.index'));
        $this->assertDatabaseMissing('timetable_slots', ['id' => $slot->id]);
    }

    public function test_admin_can_save_period_and_working_day_settings(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->put('/admin/timetable/settings', [
            'periods' => [
                ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:40'],
                ['period_number' => 2, 'start_time' => '08:40', 'end_time' => '09:20'],
            ],
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_periods', [
            'school_id' => $school->id,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:40:00',
        ]);
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $school->fresh()->working_days);
    }

    public function test_admin_can_publish_and_unpublish_the_current_years_timetable(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school, ['is_current' => true]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $publishResponse = $this->actingAs($admin)->post('/admin/timetable/publish');
        $publishResponse->assertRedirect();
        $this->assertDatabaseHas('timetable_publications', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'is_published' => true,
        ]);

        $unpublishResponse = $this->actingAs($admin)->post('/admin/timetable/unpublish');
        $unpublishResponse->assertRedirect();
        $this->assertDatabaseHas('timetable_publications', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'is_published' => false,
        ]);
    }

    /**
     * Delegation: a staff member with no admin role but
     * staff_profiles.can_manage_timetable = true can reach the same
     * timetable-management routes a school_admin can.
     */
    public function test_a_delegated_staff_member_can_manage_the_timetable(): void
    {
        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $delegate = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeStaffProfile($school, $delegate, ['can_manage_timetable' => true]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $year, $teacher);

        $response = $this->actingAs($delegate)->post('/admin/timetable', [
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
        ]);

        $response->assertRedirect(route('admin.timetable.index'));
        $this->assertDatabaseHas('timetable_slots', ['class_section_id' => $classSection->id, 'teacher_id' => $teacher->id]);
    }

    /**
     * Without the delegation flag set, an ordinary teacher is still
     * forbidden — the flag, not the role alone, is what grants access.
     */
    public function test_a_staff_member_without_the_delegation_flag_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $staff = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $this->makeStaffProfile($school, $staff, ['can_manage_timetable' => false]);

        $response = $this->actingAs($staff)->get('/admin/timetable');

        $response->assertForbidden();
    }
}
