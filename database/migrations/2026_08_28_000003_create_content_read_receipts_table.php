<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 13-schoolos-database-schema-v2.md §6
//
// Schema exactly as specified there (school_id, user_id, entity_type,
// entity_id, read_at), with 'announcement' added to the entity_type enum
// — 13 §6 describes this table as "a general read-receipt tracker for
// any posted content type, attendance included only incidentally," and
// announcements are exactly the next posted-content type this pass adds.
//
// Scope note: this migration and its model (ContentReadReceipt) are
// built now as the schema investment 14 §5 calls for, but no write path
// (a "mark as read" endpoint) is wired to it in this pass — that belongs
// to the follow-up pass that completes Phase 5's read-side work,
// alongside per-announcement show()/detail endpoints. Building the table
// ahead of its full wiring has direct precedent in this codebase
// (ClassTeacherAssignmentRepository predated any controller calling it).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('entity_type', ['image', 'video', 'assignment', 'homework', 'announcement']);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('read_at');

            $table->unique(['user_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_read_receipts');
    }
};
