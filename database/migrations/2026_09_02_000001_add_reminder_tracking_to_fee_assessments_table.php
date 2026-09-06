<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
//
// Two cached "last reminded at" columns, same discipline as
// fee_assessments.status: the one write path is
// SendFeeReminders (app/Console/Commands/SendFeeReminders.php), which is
// also the only reader that needs them. Nullable so "never reminded" is
// distinguishable from "reminded a moment ago" without a sentinel date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->timestamp('overdue_reminder_sent_at')->nullable()->after('status');
            $table->timestamp('deadline_reminder_sent_at')->nullable()->after('overdue_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('fee_assessments', function (Blueprint $table) {
            $table->dropColumn(['overdue_reminder_sent_at', 'deadline_reminder_sent_at']);
        });
    }
};
