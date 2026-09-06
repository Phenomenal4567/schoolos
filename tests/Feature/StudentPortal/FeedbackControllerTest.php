<?php

namespace Tests\Feature\StudentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §3, §4, §6, §8 (F27)
 *
 * StudentPortal\FeedbackController's HTTP-layer coverage — see
 * ParentPortal\FeedbackControllerTest's doc comment for the shared shape;
 * a student's store() is self-only, enforced by
 * FeedbackRepository::create().
 */
class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/feedback');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/feedback');

        $response->assertForbidden();
    }

    /**
     * The "Send feedback" view is a plain `<form method="POST">` with
     * no fetch(), so a successful submit must redirect — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape.
     */
    public function test_a_student_can_send_feedback(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($student)->post('/student/feedback', [
            'student_id' => $student->id,
            'message' => 'I have a question about the assignment.',
            'recipient_type' => 'school',
        ]);

        $response->assertRedirect(route('student.feedback.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('feedback', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'author_id' => $student->id,
            'message' => 'I have a question about the assignment.',
            'recipient_type' => 'school',
        ]);
    }

    /**
     * A fetch()/API caller (Accept: application/json, via postJson())
     * keeps the original JSON 201 contract untouched by the wantsJson()
     * branch above.
     */
    public function test_a_json_caller_still_gets_a_201_with_the_created_feedback(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($student)->postJson('/student/feedback', [
            'student_id' => $student->id,
            'message' => 'I have a question about the assignment.',
            'recipient_type' => 'school',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.student_id', $student->id);
    }

    public function test_a_student_can_index_and_show_their_own_feedback(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $feedback = $this->makeFeedback($school, $student, $student);

        $indexResponse = $this->actingAs($student)->get('/student/feedback');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('student.feedback.index');
        $indexResponse->assertViewHas('feedback', fn ($items) => $items->contains('id', $feedback->id));

        $showResponse = $this->actingAs($student)->get("/student/feedback/{$feedback->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('student.feedback.show');
        $showResponse->assertViewHas('feedback', fn ($viewFeedback) => $viewFeedback->id === $feedback->id);
    }

    public function test_a_student_cannot_file_feedback_as_another_student(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($student)->post('/student/feedback', [
            'student_id' => $otherStudent->id,
            'message' => 'msg',
            'recipient_type' => 'school',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('feedback', ['student_id' => $otherStudent->id]);
    }

    public function test_a_student_cannot_see_another_students_feedback(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $feedback = $this->makeFeedback($school, $student, $student);

        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherStudent)->get("/student/feedback/{$feedback->id}");

        $response->assertNotFound();
    }
}
