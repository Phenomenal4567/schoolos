<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §2
// Decision ref: 16-schoolos-decisions-register.md D4 — roles/permissions are
// GLOBAL platform vocabulary, deliberately no school_id here. Collapses
// GegoK12's usergroup_id + Laratrust roles + permission_user (03-role-
// permission-map.md §1-3, findings F8/F9) into one system.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'super_admin', 'school_admin', 'teacher',
                                              // 'student', 'parent', 'accountant',
                                              // 'librarian', 'receptionist', 'staff'
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // absorbs GegoK12's Laratrust sub-roles (principal/leave_checker/
            // leave_applier) as permissions on the Teacher role, per
            // 07-teacher-domain-map.md §3 and audit §10 (Contradiction C5)
            $table->string('key')->unique(); // 'approve_leave', 'approve_assignment',
                                              // 'approve_lesson_plan', 'approve_homework'
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
