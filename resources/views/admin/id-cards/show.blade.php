<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $target->name }} — ID Card</title>
    {{-- Standalone print artifact, deliberately not extending
         layouts.app: a printed/laminated card has no sidebar to render
         and nowhere to navigate from. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            width: 340px;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .card-header {
            background: #4338ca;
            color: #fff;
            padding: 14px 16px;
        }
        .card-header .school { font-size: 13px; font-weight: 600; letter-spacing: .02em; }
        .card-header .label { font-size: 11px; opacity: .85; margin-top: 2px; }
        .card-body { padding: 16px; display: flex; gap: 14px; }
        .photo {
            width: 84px; height: 100px; border-radius: 8px; object-fit: cover;
            background: #e5e7eb; flex-shrink: 0; border: 1px solid #d1d5db;
        }
        .details { min-width: 0; flex: 1; }
        .details .name { font-size: 16px; font-weight: 700; color: #111827; line-height: 1.2; }
        .details .position { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .details dl { margin: 10px 0 0; font-size: 12px; }
        .details dt { color: #9ca3af; }
        .details dd { margin: 0 0 6px; color: #111827; font-weight: 600; }
        .card-footer { padding: 12px 16px 16px; display: flex; align-items: center; justify-content: space-between; }
        #qr-code { width: 84px; height: 84px; }
        .footnote { font-size: 10px; color: #9ca3af; max-width: 180px; }
        @media print {
            body { background: #fff; }
            .card { box-shadow: none; border: 1px solid #000; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <div class="school">{{ $target->school->name ?? 'SchoolOS' }}</div>
            <div class="label">{{ $target->student_id ? 'Student ID Card' : 'Staff ID Card' }}</div>
        </div>
        <div class="card-body">
            <img
                class="photo"
                src="{{ $photoUrl ?? 'https://via.placeholder.com/84x100?text=Photo' }}"
                alt="Photo of {{ $target->name }}"
            >
            <div class="details">
                <div class="name">{{ $target->name }}</div>
                @if ($position)
                    <div class="position">{{ $position }}</div>
                @endif
                <dl>
                    <dt>ID</dt>
                    <dd>{{ $identifier }}</dd>
                </dl>
            </div>
        </div>
        <div class="card-footer">
            <div id="qr-code"></div>
            <p class="footnote">Scan to verify identity or record attendance. Valid only while issued.</p>
        </div>
    </div>

    <script>
        // Client-side QR rendering only — the server never generates a
        // QR image, it only issues the signed token embedded here (see
        // IdCardController::show()). Rendering the code is presentation,
        // not part of QrTokenService's own concern.
        new QRCode(document.getElementById('qr-code'), {
            text: @json($qrToken),
            width: 84,
            height: 84,
        });
    </script>
</body>
</html>
