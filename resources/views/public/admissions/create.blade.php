<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Apply — {{ $school->name }} — SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; padding: 2rem 1rem; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); width: 100%; max-width: 480px; margin: 0 auto; }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p.sub { color: #666; font-size: .875rem; margin: 0 0 1.25rem; }
        label { display: block; font-size: .875rem; margin-bottom: .25rem; color: #333; }
        input, textarea { width: 100%; padding: .5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        fieldset { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 1rem; padding: .75rem; }
        legend { font-size: .875rem; color: #333; padding: 0 .25rem; }
        .checkbox-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
        .checkbox-row input { width: auto; margin: 0; }
        button { width: 100%; padding: .6rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .error { color: #b91c1c; font-size: .875rem; margin-bottom: 1rem; }
        .status { color: #15803d; font-size: .875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Apply to {{ $school->name }}</h1>
        <p class="sub">Fill in the student's details below to submit an admission application.</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('public.admissions.store', ['school' => $school->short_code]) }}">
            @csrf
            <input type="hidden" name="school" value="{{ $school->short_code }}">

            <label for="name">Student's full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required>

            <label for="date_of_birth">Date of birth</label>
            <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" required>

            <label for="email">Student email (optional)</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}">

            <label for="mobile_no">Student mobile number (optional)</label>
            <input id="mobile_no" name="mobile_no" type="text" value="{{ old('mobile_no') }}">

            <label for="guardian_name">Parent/guardian name</label>
            <input id="guardian_name" name="guardian_name" type="text" value="{{ old('guardian_name') }}" required>

            <label for="guardian_relationship">Relationship to student</label>
            <input id="guardian_relationship" name="guardian_relationship" type="text" value="{{ old('guardian_relationship') }}" required>

            <label for="guardian_phone">Parent/guardian phone</label>
            <input id="guardian_phone" name="guardian_phone" type="text" value="{{ old('guardian_phone') }}" required>

            <label for="medical_info">Medical information (optional)</label>
            <textarea id="medical_info" name="medical_info" rows="3">{{ old('medical_info') }}</textarea>

            <fieldset>
                <legend>Fee category acknowledgment</legend>
                @foreach ($feeCategories as $category)
                    <div class="checkbox-row">
                        <input
                            id="fee-category-{{ $category->id }}"
                            type="checkbox"
                            name="fee_category_acknowledgments[]"
                            value="{{ $category->id }}"
                            @checked(collect(old('fee_category_acknowledgments', []))->contains($category->id))
                        >
                        <label for="fee-category-{{ $category->id }}" style="margin:0;">{{ $category->label }}</label>
                    </div>
                @endforeach
            </fieldset>

            <button type="submit">Submit application</button>
        </form>
    </div>
</body>
</html>
