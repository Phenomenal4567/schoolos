<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 22-schoolos-finance-schema.md §5 (discovery §13.2).
 *
 * Admin\ExpenseController's HTTP-layer coverage — admin-only,
 * tenantScope()-only (no relationshipScope dimension, no parent
 * visibility, per that schema section's own note).
 */
class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/expenses');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->get('/admin/expenses');

        $response->assertForbidden();
    }

    public function test_admin_can_record_an_expense(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/expenses', [
            'category' => 'operational',
            'amount' => '2500.00',
            'description' => 'Generator fuel',
            'incurred_on' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('admin.expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'school_id' => $school->id,
            'category' => 'operational',
            'amount' => '2500.00',
            'description' => 'Generator fuel',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_an_invalid_category_is_rejected_with_a_validation_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/expenses', [
            'category' => 'not-a-real-category',
            'amount' => '100.00',
            'incurred_on' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_admin_index_only_lists_their_own_schools_expenses(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeRoleUser('school_admin', $schoolA);
        $adminB = $this->makeRoleUser('school_admin', $schoolB);

        $expenseA = $this->makeExpense($schoolA, $adminA, ['category' => 'staff_payment']);
        $this->makeExpense($schoolB, $adminB, ['category' => 'other']);

        $response = $this->actingAs($adminA)->get('/admin/expenses');

        $response->assertOk();
        $response->assertViewHas('expenses', function ($expenses) use ($expenseA) {
            return $expenses->count() === 1 && $expenses->first()->id === $expenseA->id;
        });
    }
}