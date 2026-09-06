<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11 (discovery
// §15 — "Backup & Data Management"), 20-phase-6-8-execution-prompt.md §2.
// Decision ref: 16-schoolos-decisions-register.md D11 — storage
// location (local disk), retention window (7 days), and download
// access (any school_admin at the export's school) are closed. This
// table's shape was scaffolded ahead of that decision and needed no
// changes once it closed — see Services\ExportFileGenerator,
// Jobs\ProcessExportJob, and Admin\ExportJobController for what now
// writes file_path/expires_at/status.
//
// Column shape follows 19 §11's tracking-table sketch (status,
// requested_by, school_id, date_range, file_path, expires_at) with
// date_range expanded into concrete, validatable columns rather than a
// single opaque field — range_type dispatches which of the three
// following columns is authoritative, mirroring how calendar_events
// (20 §1) keeps academic_year_id/academic_term_id as separate typed
// columns instead of a generic range blob.
//
// expires_at is nullable because it's set at completion time by
// ExportFileGenerator (created_at + 7 days from when the file actually
// exists), not by ExportJobRepository::create() at request time — see
// that class's doc comment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('requested_by')->constrained('users');
            $table->enum('range_type', ['term', 'session', 'custom']);
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])
                ->default('queued');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
