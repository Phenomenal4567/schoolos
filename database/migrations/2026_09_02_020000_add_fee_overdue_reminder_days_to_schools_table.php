<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
//
// Per-school override of SendFeeReminders' overdue-repeat cadence
// (app/Console/Commands/SendFeeReminders.php), editable by that school's
// own school_admin through Admin\SchoolProfileController — same
// per-school-column discipline as every other school-profile field
// (School model's own doc comment). NOT NULL with a default matching the
// command's previous hardcoded constant, so existing schools keep
// today's behavior unless a school_admin changes it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('fee_overdue_reminder_days')->default(7)->after('school_type');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('fee_overdue_reminder_days');
        });
    }
};
