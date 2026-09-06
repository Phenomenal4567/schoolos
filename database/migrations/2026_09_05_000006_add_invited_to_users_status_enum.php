<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Account Creation & Onboarding plan, "New: Invitation
// mechanism"
//
// Widens users.status (enum('active','inactive','exited') since
// 2026_08_24_000003) to add 'invited' — same ->change() idiom already
// used by 2026_08_31_000003 to widen content_read_receipts.entity_type,
// which that migration's own comment confirms works against both this
// app's sqlite test connection and a MySQL production connection with no
// doctrine/dbal dependency.
//
// 'invited' means "a User row exists (role/school already assigned) but
// no one has ever set a real, usable password for it" —
// InvitationRepository::issue() is the only place that sets it,
// InvitationRepository::accept() is the only place that clears it back to
// 'active'. AuthenticationService::authenticate() doesn't need a new
// branch for this: an 'invited' row's password is already an unusable
// Str::random(32)/Str::random(64), so Hash::check() already fails it the
// same way a wrong password would — no separate "is this account even
// activated yet" check is needed at login time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'exited', 'invited'])
                ->default('active')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'exited'])
                ->default('active')
                ->change();
        });
    }
};
