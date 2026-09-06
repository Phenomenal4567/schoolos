<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
// onboarding)
//
// Widens student_parent_links.status (enum('active','inactive') since
// 2026_08_24_000007) to add 'pending' — same ->change() idiom as
// 2026_09_05_000006.
//
// 'pending' is written only by the new ParentLinkRepository::request()
// (a self-service parent identifying a child they want linked to) — never
// by link(), which stays the only writer of 'active' and is, per its own
// doc comment, "admin-initiated, not self-service." Admin\ParentLinkController's
// new approve()/reject() actions are what promote a 'pending' row to
// 'active' (by calling the existing link()) or remove it — the
// self-service request step itself never grants access on its own.
//
// ParentPortal\DashboardController's existing child-listing query already
// filters to active links only (it has no reason to know about a third
// status value), so a 'pending' row is invisible there for free — no
// change needed to prove a requested-but-unapproved child stays hidden.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_parent_links', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'pending'])
                ->default('active')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_parent_links', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])
                ->default('active')
                ->change();
        });
    }
};
