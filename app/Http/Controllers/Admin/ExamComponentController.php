<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ExamComponentRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamComponentController extends Controller
{
    public function store(Request $request, ExamComponentRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'components.*.weight_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        try {
            $repository->replaceForSchool($actor->school_id, $data['components']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['components' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Exam components saved.');
    }
}
