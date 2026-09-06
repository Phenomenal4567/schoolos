<?php

namespace App\Repositories;

use App\Events\TimetableSlotChanged;
use App\Models\ClassSection;
use App\Models\School;
use App\Models\Subject;
use App\Models\TimetablePeriod;
use App\Models\TimetablePublication;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
 * Timetable Management module (School Admin build/publish requirement).
 *
 * The write path for timetable_slots, timetable_periods, and
 * timetable_publications. Ownership moved here entirely to the admin
 * surface (or a delegated staff member — see EnsureCanManageTimetable):
 * teachers no longer have a create() path of their own (the earlier
 * teacher-self-create() this class exposed has been removed along with
 * TeacherPortal\TimetableController::store() and its route — see this
 * module's design note for why letting any teacher unilaterally book
 * their own slots was the wrong owner for a school-wide, conflict-
 * checked schedule).
 *
 * create()/update() assign a caller-supplied $teacherId directly
 * (an admin-assigns-any-teacher surface), unlike the removed teacher
 * path where teacher_id was always $actor->id — see this class's
 * previous revision's doc comment for that now-obsolete distinction.
 *
 * Conflict detection: the schema-level UNIQUE(class_section_id,
 * day_of_week, period_number) constraint (2026_08_27_000001's migration)
 * is what actually prevents double-booking a *section* — this class
 * relies on that constraint rather than a pre-check-then-insert for that
 * one dimension, the same TOCTOU-avoiding posture EnrollmentRepository::
 * enroll() takes for its own UNIQUE constraint. Teacher and room
 * double-booking have no equivalent schema constraint (a teacher/room
 * conflict spans class_section boundaries, so it can't be expressed as
 * a single-table unique index the way section-level booking can) and
 * are therefore explicit pre-checks below — best-effort, not
 * TOCTOU-proof, which is an acceptable gap for a single-admin-at-a-time
 * scheduling UI.
 */
class TimetableRepository
{
    /**
     * @throws \InvalidArgumentException if $classSectionId, $subjectId, or
     *         $teacherId doesn't belong to $schoolId, or if the requested
     *         (class_section_id | teacher_id | room) + day + period
     *         combination is already booked.
     */
    public function create(
        int $schoolId,
        int $classSectionId,
        int $subjectId,
        int $teacherId,
        string $dayOfWeek,
        int $periodNumber,
        ?string $room = null,
    ): TimetableSlot {
        [$classSection, $subject] = $this->assertBelongsToSchool($schoolId, $classSectionId, $subjectId, $teacherId);

        $this->assertNoTeacherOrRoomConflict($schoolId, $teacherId, $dayOfWeek, $periodNumber, $room, excludeSlotId: null);

        try {
            $slot = TimetableSlot::create([
                'school_id' => $schoolId,
                'academic_year_id' => $classSection->academic_year_id,
                'class_section_id' => $classSection->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'period_number' => $periodNumber,
                'room' => $room,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            throw new \InvalidArgumentException(
                "TimetableRepository::create(): class_section #{$classSectionId} already has a "
                . "slot booked for {$dayOfWeek} period {$periodNumber}."
            );
        }

        event(new TimetableSlotChanged($slot, $teacherId, 'created'));

        return $slot;
    }

    /**
     * Same validation/conflict shape as create(), with $slot itself
     * excluded from the teacher/room conflict pre-check (a slot doesn't
     * conflict with its own prior booking) and the section unique
     * constraint's QueryException handled identically.
     *
     * @throws \InvalidArgumentException same conditions as create().
     */
    public function update(
        TimetableSlot $slot,
        int $classSectionId,
        int $subjectId,
        int $teacherId,
        string $dayOfWeek,
        int $periodNumber,
        ?string $room = null,
    ): TimetableSlot {
        [$classSection, $subject] = $this->assertBelongsToSchool($slot->school_id, $classSectionId, $subjectId, $teacherId);

        $this->assertNoTeacherOrRoomConflict($slot->school_id, $teacherId, $dayOfWeek, $periodNumber, $room, excludeSlotId: $slot->id);

        try {
            $slot->update([
                'academic_year_id' => $classSection->academic_year_id,
                'class_section_id' => $classSection->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'period_number' => $periodNumber,
                'room' => $room,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            throw new \InvalidArgumentException(
                "TimetableRepository::update(): class_section #{$classSectionId} already has a "
                . "slot booked for {$dayOfWeek} period {$periodNumber}."
            );
        }

        $slot->refresh();

        event(new TimetableSlotChanged($slot, $teacherId, 'updated'));

        return $slot;
    }

    public function delete(TimetableSlot $slot): void
    {
        $teacherId = $slot->teacher_id;

        $slot->delete();

        event(new TimetableSlotChanged(null, $teacherId, 'deleted'));
    }

    /**
     * @param  int  $schoolId
     * @param  int  $classSectionId
     * @param  int  $subjectId
     * @param  int  $teacherId
     * @return array{0: ClassSection, 1: Subject}
     */
    private function assertBelongsToSchool(int $schoolId, int $classSectionId, int $subjectId, int $teacherId): array
    {
        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "TimetableRepository: class_section #{$classSectionId} belongs to school "
                . "#{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "TimetableRepository: subject #{$subjectId} belongs to school "
                . "#{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        $teacher = User::findOrFail($teacherId);

        if ($teacher->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "TimetableRepository: teacher #{$teacherId} belongs to school "
                . "#{$teacher->school_id}, not the requested school #{$schoolId}."
            );
        }

        return [$classSection, $subject];
    }

    private function assertNoTeacherOrRoomConflict(
        int $schoolId,
        int $teacherId,
        string $dayOfWeek,
        int $periodNumber,
        ?string $room,
        ?int $excludeSlotId,
    ): void {
        $teacherConflict = TimetableSlot::where('school_id', $schoolId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_number', $periodNumber)
            ->when($excludeSlotId, fn ($q) => $q->where('id', '!=', $excludeSlotId))
            ->exists();

        if ($teacherConflict) {
            throw new \InvalidArgumentException(
                "TimetableRepository: teacher #{$teacherId} is already booked for {$dayOfWeek} period {$periodNumber}."
            );
        }

        if ($room === null || trim($room) === '') {
            return;
        }

        $roomConflict = TimetableSlot::where('school_id', $schoolId)
            ->where('room', $room)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_number', $periodNumber)
            ->when($excludeSlotId, fn ($q) => $q->where('id', '!=', $excludeSlotId))
            ->exists();

        if ($roomConflict) {
            throw new \InvalidArgumentException(
                "TimetableRepository: room \"{$room}\" is already booked for {$dayOfWeek} period {$periodNumber}."
            );
        }
    }

    /**
     * Writes both halves of the "Configure period start/end times" /
     * "Select the school days" settings screen in one call, matching
     * ExamComponentRepository::replaceForSchool()'s full-replace shape:
     * the caller submits the complete period grid and day list every
     * time, this method deletes rows for period_numbers no longer
     * present and updateOrCreate()s the rest, rather than exposing
     * separate add/remove endpoints for a school-wide setting there's
     * only ever one of.
     *
     * @param  list<array{period_number:int,start_time:string,end_time:string}>  $periods
     * @param  list<string>  $workingDays
     * @return Collection<int, TimetablePeriod>
     */
    public function replaceSettings(int $schoolId, array $periods, array $workingDays): Collection
    {
        return DB::transaction(function () use ($schoolId, $periods, $workingDays): Collection {
            $periodNumbers = collect($periods)->pluck('period_number')->all();

            TimetablePeriod::where('school_id', $schoolId)
                ->whereNotIn('period_number', $periodNumbers)
                ->delete();

            $saved = collect($periods)->map(fn (array $period) => TimetablePeriod::updateOrCreate(
                ['school_id' => $schoolId, 'period_number' => $period['period_number']],
                [
                    'start_time' => Carbon::createFromFormat('H:i', $period['start_time'])->format('H:i:s'),
                    'end_time' => Carbon::createFromFormat('H:i', $period['end_time'])->format('H:i:s'),
                ]
            ));

            School::where('id', $schoolId)->update(['working_days' => $workingDays]);

            return $saved;
        });
    }

    /**
     * Header-row publish, per timetable_publications' own migration doc
     * comment: a school's timetable for a given academic year is
     * published or it isn't, as a whole — this flips (or creates) the
     * one row for (school_id, academic_year_id) rather than touching
     * every timetable_slots row.
     */
    public function publish(int $schoolId, int $academicYearId, User $actor): TimetablePublication
    {
        return TimetablePublication::updateOrCreate(
            ['school_id' => $schoolId, 'academic_year_id' => $academicYearId],
            ['is_published' => true, 'published_at' => now(), 'published_by' => $actor->id]
        );
    }

    public function unpublish(int $schoolId, int $academicYearId): TimetablePublication
    {
        return TimetablePublication::updateOrCreate(
            ['school_id' => $schoolId, 'academic_year_id' => $academicYearId],
            ['is_published' => false]
        );
    }
}
