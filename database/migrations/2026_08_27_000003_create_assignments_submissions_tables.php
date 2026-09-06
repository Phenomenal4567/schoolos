<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
//
// Two tables, grouped in one migration the same way class_sections +
// class_teacher_assignments and enrollments + parent_links were —
// assignments and assignment_submissions are introduced together and
// one exists only in service of the other.
//
// assignments carries class_section_id — covered by ScopeService's
// existing generic dispatch, no new code needed (17 §3).
//
// assignment_submissions is the direct schema-level fix for F29 (any
// teacher, any school, could write obtained_marks/comments onto any
// student's submission — zero scoping on the entire reference-codebase
// controller). It deliberately carries student_id + assignment_id, not
// class_section_id directly: a submission belongs to a student, not a
// section. student_id lets ScopeService's existing generic dispatch
// cover the parent/student side for free; assignment_id is what the new
// teacherRelationshipScope() branch (this pass, see ScopeService.php)
// joins through to assignments.class_section_id for the teacher side,
// since a teacher's relationship to a submission is "is this row's
// parent assignment on one of my assigned sections," not any
// relationship to the student directly (teachers have no
// student_parent_links-style table — that relationship is parent-only,
// 13 §3).
//
// obtained_marks/comments/graded_by/graded_at match the field names
// 15-academic-domain-map.md §3 (F29) confirms the reference controller
// actually writes — kept minimal per this task's own scope limit (no
// attachments, no late-submission policy, no resubmission workflow).
// UNIQUE(assignment_id, student_id) — one submission row per student per
// assignment, the same "make the mistake structurally impossible"
// posture as this bundle's other uniqueness constraints; a resubmission
// is an UPDATE to this row, not a second row (grading fields have no
// existing writer yet — this task builds scope/schema only, per its own
// Do NOT list — so nothing here contradicts that; the constraint is what
// keeps a future grading write path from having two ways to represent
// "graded twice").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('assignment_id')->constrained('assignments');
            $table->foreignId('student_id')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('obtained_marks', 6, 2)->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users');
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id'], 'assignment_submissions_unique_assignment_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
