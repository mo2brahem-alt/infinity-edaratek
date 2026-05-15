<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>التحقق من الشهادة</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at top, #0f766e 0, #0f172a 42%, #020617 100%);
            color: #e5e7eb;
            font-family: "Cairo", "Tajawal", Arial, sans-serif;
            direction: rtl;
        }
        .panel {
            width: min(92vw, 560px);
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 22px;
            background: rgba(15, 23, 42, .88);
            padding: 28px;
            box-shadow: 0 22px 60px rgba(0, 0, 0, .32);
        }
        h1 { margin: 0 0 18px; font-size: 26px; }
        .status { display: inline-flex; border-radius: 999px; padding: 8px 14px; font-weight: 800; }
        .ok { background: rgba(16, 185, 129, .14); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, .35); }
        .bad { background: rgba(239, 68, 68, .14); color: #fca5a5; border: 1px solid rgba(239, 68, 68, .35); }
        dl { display: grid; gap: 12px; margin: 22px 0 0; }
        .row { display: flex; justify-content: space-between; gap: 18px; border-bottom: 1px solid rgba(148, 163, 184, .14); padding-bottom: 10px; }
        dt { color: #94a3b8; }
        dd { margin: 0; text-align: left; font-weight: 800; }
    </style>
</head>
<body>
    <main class="panel">
        <h1>التحقق من الشهادة</h1>

        @if (!$certificate)
            <span class="status bad">الشهادة غير موجودة</span>
            <p>تعذر العثور على شهادة بهذا الرابط. يرجى التأكد من الرابط أو التواصل مع المدرسة.</p>
        @else
            <span class="status {{ $certificate['status'] === 'cancelled' ? 'bad' : 'ok' }}">
                {{ $certificate['status_label'] }}
            </span>
            <dl>
                <div class="row"><dt>رقم الشهادة</dt><dd>{{ $certificate['certificate_number'] }}</dd></div>
                <div class="row"><dt>المستفيد</dt><dd>{{ $certificate['recipient_name'] }}</dd></div>
                <div class="row"><dt>الصفة</dt><dd>{{ $certificate['recipient_label'] }}</dd></div>
                <div class="row"><dt>المدرسة</dt><dd>{{ $certificate['school_name'] }}</dd></div>
                <div class="row"><dt>نوع الشهادة</dt><dd>{{ $certificate['title'] }}</dd></div>
                <div class="row"><dt>تاريخ الإصدار</dt><dd>{{ $certificate['issued_at'] }}</dd></div>
            </dl>
        @endif
    </main>
</body>
</html>
