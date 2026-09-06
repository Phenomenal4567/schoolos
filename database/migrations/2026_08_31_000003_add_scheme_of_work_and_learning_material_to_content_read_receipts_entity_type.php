<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 ("Both reuse
// the existing content_read_receipts mechanism ... for tracking whether
// students/parents have opened them — no new read-tracking mechanism
// needed"), 20-phase-6-8-execution-prompt.md §3.
//
// content_read_receipts.entity_type (13 §6, created by
// 2026_08_28_000003_create_content_read_receipts_table.php) was an
// enum(image, video, assignment, homework, announcement) with no
// 'scheme_of_work' or 'learning_material' value — reusing the
// mechanism for these two new content types means widening the enum,
// not building a second tracking table.
//
// ->change() rather than drop+recreate the column: Laravel 11 removed
// the doctrine/dbal requirement for column alterations (this app has no
// doctrine/dbal dependency at all — see composer.json), so a plain
// ->change() on the enum's value list works natively against both this
// app's sqlite test connection and a MySQL production connection,
// without the "no ->change() precedent in this codebase" concern a
// pre-Laravel-11 pass would have had to work around.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_read_receipts', function (Blueprint $table) {
            $table->enum('entity_type', [
                'image',
                'video',
                'assignment',
                'homework',
                'announcement',
                'scheme_of_work',
                'learning_material',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('content_read_receipts', function (Blueprint $table) {
            $table->enum('entity_type', [
                'image',
                'video',
                'assignment',
                'homework',
                'announcement',
            ])->change();
        });
    }
};
