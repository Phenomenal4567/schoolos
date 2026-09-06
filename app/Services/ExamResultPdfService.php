<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamRemark;
use App\Models\User;
use Illuminate\Support\Collection;

class ExamResultPdfService
{
    public function __construct(private readonly ExamResultService $results)
    {
    }

    /**
     * @param  array{school_days: int, days_attended: int, period_start: ?string, period_end: ?string}|null  $attendanceSummary
     */
    public function render(
        Exam $exam,
        User $student,
        Collection $marks,
        ?ExamRemark $remark = null,
        ?array $attendanceSummary = null,
    ): string {
        $lines = [
            'SchoolOS Result',
            "Student: {$student->name}",
            "Exam: {$exam->name}",
            'Subject: ' . ($exam->subject?->name ?? '-'),
            'Date: ' . ($exam->exam_date?->format('Y-m-d') ?? '-'),
            '',
        ];

        foreach ($marks as $mark) {
            $lines[] = sprintf(
                '%s: %s / %s (%s%% weight)',
                $mark->component?->name ?? 'Component',
                $mark->marks_obtained,
                $mark->max_marks,
                $mark->component?->weight_percent ?? '0.00'
            );
        }

        $lines[] = '';
        $lines[] = 'Weighted total: ' . number_format($this->results->weightedTotal($marks), 2) . '%';

        if ($attendanceSummary !== null) {
            $lines[] = '';
            $lines[] = 'Times school opened: ' . $attendanceSummary['school_days'];
            $lines[] = 'Times student attended: ' . $attendanceSummary['days_attended'];
        }

        if ($remark !== null && ($remark->class_teacher_remark !== null || $remark->proprietor_remark !== null)) {
            $lines[] = '';

            if ($remark->class_teacher_remark !== null) {
                $lines[] = 'Class teacher remark: ' . $remark->class_teacher_remark;
            }

            if ($remark->proprietor_remark !== null) {
                $lines[] = 'Proprietor remark: ' . $remark->proprietor_remark;
            }
        }

        return $this->minimalPdf($lines);
    }

    /**
     * Produces a simple single-page PDF without an external dependency.
     *
     * @param  list<string>  $lines
     */
    private function minimalPdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n50 780 Td\n";

        foreach ($lines as $line) {
            $content .= '(' . $this->escape($line) . ") Tj\n0 -18 Td\n";
        }

        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function escape(string $line): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
    }
}
