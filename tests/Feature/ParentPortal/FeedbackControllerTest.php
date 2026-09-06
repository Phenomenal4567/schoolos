<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §3, §4, §6, §8 (F27)
 *
 * ParentPortal\FeedbackController's HTTP-layer coverage — index/show via
 * ResolvesScopedAcademicResource's generic student_id dispatch, store()
 * via FeedbackRepository::create()'s ownership check (F27).
 */
class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/feedback');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/feedback');

        $response->assertForbidden();
    }

    /**
     * The "Send feedback" view is a plain `<form method="POST">` with
     * no fetch(), so a successful submit must redirect — see
     * TeacherPortal\TimetableControllerTest's identical
     * assertRedirect()-over-assertCreated() shape.
     */
    public function test_a_linked_parent_can_send_feedback_for_their_child(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $child);

        $response = $this->actingAs($parent)->post('/parent/feedback', [
            'student_id' => $child->id,
            'message' => 'Great progress this term.',
            'recipient_type' => 'school',
        ]);

        $response->assertRedirect(route('parent.feedback.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('feedback', [
            'school_id' => $school->id,
            'student_id' => $child->id,
            'author_id' => $parent->id,
            'message' => 'Great progress this term.',
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
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $child);

        $response = $this->actingAs($parent)->postJson('/parent/feedback', [
            'student_id' => $child->id,
            'message' => 'Great progress this term.',
            'recipient_type' => 'school',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.student_id', $child->id);
    }

    public function test_a_linked_parent_can_index_and_show_feedback_for_their_child(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $child);
        $feedback = $this->makeFeedback($school, $child, $parent);

        $indexResponse = $this->actingAs($parent)->get('/parent/feedback');
        $indexResponse->assertOk();
        $indexResponse->assertViewIs('parent.feedback.index');
        $indexResponse->assertViewHas('feedback', fn ($items) => $items->contains('id', $feedback->id));

        $showResponse = $this->actingAs($parent)->get("/parent/feedback/{$feedback->id}");
        $showResponse->assertOk();
        $showResponse->assertViewIs('parent.feedback.show');
        $showResponse->assertViewHas('feedback', fn ($viewFeedback) => $viewFeedback->id === $feedback->id);
    }

    public function test_a_parent_cannot_file_feedback_under_a_student_who_is_not_their_linked_child(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($parent)->post('/parent/feedback', [
            'student_id' => $otherStudent->id,
            'message' => 'msg',
            'recipient_type' => 'school',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('feedback', ['student_id' => $otherStudent->id]);
    }

    public function test_a_parent_cannot_see_another_parents_feedback(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $child = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $child);
        $feedback = $this->makeFeedback($school, $child, $parent);

        $otherParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherParent)->get("/parent/feedback/{$feedback->id}");

        $response->assertNotFound();
    }
}
