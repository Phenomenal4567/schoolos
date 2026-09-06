<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 14-schoolos-implementation-plan.md §5
//
// Laravel's standard database-notifications shape, deliberately not a
// bespoke table: App\Models\User already has the `Notifiable` trait
// (added ahead of this pass, unused until now) which expects exactly this
// schema — `notifiable_id`/`notifiable_type` is the polymorphic target,
// `data` is the per-notification payload, `read_at` is that
// notification's own read state. Building a parallel custom
// `notifications` table here would duplicate a mechanism Laravel already
// provides and the User model was already prepared for.
//
// No school_id column: every read of this table in this codebase goes
// through $user->notifications() (see NotificationController classes),
// which is inherently scoped to one already-authenticated user via
// notifiable_id — there is no query path that lists across users, so
// there is no tenant-leakage surface a school_id column would close.
// This is a deliberate exception to "every table carries school_id" for
// that reason, not an oversight.
//
// Distinct from `content_read_receipts` (13-schoolos-database-schema-v2.md
// §6): a notification's read_at is "did the user dismiss/open this
// notification," not "has the user viewed the underlying content" — the
// two are independent and both exist in this schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
