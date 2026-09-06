<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3 (discovery
// §1.3 — "School Calendar / Agenda"), 20-phase-6-8-execution-prompt.md §1.
//
// Column shape is copied verbatim from 19 §3's "Build implies" block —
// this pass adds no columns beyond what that gap-closure plan already
// specified. school_id + academic_year_id are both not-null (every
// event belongs to exactly one school's exactly one session);
// academic_term_id is nullable because whole-session events
// (resumption date, closing date) don't belong to a single term the way
// a mid-term break or exam period does.
//
// No new scope mechanism: school_id is the only column
// ScopeService::tenantScope() needs, and this table carries no
// class_section_id/student_id, so ScopeService::relationshipScope()'s
// generic dispatch is deliberately not invoked anywhere for this
// resource — calendar events are visible to every in-tenant
// parent/student uniformly (19 §3), not narrowed by section or child,
// which is the "simplest gap in this document" property 19 §3 calls
// out explicitly.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms');
            $table->string('title');
            $table->enum('event_type', [
                'term_date',
                'mid_term_break',
                'exam_period',
                'activity',
                'holiday',
                'closing_date',
                'other',
            ]);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('visible_to_parents')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
