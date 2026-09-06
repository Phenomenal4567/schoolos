<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
 *
 * See the 2026_09_03_010000 migration's doc comment for why this is a
 * peer table to exam_marks rather than a column on it. Written by
 * ExamRemarkRepository — its own doc comment covers the write shape.
 */
class ExamRemark extends Model
{
    protected $fillable = [
        'school_id',
        'exam_id',
        'student_id',
        'class_teacher_remark',
        'class_teacher_remark_by',
        'proprietor_remark',
        'proprietor_remark_by',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function classTeacherRemarkBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_remark_by');
    }

    public function proprietorRemarkBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietor_remark_by');
    }
}
