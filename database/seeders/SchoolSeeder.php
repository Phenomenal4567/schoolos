<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

/**
 * Seeds one demo school so non-super_admin roles (which require a
 * non-null school_id per D5) have a school to belong to for local
 * testing. Idempotent via updateOrCreate on the unique `name`.
 */
class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        School::updateOrCreate(
            ['name' => 'Demo School'],
            [
                'email' => 'demo.school@schoolos.test',
                'phone' => '08000000000',
                'status' => 'active',
            ]
        );
    }
}
