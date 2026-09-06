<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 13-schoolos-database-schema-v2.md §7b (schema was reserved,
// not built — see that doc's own note: "No reference implementation
// exists anywhere in the GegoK12 audit for this module"). Decision refs:
// 16-schoolos-decisions-register.md D12 (QR method), D13 (face-
// verification retention). Execution ref:
// 20-phase-6-8-execution-prompt.md §4.
//
// Columns match 13 §7b verbatim, with one deliberate omission: no image/
// photo column of any kind on this table. D13 requires that no
// face-verification image is ever persisted to disk/storage — adding a
// column here (even a nullable one nobody's told to write to) would be
// exactly the kind of structural invitation D13 was written to close
// off. verification_result alone (the human-visible surface D13
// specifies) is what this table stores for a face-verification attempt;
// the image itself never reaches a migration, model, or table at all.
//
// method enum keeps 'fingerprint' and 'gps' as reserved-but-unimplemented
// values, matching 13 §7b's own framing — no decision has been recorded
// for either (D13 explicitly scopes itself to face-verification images
// only; fingerprint isn't in discovery §5's method list at all). Only
// 'manual', 'qr', 'selfie', and 'face' are accepted by
// StaffAttendanceRepository::checkIn() — see that class's doc comment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('user_id')->constrained('users');
            $table->date('date');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->enum('method', ['manual', 'qr', 'selfie', 'face', 'fingerprint', 'gps']);
            $table->enum('verification_result', ['verified', 'unverified', 'pending', 'rejected'])->nullable();
            $table->enum('status', ['present', 'late', 'absent', 'early_departure', 'excused']);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_records');
    }
};
