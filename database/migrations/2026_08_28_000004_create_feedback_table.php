<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 18-schoolos-communication-domain-map.md §3, §8
//
// The fourth Communication-phase entity, distinct from Announcement (see
// 18 §3's rationale: staff-authored broadcast vs. parent/student-
// initiated message tied to a specific student). Field list is the
// minimal shape 18 §8 called for and deliberately left open beyond that
// ("message body, category, a target recipient ... threading/replies ...
// deferred here, same as 17 §7 deferred Assignment's field list"):
//
// - student_id: the generic scoping column (18 §4) — ScopeService's
//   existing student_id dispatch covers parent/student reads with no new
//   code, the same shape 17 §3 established for AssignmentSubmission.
// - recipient_type/recipient_teacher_id: "a specific teacher vs. 'the
//   school' generally" (18 §8) — recipient_teacher_id is nullable and
//   only meaningful when recipient_type = 'teacher', enforced at the
//   FeedbackRepository::create() write path, not a DB constraint (same
//   "invariant lives in the repository, not the schema" posture D3
//   established for class_teacher_id).
// - No threading/replies columns — genuinely deferred, not decided here.
//   A reply is a distinct future concern (who may reply, is it a new row
//   or an update) that doesn't change this table's read/write scoping
//   model either way.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('author_id')->constrained('users');
            $table->string('category')->nullable();
            $table->text('message');
            $table->enum('recipient_type', ['school', 'teacher']);
            $table->foreignId('recipient_teacher_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
