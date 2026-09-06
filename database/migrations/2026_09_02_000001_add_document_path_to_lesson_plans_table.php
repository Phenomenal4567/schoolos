<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lesson_plans', 'document_path')) {
            return;
        }

        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lesson_plans', 'document_path')) {
            return;
        }

        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropColumn('document_path');
        });
    }
};
