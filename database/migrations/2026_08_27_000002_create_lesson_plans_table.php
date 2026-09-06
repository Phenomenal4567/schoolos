<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
//
// Carries class_section_id — covered by ScopeService's existing generic
// dispatch, same reasoning as timetable_slots' migration comment.
//
// status/reviewed_by/reviewed_at/review_note exist specifically to carry
// LessonPlanRepository::approve()/reject() (built in this same pass) —
// this is the direct schema-level fix for F30/F31 (approve/reject with
// no scoping and no role check at all in the reference codebase). A
// lesson plan is authored by a teacher (teacher_id) and reviewed by a
// school_admin (reviewed_by) — 17 §4 already settled that school_admin
// is the SchoolOS stand-in for GegoK12's principal role and needs no new
// Role row, so reviewed_by is a plain FK to users, not a role-specific
// table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('users');
            $table->string('title');
            $table->text('content');
            $table->string('document_path')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
