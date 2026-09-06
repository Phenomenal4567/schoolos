<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('initials', 20)->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('initials');
            $table->string('location')->nullable()->after('phone');
            $table->string('google_maps_url')->nullable()->after('location');
            $table->enum('school_type', ['creche', 'primary', 'secondary', 'college_tertiary', 'mixed'])->nullable()->after('google_maps_url');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'initials',
                'logo_path',
                'location',
                'google_maps_url',
                'school_type',
            ]);
        });
    }
};
