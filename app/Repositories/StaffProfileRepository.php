<?php

namespace App\Repositories;

use App\Exceptions\Staff\NotAStaffMemberFailure;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * The one write path for staff_profiles and staff_documents, matching
 * this bundle's "one repository owns a resource's writes" shape.
 * Reuses StaffAttendanceRepository::STAFF_ROLE_KEYS as the eligibility
 * gate rather than duplicating that list — a staff_profile and a
 * staff-attendance row answer the same underlying question ("is this
 * person staff at this school"), the same reasoning
 * IdentifierService::generateStaffId() already gives for its own
 * (inverted) version of the same check.
 *
 * updateProfile()/uploadCv()/acknowledgeRules() all upsert-by-user_id
 * (the migration's UNIQUE(user_id)) rather than requiring a caller to
 * already hold a StaffProfile id — a staff member's first profile edit
 * and their hundredth look identical from the caller's side, same
 * shape as ExamMarkRepository::record()'s upsert.
 */
class StaffProfileRepository
{
    /**
     * @param  array{qualifications?: list<string>, responsibilities?: ?string}  $data
     *
     * @throws NotAStaffMemberFailure if $staff's role isn't a staff role.
     */
    public function updateProfile(int $schoolId, User $staff, array $data): StaffProfile
    {
        $this->assertIsStaff($staff);

        return StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            array_merge(['school_id' => $schoolId], $data)
        );
    }

    /**
     * @throws NotAStaffMemberFailure if $staff's role isn't a staff role.
     */
    public function uploadCv(int $schoolId, User $staff, string $path): StaffProfile
    {
        $this->assertIsStaff($staff);

        $existing = StaffProfile::where('user_id', $staff->id)->first();

        if ($existing?->cv_path !== null && $existing->cv_path !== $path) {
            Storage::disk('local')->delete($existing->cv_path);
        }

        return StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            ['school_id' => $schoolId, 'cv_path' => $path]
        );
    }

    /**
     * @throws NotAStaffMemberFailure if $staff's role isn't a staff role.
     */
    public function acknowledgeRules(int $schoolId, User $staff): StaffProfile
    {
        $this->assertIsStaff($staff);

        return StaffProfile::updateOrCreate(
            ['user_id' => $staff->id],
            ['school_id' => $schoolId, 'rules_acknowledged_at' => now()]
        );
    }

    /**
     * @throws NotAStaffMemberFailure if $staff's role isn't a staff role.
     */
    public function addDocument(int $schoolId, User $staff, string $label, string $path, User $uploadedBy): StaffDocument
    {
        $this->assertIsStaff($staff);

        return StaffDocument::create([
            'school_id' => $schoolId,
            'user_id' => $staff->id,
            'label' => $label,
            'file_path' => $path,
            'uploaded_by' => $uploadedBy->id,
        ]);
    }

    /**
     * @throws \InvalidArgumentException if $documentId doesn't belong to
     *         $staff within $schoolId.
     */
    public function deleteDocument(int $schoolId, User $staff, int $documentId): void
    {
        $document = StaffDocument::where('school_id', $schoolId)
            ->where('user_id', $staff->id)
            ->find($documentId);

        if ($document === null) {
            throw new \InvalidArgumentException(
                "StaffProfileRepository::deleteDocument(): document #{$documentId} does not belong to "
                . "user #{$staff->id} within school #{$schoolId}."
            );
        }

        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    /**
     * @throws NotAStaffMemberFailure if $staff's role isn't one of
     *         StaffAttendanceRepository::STAFF_ROLE_KEYS.
     */
    private function assertIsStaff(User $staff): void
    {
        if (! in_array($staff->role->key ?? null, StaffAttendanceRepository::STAFF_ROLE_KEYS, true)) {
            throw new NotAStaffMemberFailure($staff);
        }
    }
}
