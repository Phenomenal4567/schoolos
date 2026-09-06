<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
// §7.3 — "E-Textbooks"), 20-phase-6-8-execution-prompt.md §3.
//
// Column shape is copied verbatim from 19 §6's "Build implies" block —
// no uploaded_by/author column, same restraint calendar_events'
// migration applied (D10: "this pass adds no columns beyond what that
// gap-closure plan already specified"). class_section_id and
// subject_id are both nullable per 19 §6 ("nullable if some materials
// are school-wide, not class-specific") — this is the one column-shape
// difference from scheme_of_work's migration (that table's
// class_section_id/subject_id are NOT NULL), and it's why this
// resource's read side cannot reuse ResolvesScopedAcademicResource's
// generic dispatch unmodified the way scheme_of_work does:
// ScopeService::relationshipScope()'s generic class_section_id branch
// (teacherRelationshipScope()/parentRelationshipScope()/
// studentRelationshipScope(), see app/Services/ScopeService.php) does a
// plain whereIn('class_section_id', ...), which would silently hide
// every school-wide (NULL class_section_id) row from every
// teacher/parent/student — the exact "school-wide bypass +
// class-scoped narrowing" two-branch shape
// AnnouncementRepository::visibleTo()/findVisibleTo() already solved
// for the identical nullable-class_section_id problem on Announcement.
// LearningMaterialRepository::visibleTo()/findVisibleTo() apply that
// same two-branch merge rather than a third copy of the reasoning.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('class_section_id')->nullable()->constrained('class_sections');
            $table->foreignId('subject_id')->nullable()->constrained('subjects');
            $table->string('title');
            $table->string('file_path');
            $table->enum('material_type', ['textbook', 'notes', 'other']);
            $table->timestamps();

            $table->index(['school_id', 'class_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_materials');
    }
};
