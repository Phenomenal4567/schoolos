<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_components')) {
            Schema::create('exam_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools');
                $table->string('name');
                $table->decimal('weight_percent', 5, 2);
                $table->timestamps();

                $table->unique(['school_id', 'name']);
            });
        }

        if (Schema::hasTable('exam_marks') && ! Schema::hasColumn('exam_marks', 'exam_component_id')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                $table->foreignId('exam_component_id')->nullable()->after('exam_id')->constrained('exam_components');
            });

            DB::table('exam_marks')
                ->select('school_id')
                ->distinct()
                ->orderBy('school_id')
                ->get()
                ->each(function (object $row): void {
                    $componentId = DB::table('exam_components')->insertGetId([
                        'school_id' => $row->school_id,
                        'name' => 'Exam',
                        'weight_percent' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('exam_marks')
                        ->where('school_id', $row->school_id)
                        ->whereNull('exam_component_id')
                        ->update(['exam_component_id' => $componentId]);
                });
        }

        if (Schema::hasTable('exam_marks') && ! Schema::hasColumn('exam_marks', 'status')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                $table->string('status')->default('draft')->after('max_marks');
            });

            DB::table('exam_marks')->update(['status' => 'published']);
        }

        if (Schema::hasTable('exam_marks')) {
            try {
                Schema::table('exam_marks', function (Blueprint $table) {
                    $table->dropUnique('exam_marks_unique_exam_student');
                });
            } catch (Throwable) {
                // Fresh databases already have the component-aware unique key.
            }

            try {
                Schema::table('exam_marks', function (Blueprint $table) {
                    $table->unique(['exam_id', 'student_id', 'exam_component_id'], 'exam_marks_unique_exam_student_component');
                });
            } catch (Throwable) {
                // Index already exists on fresh databases.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exam_marks') && Schema::hasColumn('exam_marks', 'status')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('exam_marks') && Schema::hasColumn('exam_marks', 'exam_component_id')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('exam_component_id');
            });
        }

        Schema::dropIfExists('exam_components');
    }
};
