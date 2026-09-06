<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Account Creation & Onboarding plan, "New: Invitation
// mechanism"
//
// One invitation mechanism, reused for teacher/staff/parent/student/
// school-admin activation — the same "one write path, several callers"
// shape AttendanceRepository/SubjectAttendanceRepository already
// establish for their own domains.
//
// user_id is NOT NULL: every invitation targets an already-created User
// row (status = 'invited', an unusable random password — the same
// pattern AdmissionApplicationRepository::accept() already uses for a
// freshly admitted student) rather than deferring account creation to
// accept time. This keeps "who is this invitation for, which school,
// which role" answerable from the User row itself at every point in the
// flow, with no separate un-attached invitation state to reconcile.
//
// token_hash, not the plain token, is what's stored — mirrors Laravel's
// own password-reset-token convention: a DB read (backup, replication,
// accidental log) never exposes a usable token, only
// InvitationRepository::accept()'s hash() of the caller's input can ever
// match a row.
//
// status is a real three-value enum (not just "used_at is null"),
// because 'revoked' is a distinct fact from 'never accepted' — an admin
// revoking an invitation before it's opened must not look identical to
// one still pending, since InvitationRepository::accept() needs to tell
// a revoked token apart from one that's simply expired or wrong.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('role_id')->constrained('roles');
            $table->string('token_hash')->unique();
            $table->enum('status', ['pending', 'accepted', 'revoked'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
