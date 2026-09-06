<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §2
 * Decision ref: 16-schoolos-decisions-register.md D4
 *
 * Seeds the exact role set already named in this table's own migration
 * comment (2026_08_24_000002_create_roles_permissions_tables.php) — no
 * role here is invented by this seeder, all nine are already a documented
 * commitment, this just makes the table non-empty.
 *
 * D4: roles are global platform vocabulary, not per-school — this seeder
 * runs once, is idempotent (updateOrCreate on `key`), and is safe to
 * re-run in any environment without duplicating rows.
 *
 * Ground rule (14 §0): every user needs a role_id (`users.role_id` is
 * NOT NULL — 13 §2), so this seeder is a hard prerequisite for creating
 * any user at all, including in Phase 1. It's listed here even though
 * some of these roles' actual domains (accountant → finance, librarian →
 * library) are explicitly out of scope until Phase 6-8 (14 §6) — the
 * *role* existing now doesn't imply its domain is built; a user can be
 * tagged `role = 'accountant'` in Phase 1 with nothing yet in the app
 * that treats that role specially, which is fine and expected.
 *
 * Deliberately NOT seeded here: `stock_keeper`. F17 found this role has
 * an authorization identity in GegoK12 (usergroup_id, middleware alias)
 * but zero actual behavior behind it, and the roles migration's own
 * comment doesn't list it either — 12 §9's traceability table doesn't
 * carry F17 into a SchoolOS design decision. If/when Stock Keeper gets a
 * real net-new design (F17's recommendation), it's added here as part of
 * that work, not seeded speculatively now.
 */
class RoleSeeder extends Seeder
{
    /**
     * @var array<string, string> role key => display label
     */
    private const ROLES = [
        // Phase 1 roles — actually used starting this phase.
        'super_admin' => 'Super Admin',
        'school_admin' => 'School Admin',
        'teacher' => 'Teacher',
        'student' => 'Student',
        'parent' => 'Parent',

        // Named in the roles migration's own comment, but their domains
        // are explicitly deferred (14 §6: finance/payroll, library,
        // transport, HR need their own domain-map pass before schema/
        // implementation work starts). Seeded now so the global
        // vocabulary is complete and stable per D4 — not because any
        // Phase 1 code path checks for them yet.
        'accountant' => 'Accountant',
        'librarian' => 'Librarian',
        'receptionist' => 'Receptionist',
        'staff' => 'Staff',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $key => $label) {
            Role::updateOrCreate(['key' => $key], ['label' => $label]);
        }
    }
}
