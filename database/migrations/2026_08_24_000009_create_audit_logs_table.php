<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §7a
// Closes blocking item 1 of the Audit's Final Verdict (audit_logs was required
// in prose three separate times — original handoff §24, Master Plan §31, and
// implicitly by F3/F18's remediation — and never once schema'd until now).
//
// Decision refs this table is the concrete mechanism for:
//   D2 — both schools.status transitions (active <-> suspended) must write a row here.
//   D3 — any write to class_sections.class_teacher_id via
//        ClassSectionRepository::assignClassTeacher() must write a row here.
//
// Numbered 000009 (after student_health_profiles) purely because it was drafted
// last; its only hard dependency is `users`/`schools` (both created in 000001/
// 000003), so it is safe to run any time after those two.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action'); // 'user.created', 'attendance.corrected',
                                       // 'school.suspended', 'school.reactivated',
                                       // 'class_section.class_teacher_assigned', ...
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
