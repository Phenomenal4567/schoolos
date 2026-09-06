<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Exam;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
 *
 * Neither Exam nor AttendanceRecord carries an academic_term_id (both
 * only go as far as academic_year_id — see those models' own doc
 * comments), so "the exam's own report-card period" is resolved here:
 * the AcademicTerm (if any) whose [start_date, end_date] contains the
 * exam's exam_date narrows the count to that term; with no such term
 * configured, this falls back to the exam's whole academic_year rather
 * than raising, since a report card must still render something.
 * "School opened" is inferred from attendance itself (any date with at
 * least one attendance_records row for the exam's class_section counts
 * as a school day) rather than from calendar_events, which record
 * holidays/breaks, not a positive "school was open" whitelist — see this
 * feature's own research note on why that's the more reliable source.
 */
class AttendanceSummaryService
{
    /**
     * @return array{school_days: int, days_attended: int, period_start: ?string, period_end: ?string}
     */
    public function forStudent(Exam $exam, int $studentId): array
    {
        [$start, $end] = $this->periodFor($exam);

        $base = AttendanceRecord::where('school_id', $exam->school_id)
            ->where('class_section_id', $exam->class_section_id);

        if ($start !== null && $end !== null) {
            $base->whereBetween('date', [$start, $end]);
        } else {
            $base->where('academic_year_id', $exam->academic_year_id);
        }

        $schoolDays = $base->clone()->distinct('date')->count('date');

        $daysAttended = $base->clone()
            ->where('student_id', $studentId)
            ->whereIn('status', ['present', 'late'])
            ->distinct('date')
            ->count('date');

        return [
            'school_days' => $schoolDays,
            'days_attended' => $daysAttended,
            'period_start' => $start,
            'period_end' => $end,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function periodFor(Exam $exam): array
    {
        if ($exam->exam_date === null) {
            return [null, null];
        }

        $term = AcademicTerm::where('academic_year_id', $exam->academic_year_id)
            ->whereDate('start_date', '<=', $exam->exam_date)
            ->whereDate('end_date', '>=', $exam->exam_date)
            ->first();

        if ($term === null) {
            return [null, null];
        }

        return [$term->start_date->toDateString(), $term->end_date->toDateString()];
    }
}
