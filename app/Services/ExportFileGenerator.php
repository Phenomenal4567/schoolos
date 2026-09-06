<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\ExamMark;
use App\Models\ExportJob;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * The single place that turns a `queued` ExportJob row into an actual
 * file on disk. Both triggers this track's test gate requires —
 * Admin\ExportJobController::store() (manual, a school_admin submits
 * the form) and Console\Commands\RunScheduledExports (automatic, a
 * school's term ending fires it without a human involved) — create the
 * ExportJob row through ExportJobRepository::create() and then dispatch
 * Jobs\ProcessExportJob, which calls generate() here and nowhere else.
 * "Manual and automatic triggers produce identical output" (the test
 * gate's third item) holds by construction, not by parallel
 * implementation kept in sync: there is exactly one code path that
 * writes a file for an ExportJob, and both triggers reach it through
 * the same job class.
 *
 * Storage (D11 item 1): writes to the 'local' filesystem disk
 * (storage/app/private per config/filesystems.php) — not 'public', not
 * S3. `file_path` is stored relative to that disk's root, matching
 * config/filesystems.php's 'local' driver, so nothing here hardcodes an
 * absolute path.
 *
 * Retention (D11 item 2): expires_at is computed here, at completion
 * time (created_at + 7 days), not at request time in
 * ExportJobRepository::create() — an export that takes an hour to
 * generate under load gets a full 7 days of availability from when the
 * file actually exists, not from when it was requested.
 *
 * Content scope: this bundle's "backup of student records" is built as
 * three CSVs (roster, attendance, exam marks) zipped together, scoped
 * to $job->school_id and the resolved date range. Neither 19 §11 nor
 * discovery §15 specify an exact record shape beyond "student
 * records" / "years of student records" — this is an engineering
 * default, not a product decision, so it's recorded here rather than
 * in the D11 register entry. Extending the export to cover more tables
 * (e.g. class_sections, exams metadata) is additive, not a breaking
 * change to this shape.
 */
class ExportFileGenerator
{
    private const RETENTION_DAYS = 7;

    public function generate(ExportJob $job): void
    {
        $job->update(['status' => 'processing']);

        try {
            [$startDate, $endDate] = $this->resolveDateRange($job);

            $zipPath = $this->buildZip($job, $startDate, $endDate);
            $relativePath = "exports/{$job->school_id}/export-{$job->id}-" . now()->format('Ymd-His') . '.zip';

            Storage::disk('local')->put($relativePath, file_get_contents($zipPath));
            @unlink($zipPath);

            $job->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'expires_at' => now()->addDays(self::RETENTION_DAYS),
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed']);

            throw $e;
        }
    }

    /**
     * @return array{0: ?string, 1: ?string} [start_date, end_date] as
     *         'Y-m-d' strings, or [null, null] if no bound can be
     *         resolved (e.g. a 'session' export whose academic_year has
     *         no academic_terms yet) — callers treat a null bound as
     *         "no lower/upper filter", not as an error, since an empty
     *         range shouldn't block the roster half of the export.
     */
    private function resolveDateRange(ExportJob $job): array
    {
        if ($job->range_type === 'custom') {
            return [
                $job->start_date?->toDateString(),
                $job->end_date?->toDateString(),
            ];
        }

        if ($job->range_type === 'term') {
            $term = AcademicTerm::find($job->academic_term_id);

            return [$term?->start_date?->toDateString(), $term?->end_date?->toDateString()];
        }

        // 'session' — academic_years has no start/end_date of its own
        // (13 §3), so the session's bounds are derived from the min/max
        // of its academic_terms' own dates rather than a column that
        // doesn't exist.
        $bounds = AcademicTerm::where('academic_year_id', $job->academic_year_id)
            ->selectRaw('MIN(start_date) as min_start, MAX(end_date) as max_end')
            ->first();

        return [$bounds?->min_start, $bounds?->max_end];
    }

    private function buildZip(ExportJob $job, ?string $startDate, ?string $endDate): string
    {
        $tmpDir = sys_get_temp_dir() . '/export-job-' . $job->id . '-' . uniqid();
        mkdir($tmpDir, 0700, true);

        file_put_contents($tmpDir . '/students.csv', $this->studentsCsv($job));
        file_put_contents($tmpDir . '/attendance.csv', $this->attendanceCsv($job, $startDate, $endDate));
        file_put_contents($tmpDir . '/exam_marks.csv', $this->examMarksCsv($job, $startDate, $endDate));

        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($tmpDir . '/students.csv', 'students.csv');
        $zip->addFile($tmpDir . '/attendance.csv', 'attendance.csv');
        $zip->addFile($tmpDir . '/exam_marks.csv', 'exam_marks.csv');
        $zip->close();

        foreach (glob($tmpDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($tmpDir);

        return $zipPath;
    }

    private function studentsCsv(ExportJob $job): string
    {
        $rows = StudentEnrollment::query()
            ->where('student_enrollments.school_id', $job->school_id)
            ->join('users', 'users.id', '=', 'student_enrollments.student_id')
            ->select([
                'student_enrollments.student_id',
                'users.name',
                'users.registration_number as student_number',
                'users.email',
                'student_enrollments.academic_year_id',
                'student_enrollments.class_section_id',
                'student_enrollments.roll_number',
                'student_enrollments.status',
            ])
            ->orderBy('student_enrollments.student_id')
            ->get();

        return $this->toCsv(
            ['student_id', 'name', 'student_number', 'email', 'academic_year_id', 'class_section_id', 'roll_number', 'status'],
            $rows->map(fn ($r) => $r->toArray())
        );
    }

    private function attendanceCsv(ExportJob $job, ?string $startDate, ?string $endDate): string
    {
        $query = AttendanceRecord::query()
            ->where('school_id', $job->school_id)
            ->select(['student_id', 'class_section_id', 'date', 'session', 'status', 'note']);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $rows = $query->orderBy('date')->get();

        return $this->toCsv(
            ['student_id', 'class_section_id', 'date', 'session', 'status', 'note'],
            $rows->map(fn ($r) => $r->toArray())
        );
    }

    private function examMarksCsv(ExportJob $job, ?string $startDate, ?string $endDate): string
    {
        $query = ExamMark::query()
            ->where('exam_marks.school_id', $job->school_id)
            ->join('exams', 'exams.id', '=', 'exam_marks.exam_id')
            ->select([
                'exam_marks.student_id',
                'exams.name as exam_name',
                'exams.exam_date',
                'exam_marks.marks_obtained',
                'exam_marks.max_marks',
            ]);

        if ($startDate) {
            $query->where('exams.exam_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('exams.exam_date', '<=', $endDate);
        }

        $rows = $query->orderBy('exams.exam_date')->get();

        return $this->toCsv(
            ['student_id', 'exam_name', 'exam_date', 'marks_obtained', 'max_marks'],
            $rows->map(fn ($r) => $r->toArray())
        );
    }

    private function toCsv(array $header, $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);

        foreach ($rows as $row) {
            fputcsv($fh, array_map(
                function ($key) use ($row) {
                    $v = $row[$key] ?? null;

                    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
                },
                $header
            ));
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }
}
