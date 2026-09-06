<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Onboarding & Authentication UI ("School
// Information" / "Academic Setup" / "Modules & Features" screens)
//
// Four new columns backing the public self-service onboarding flow
// (Public\SchoolOnboardingController) — this is deliberately the
// smallest real set, not a speculative wide schema:
//
// - education_levels: which broad bands (creche/nursery/primary/jss/sss)
//   the school selected in "Academic Setup". Informational plus a
//   one-time seed: SchoolOnboardingController creates starter Standard
//   rows for each selected level (13-schoolos-database-schema-v2.md's
//   own "schools must control their own structure" rule is still
//   respected — these are ordinary, freely renamable/deletable Standard
//   rows via the existing Admin\StandardController, not a hardcoded
//   enum anywhere in application logic).
// - grading_system: stored school preference only (e.g. 'percentage')
//   — not wired into ExamMark/ExamResultService's actual computation,
//   which already works in raw marks_obtained/max_marks regardless of
//   label. Honest about that scope in School's own doc comment.
// - allow_manual_promotion: real gate, checked by
//   Admin\PromotionController::override() before an admin can manually
//   promote a student — defaults true so every existing school (and
//   every existing test's makeSchool() fixture, which never sets this)
//   keeps today's behavior unchanged.
// - enabled_modules: which of the "Modules & Features" cards were
//   selected. Nullable — School::enabledModules() (not a raw attribute
//   read) is what callers use, and returns "everything enabled" when
//   null, so a school created before this column existed (or via the
//   still-supported SuperAdmin\SchoolController path, which this
//   onboarding flow does not replace) is never silently gated out of
//   features it always had.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('education_levels')->nullable()->after('working_days');
            $table->string('grading_system')->nullable()->after('education_levels');
            $table->boolean('allow_manual_promotion')->default(true)->after('grading_system');
            $table->json('enabled_modules')->nullable()->after('allow_manual_promotion');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['education_levels', 'grading_system', 'allow_manual_promotion', 'enabled_modules']);
        });
    }
};
