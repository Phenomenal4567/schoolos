<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 */
class ParentRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_registration_page_loads_for_a_valid_school_short_code(): void
    {
        $school = $this->makeSchool(['short_code' => 'GHS']);

        $response = $this->get('/register/parent?school=GHS');

        $response->assertOk();
    }

    public function test_an_unknown_short_code_404s(): void
    {
        $response = $this->get('/register/parent?school=DOES-NOT-EXIST');

        $response->assertNotFound();
    }

    public function test_registering_creates_the_parent_account_only_with_no_student_link(): void
    {
        $school = $this->makeSchool(['short_code' => 'GHS']);
        $this->makeRole('parent');

        $response = $this->post('/register/parent', [
            'school' => 'GHS',
            'name' => 'New Parent',
            'email' => 'new.parent@example.test',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertRedirect(route('parent.dashboard'));

        $parent = User::where('email', 'new.parent@example.test')->firstOrFail();
        $this->assertSame($school->id, $parent->school_id);
        $this->assertSame('parent', $parent->role->key);
        $this->assertSame('active', $parent->status);
        $this->assertDatabaseCount('student_parent_links', 0);
        $this->assertAuthenticatedAs($parent);
    }

    public function test_password_confirmation_mismatch_fails_validation(): void
    {
        $school = $this->makeSchool(['short_code' => 'GHS']);
        $this->makeRole('parent');

        $response = $this->post('/register/parent', [
            'school' => 'GHS',
            'name' => 'New Parent',
            'email' => 'mismatch@example.test',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §12
     * (Security Requirements — "users cannot assign themselves privileged
     * roles", "user-controlled school_id cannot override the
     * authenticated school context"). This controller never reads a
     * `role`/`role_id`/numeric `school_id` field from the request body at
     * all — it hardcodes the 'parent' role and resolves the school
     * server-side from the `school` short_code — so posting forged values
     * for either has no effect whatsoever; this test pins that down
     * rather than leaving it merely true-by-omission.
     */
    public function test_forged_role_and_school_id_fields_are_silently_ignored(): void
    {
        $school = $this->makeSchool(['short_code' => 'GHS']);
        $otherSchool = $this->makeSchool();
        $this->makeRole('parent');
        $this->makeRole('school_admin');

        $response = $this->post('/register/parent', [
            'school' => 'GHS',
            'name' => 'Aspiring Admin',
            'email' => 'aspiring.admin@example.test',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
            'role' => 'school_admin',
            'role_id' => 999999,
            'school_id' => $otherSchool->id,
        ]);

        $response->assertRedirect(route('parent.dashboard'));

        $created = User::where('email', 'aspiring.admin@example.test')->firstOrFail();
        $this->assertSame('parent', $created->role->key);
        $this->assertSame($school->id, $created->school_id);
    }

    public function test_duplicate_email_across_schools_is_rejected(): void
    {
        $school = $this->makeSchool(['short_code' => 'GHS']);
        $this->makeRole('parent');
        $this->makeUser(['email' => 'taken@example.test']);

        $response = $this->post('/register/parent', [
            'school' => 'GHS',
            'name' => 'New Parent',
            'email' => 'taken@example.test',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
