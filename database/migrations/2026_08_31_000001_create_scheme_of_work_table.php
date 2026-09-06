<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
// §7.1 — "Scheme of Work"), 20-phase-6-8-execution-prompt.md §3.
//
// Column shape is copied verbatim from 19 §6's "Build implies" block —
// this pass adds no columns beyond what that gap-closure plan already
// specified, matching calendar_events' migration's own precedent (D10)
// for how a 19-sourced "Build implies" block gets turned into a
// migration. `content` is a text column, not a file path — same
// treatment as lesson_plans.content, since 19 §6 lists "file/text"
// without picking one and a text field is the smaller, already-proven
// shape in this codebase (no file-storage mechanism exists yet
// anywhere in this bundle to hang a file_path column's actual upload
// handling off of).
//
// Distinct from lesson_plans (17 §2, §3): scheme_of_work is
// admin-authored (uploaded_by -> users, gated to school_admin in
// SchemeOfWorkRepository::create()) and carries no status/review
// workflow — it is the syllabus a school hands down, not a teacher's
// record of what was actually delivered against it. class_section_id
// is what lets ScopeService::relationshipScope() cover reads of this
// model generically (same property LessonPlan's own migration comment
// documents) — no new scope mechanism needed for "teachers see the
// scheme for their assigned classes only" (20 §3's test-gate wording).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheme_of_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_term_id')->constrained('academic_terms');
            $table->foreignId('class_section_id')->constrained('class_sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->text('content');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'class_section_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_of_work');
    }
};
