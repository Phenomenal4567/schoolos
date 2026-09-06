<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
// billing addition.
//
// A school chooses, at signup (Public\SchoolOnboardingController), to
// start a time-limited 'trial' (trial_ends_at set from
// PlatformSetting::current()->trial_days) or go straight to 'paid'
// (trial_ends_at left null — no expiry to enforce). Default 'trial'/null
// so every school that already existed before this column (both the
// SuperAdmin\SchoolController path and every existing test fixture)
// keeps working with no expiry ever enforced against it — School::
// isTrialExpired() is false whenever trial_ends_at is null, regardless
// of billing_plan.
//
// This does not collect any payment — 'paid' only records that the
// school opted out of the trial clock. Actually charging a card is a
// distinct, unbuilt integration (the existing Paystack wiring in this
// app is scoped to student fee payments within a school, a different
// domain entirely) — see School's own doc comment for this same caveat.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->enum('billing_plan', ['trial', 'paid'])->default('trial')->after('enabled_modules');
            $table->timestamp('trial_ends_at')->nullable()->after('billing_plan');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['billing_plan', 'trial_ends_at']);
        });
    }
};
