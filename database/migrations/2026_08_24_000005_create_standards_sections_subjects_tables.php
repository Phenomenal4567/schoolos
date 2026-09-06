<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §3
// "sections" is the canonical term (audit §27, Contradiction C6) — "Arm" in
// the Master Product Plan is a display-label alias only, not a schema
// concept. subjects is NEW — it was referenced (subject_id FK) throughout
// the original 13-schoolos-database-schema.md but never actually defined
// there (audit §11, a genuine dangling-reference bug in the prior doc).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name'); // 'Grade 5'
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name'); // 'A' / 'B' — pooled per-school, not per-
                                    // standard, matching 05-database-map.md §2
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('standards');
    }
};
