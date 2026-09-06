<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 14-schoolos-implementation-plan.md §5 ("notifications,
// announcements, parent communication")
//
// audience_type/class_section_id carry the same "generic class_section_id
// dispatch — no new ScopeService code" shape TimetableSlot/LessonPlan/
// Assignment/Exam already use (17-schoolos-academic-domain-map.md §3):
// a 'class_section' announcement is visible through
// ScopeService::relationshipScope()'s existing class_section_id branches
// for teacher/parent/student, with no changes needed to that class. A
// 'school' announcement has class_section_id = null and bypasses
// relationship narrowing entirely (see AnnouncementRepository::visibleTo())
// — the two audience types are therefore two different query branches at
// the read side, not two different tables.
//
// No draft/scheduled state: this pass publishes on create (published_at
// is set at insert time, not editable). A draft workflow analogous to
// LessonPlan's status column is a reasonable future addition but isn't
// part of this pass's scope — see AnnouncementRepository's doc comment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('author_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->enum('audience_type', ['school', 'class_section']);
            $table->foreignId('class_section_id')->nullable()->constrained('class_sections');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['school_id', 'audience_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
