<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * No DatabaseSeeder existed anywhere in the Phase 1 scaffolding pass —
 * this is the first one, wiring in RoleSeeder since it's a hard
 * prerequisite for creating any user (13 §2: users.role_id is NOT NULL).
 * Later phases' seeders (academic years, standards/sections for Phase 2,
 * etc.) get added to this call() list as they're written — this is not
 * meant to be a complete Phase 1 seed set on its own, just the one piece
 * that's actually blocking right now.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SchoolSeeder::class,
            UserSeeder::class,
        ]);
    }
}
