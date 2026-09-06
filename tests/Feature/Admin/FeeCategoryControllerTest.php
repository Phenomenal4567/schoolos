<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 22-schoolos-finance-schema.md §1 (discovery §10.1).
 *
 * Admin\FeeCategoryController's HTTP-layer coverage — store-only,
 * school-scoped authoring, plus the one guard this schema reserves:
 * 'rolled_over_debt' is system-seeded (D12) and never admin-creatable
 * directly, even though every other key is free-form.
 */
class FeeCategoryControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/admin/fee-categories', []);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->post('/admin/fee-categories', []);

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_fee_category(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/fee-categories', [
            'key' => 'sports',
            'label' => 'Sports Levy',
        ]);

        $response->assertRedirect(route('admin.fee-assessments.index'));
        $this->assertDatabaseHas('fee_categories', [
            'school_id' => $school->id,
            'key' => 'sports',
            'label' => 'Sports Levy',
            'is_system_reserved' => false,
        ]);
    }

    public function test_the_reserved_rolled_over_debt_key_cannot_be_created_directly(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/fee-categories', [
            'key' => 'rolled_over_debt',
            'label' => 'Sneaky',
        ]);

        $response->assertSessionHasErrors('key');
        $this->assertDatabaseCount('fee_categories', 0);
    }
}