<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds one login per role (all nine from RoleSeeder) for local/dev
 * testing. `super_admin` gets a null school_id per D5; every other
 * role is attached to the demo school from SchoolSeeder. Idempotent
 * via updateOrCreate on email. Password is the same for every account
 * below — this is for local testing only, never use this seeder or
 * these credentials outside a local/dev environment.
 */
class UserSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $school = School::where('name', 'Demo School')->firstOrFail();

        $users = [
            ['role' => 'super_admin', 'name' => 'Super Admin', 'email' => 'superadmin@schoolos.test', 'school_id' => null],
            ['role' => 'school_admin', 'name' => 'School Admin', 'email' => 'schooladmin@schoolos.test', 'school_id' => $school->id],
            ['role' => 'teacher', 'name' => 'Demo Teacher', 'email' => 'teacher@schoolos.test', 'school_id' => $school->id],
            ['role' => 'student', 'name' => 'Demo Student', 'email' => 'student@schoolos.test', 'school_id' => $school->id],
            ['role' => 'parent', 'name' => 'Demo Parent', 'email' => 'parent@schoolos.test', 'school_id' => $school->id],
            ['role' => 'accountant', 'name' => 'Demo Accountant', 'email' => 'accountant@schoolos.test', 'school_id' => $school->id],
            ['role' => 'librarian', 'name' => 'Demo Librarian', 'email' => 'librarian@schoolos.test', 'school_id' => $school->id],
            ['role' => 'receptionist', 'name' => 'Demo Receptionist', 'email' => 'receptionist@schoolos.test', 'school_id' => $school->id],
            ['role' => 'staff', 'name' => 'Demo Staff', 'email' => 'staff@schoolos.test', 'school_id' => $school->id],
        ];

        foreach ($users as $u) {
            $role = Role::where('key', $u['role'])->firstOrFail();

            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'school_id' => $u['school_id'],
                    'role_id' => $role->id,
                    'name' => $u['name'],
                    'password' => self::PASSWORD,
                    'status' => 'active',
                ]
            );
        }
    }
}
