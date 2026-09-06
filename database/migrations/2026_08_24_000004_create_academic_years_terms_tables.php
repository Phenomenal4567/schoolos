<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit ref: 13-schoolos-database-schema-v2.md §3
// academic_terms is NEW relative to the original 13-schoolos-database-
// schema.md — referenced in prose across two documents (original handoff
// §13/§20, Master Plan §4/§20) but never previously schema'd (audit §11).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('label'); // '2026-2027'
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->string('name'); // 'Term 1', 'Semester 1'
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_years');
    }
};
