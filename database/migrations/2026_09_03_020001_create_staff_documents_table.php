<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
// Enrollment And Profiles").
//
// One row per uploaded supporting document (many per staff user) —
// separate from staff_profiles.cv_path because "supporting documents"
// is plural and open-ended (certificates, ID, references), unlike the
// CV, which every staff member has at most one current copy of and so
// lives as a single column on the 1:1 profile row instead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('user_id')->constrained('users');
            $table->string('label');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};
