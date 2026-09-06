<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Timetable Management module (School Admin requirement: "If the
// school has a dedicated academic/timetable officer, the School Admin
// should be able to grant that staff member appropriate
// timetable-management permissions").
//
// roles/permissions (13-schoolos-database-schema-v2.md §2, D4) are
// GLOBAL platform vocabulary shared by every school on purpose — a
// role_permissions grant can't express "this one staff member, at this
// one school" without breaking that guarantee for every other school.
// A per-user delegation therefore can't be modeled as a Role/Permission
// row without reopening D4. staff_profiles is already the natural
// one-row-per-staff-user_id home for this: a boolean here is a single
// school_admin-owned toggle, checked by
// EnsureCanManageTimetable, scoped to exactly the one user it's set on.
// Defaults false — delegation is opt-in per staff member, never implied
// by role.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->boolean('can_manage_timetable')->default(false)->after('rules_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('can_manage_timetable');
        });
    }
};
