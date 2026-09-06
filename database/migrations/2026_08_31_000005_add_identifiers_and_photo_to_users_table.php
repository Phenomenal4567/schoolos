<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: school_management_system_discovery_hierarchy.md §2.2, §2.3,
// §5 ("Staff ID Card"). Decision refs: 16-schoolos-decisions-register.md
// D12 (QR payload/verification service), D13 (face-verification
// retention). 20-phase-6-8-execution-prompt.md §4's build prompt names
// these two columns verbatim: "users.student_id/users.staff_id
// generation at enrollment per discovery §2.2's format."
//
// Deliberately separate from `registration_number` (13 §2): that column
// is the free-form, already-shipped Phase 1 LOGIN identifier
// (AuthenticationService::resolveIdentifier()) and is never touched by
// this track. student_id/staff_id are the discovery §2.2/§5 ID-CARD
// identifiers — display/print artifacts, not login credentials — kept
// as their own columns so a future change to either concept (e.g.
// re-issuing a lost ID card) never risks breaking login.
//
// `photo_path` is new and not named by any prior design doc: discovery
// §2.3/§5 both list a photograph as a required ID-card field, and D13's
// face-verification matcher needs a stored reference image to compare a
// live capture against — this is that one stored, non-expiring reference
// photo. It's a judgment call to reuse a single column for both purposes
// (ID-card photo and face-verification reference) rather than two,
// on the basis that they're the same photograph in every school's
// actual workflow (the ID-card photo *is* the enrollment photo) and
// discovery names no separate concept for a second one.
//
// Both student_id and staff_id are nullable + unique: nullable because
// they're assigned at enrollment (students) or on first use (staff, see
// IdentifierService's doc comment), not at row-creation time, so a
// freshly created user has neither yet; unique so two people can never
// print/scan the same card.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id')->nullable()->unique()->after('registration_number');
            $table->string('staff_id')->nullable()->unique()->after('student_id');
            $table->string('photo_path')->nullable()->after('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'staff_id', 'photo_path']);
        });
    }
};
