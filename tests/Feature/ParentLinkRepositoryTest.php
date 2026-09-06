<?php

namespace Tests\Feature;

use App\Exceptions\ParentLink\InvalidParentLinkTargetFailure;
use App\Repositories\ParentLinkRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2's Phase 2 test gate
 *
 * Mirrors ClassTeacherAssignmentRepositoryTest's shape for the other
 * reconstructed Phase 2 write path.
 */
class ParentLinkRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_linking_a_parent_and_student_creates_the_row(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $link = (new ParentLinkRepository())->link($school->id, $parent->id, $student->id, $admin);

        $this->assertDatabaseHas('student_parent_links', [
            'id' => $link->id,
            'school_id' => $school->id,
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_linking_a_non_parent_is_rejected(): void
    {
        $school = $this->makeSchool();
        $notAParent = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(InvalidParentLinkTargetFailure::class);

        (new ParentLinkRepository())->link($school->id, $notAParent->id, $student->id, $admin);
    }

    public function test_linking_a_non_student_is_rejected(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $notAStudent = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $this->expectException(InvalidParentLinkTargetFailure::class);

        (new ParentLinkRepository())->link($school->id, $parent->id, $notAStudent->id, $admin);
    }

    public function test_linking_the_same_pair_twice_is_idempotent(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = new ParentLinkRepository();
        $first = $repository->link($school->id, $parent->id, $student->id, $admin);
        $second = $repository->link($school->id, $parent->id, $student->id, $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('student_parent_links', 1);
    }

    public function test_unlinking_sets_status_inactive_without_deleting_the_row(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = new ParentLinkRepository();
        $link = $repository->link($school->id, $parent->id, $student->id, $admin);

        $repository->unlink($link->id);

        $this->assertDatabaseHas('student_parent_links', [
            'id' => $link->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseCount('student_parent_links', 1);
    }

    public function test_relinking_after_unlink_reactivates_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $repository = new ParentLinkRepository();
        $link = $repository->link($school->id, $parent->id, $student->id, $admin);
        $repository->unlink($link->id);

        $relinked = $repository->link($school->id, $parent->id, $student->id, $admin);

        $this->assertSame($link->id, $relinked->id);
        $this->assertDatabaseHas('student_parent_links', [
            'id' => $link->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('student_parent_links', 1);
    }
}
