<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: school_management_system_discovery_hierarchy.md §2.2
// (student ID format "SCH + ADE + SS2 + 001", where SCH = "school
// initials"). Decision ref: 16-schoolos-decisions-register.md D12.
//
// 'short_code' is new: no existing column carries a stable school
// abbreviation, and deriving initials from `schools.name` on the fly
// (rather than persisting them) would make every already-issued
// student_id/staff_id silently change if a school is ever renamed —
// a school's issued IDs must survive a name edit unchanged, the same
// way `staff_attendance_records`'s doc comment treats a UNIQUE
// constraint as the source of truth rather than a runtime recomputation.
// Nullable + backfilled lazily by IdentifierService::resolveShortCode()
// the first time a school needs one, rather than a data migration that
// would have to guess every existing school's intended abbreviation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('short_code')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('short_code');
        });
    }
};
