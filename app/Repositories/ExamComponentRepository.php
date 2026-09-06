<?php

namespace App\Repositories;

use App\Models\ExamComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamComponentRepository
{
    /**
     * @param  list<array{name:string,weight_percent:float|int|string}>  $components
     * @return Collection<int, ExamComponent>
     */
    public function replaceForSchool(int $schoolId, array $components): Collection
    {
        $total = collect($components)->sum(fn (array $component) => (float) $component['weight_percent']);

        if (round($total, 2) !== 100.00) {
            throw new \InvalidArgumentException('Exam component weights must sum to 100%.');
        }

        return DB::transaction(function () use ($schoolId, $components): Collection {
            $names = collect($components)->map(fn (array $component) => trim($component['name']))->all();

            $usedRemoved = ExamComponent::where('school_id', $schoolId)
                ->whereNotIn('name', $names)
                ->whereHas('marks')
                ->exists();

            if ($usedRemoved) {
                throw new \InvalidArgumentException('Exam components already used by marks cannot be removed.');
            }

            ExamComponent::where('school_id', $schoolId)
                ->whereNotIn('name', $names)
                ->delete();

            return collect($components)->map(fn (array $component) => ExamComponent::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'name' => trim($component['name']),
                ],
                ['weight_percent' => (float) $component['weight_percent']]
            ));
        });
    }
}
