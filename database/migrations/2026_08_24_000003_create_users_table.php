<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §2
// Decision ref: D5 — school_id nullable ONLY for role='super_admin'. This is
// enforced at the application layer (AuthenticationService::createUser() /
// a DB-level CHECK where the RDBMS supports it), never inferred from a magic
// role value scattered across call sites the way GegoK12's usergroup_id==1
// bypass was (F3). See app/Services/ScopeService.php.
//
// email/mobile_no/registration_number are all UNIQUE — this is what makes
// AuthenticationService::resolveIdentifier() safe. GegoK12's checkschool
// validator could resolve the wrong user via a non-unique `name` match
// (F6); `name` is deliberately NOT a unique/lookup column here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools');
            $table->foreignId('role_id')->constrained('roles');
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('mobile_no')->unique()->nullable();
            $table->string('registration_number')->unique()->nullable();
            $table->string('password');
            $table->enum('status', ['active', 'inactive', 'exited'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
