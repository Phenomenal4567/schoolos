<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
// billing addition ("super admin should be able to set days for demo
// account creation").
//
// A single-row settings table, not a generic key-value store — there is
// exactly one platform-wide tunable today (trial_days), and a
// key-value table would be speculative generality for a value this app
// has never needed more than one of. App\Models\PlatformSetting::current()
// is the only way this row is ever read or written (firstOrCreate on a
// fixed id), so there's no risk of a second row ever existing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('trial_days')->default(14);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
