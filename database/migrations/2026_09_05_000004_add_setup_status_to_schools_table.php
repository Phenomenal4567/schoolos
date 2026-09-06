<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Account Creation & Onboarding plan, §1 (School Admin
// onboarding)
//
// Tracks first-run setup wizard progress. Deliberately a plain
// enum + step counter, not a bigger state machine — the wizard itself
// never gates the admin dashboard (a school_admin can always jump
// straight there), so this column is read only to decide whether to show
// a "finish setting up" banner and which step to resume at, never to
// authorize anything.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->enum('setup_status', ['pending', 'in_progress', 'complete'])
                ->default('pending')
                ->after('status');
            $table->unsignedTinyInteger('setup_step')->default(1)->after('setup_status');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['setup_status', 'setup_step']);
        });
    }
};
