<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design ref: 22-schoolos-finance-schema.md §1
// School-defined fee categories (discovery §10.1). is_system_reserved is
// true only for the 'rolled_over_debt' key (§2, D12) — a school-seeded
// row an admin cannot edit/delete, distinct from the five ordinary
// starter categories a school authors for itself.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->string('key');
            $table->string('label');
            $table->boolean('is_system_reserved')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['school_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_categories');
    }
};
