<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use Illuminate\Support\Collection;

class ExamResultService
{
    /**
     * @param  Collection<int, ExamMark>  $marks
     */
    public function weightedTotal(Collection $marks): float
    {
        return round($marks->sum(function (ExamMark $mark): float {
            if ((float) $mark->max_marks <= 0.0) {
                return 0.0;
            }

            $percentage = ((float) $mark->marks_obtained / (float) $mark->max_marks) * 100;

            return $percentage * ((float) $mark->component->weight_percent / 100);
        }), 2);
    }

    public function publishedMarksForStudent(Exam $exam, int $studentId): Collection
    {
        return $exam->marks()
            ->where('student_id', $studentId)
            ->where('status', ExamMark::STATUS_PUBLISHED)
            ->with(['component', 'student'])
            ->get();
    }
}
