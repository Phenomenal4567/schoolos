<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
// Enrollment And Profiles").
//
// One row per staff user_id (UNIQUE) — a peer table to `users`, not more
// columns on it, since `users` is shared by every role (see that
// model's own doc comment) and these columns only ever apply to staff
// roles (StaffProfileRepository::STAFF_ROLE_KEYS). qualifications is
// JSON because it's genuinely list-shaped (a staff member can hold any
// number of them) — the one place in this schema JSON is already used
// for the same reason is admission_applications.documents/
// fee_category_acknowledgments. rules_acknowledged_at follows this
// schema's existing "timestamp presence = happened, null = hasn't yet"
// idiom (matching admission_applications.submitted_at/decided_at)
// rather than a separate acknowledgements table, since there is only
// ever one rules document per school to acknowledge, not a version
// history to track.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->json('qualifications')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('cv_path')->nullable();
            $table->timestamp('rules_acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
