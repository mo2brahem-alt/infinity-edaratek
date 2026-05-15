<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>الجدول الدراسي الأسبوعي</title>
    <style>
        body {
            direction: rtl;
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            margin: 24px;
            font-size: 12px;
            line-height: 1.7;
        }

        .header {
            margin-bottom: 20px;
        }

        .title {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .subtitle {
            margin: 0;
            color: #475569;
        }

        .context {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .context td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
        }

        .context strong {
            display: inline-block;
            min-width: 88px;
        }

        .notes {
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #bae6fd;
            background: #f0f9ff;
            color: #0f172a;
        }

        .notes p {
            margin: 0;
        }

        .notes p + p {
            margin-top: 6px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 10px 8px;
            vertical-align: top;
            text-align: right;
        }

        table.grid thead th {
            background: #e2e8f0;
            font-weight: 700;
        }

        table.grid tbody th {
            background: #f8fafc;
            width: 110px;
        }

        .cell-subject {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .cell-teacher,
        .cell-time,
        .cell-notes {
            color: #475569;
            font-size: 11px;
        }

        .cell-time,
        .cell-notes {
            margin-top: 3px;
        }

        .empty {
            color: #94a3b8;
        }

        .footer {
            margin-top: 18px;
            color: #64748b;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">الجدول الدراسي الأسبوعي</h1>
        <p class="subtitle">تم إنشاء هذا التصدير من نفس بيانات الجدول المحفوظة داخل Edaratek.</p>
    </div>

    <table class="context">
        <tr>
            <td><strong>المدرسة:</strong> {{ $school->name }}</td>
            <td><strong>الترم:</strong> {{ $term->name }}</td>
        </tr>
        <tr>
            <td><strong>المرحلة:</strong> {{ $stage->name }}</td>
            <td><strong>الصف:</strong> {{ $grade_name ?: ($classroom->grade_name ?? '-') }}</td>
        </tr>
        <tr>
            <td><strong>الفصل:</strong> {{ $classroom->name }}</td>
            <td><strong>نسخة الجدول:</strong> {{ $timetableVersionName !== '' ? $timetableVersionName : 'النسخة الأساسية' }}</td>
        </tr>
    </table>

    <div class="notes">
        <p>
            <strong>العطلة الأسبوعية:</strong>
            {{ count($weeklyOffLabels) > 0 ? implode('، ', $weeklyOffLabels->all()) : 'لا توجد عطلة أسبوعية مسجلة.' }}
        </p>
        <p>هذا الجدول يمثل النمط الأسبوعي الثابت، وتُحترم الإجازات الرسمية والتقويم المدرسي عند القراءة الزمنية والتطبيق الفعلي للجلسات.</p>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th>اليوم</th>
                @foreach ($periods as $period)
                    <th>الحصة {{ $period }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($weekDays as $day)
                <tr>
                    <th>{{ $day['label'] }}</th>
                    @foreach ($periods as $period)
                        @php($cell = $matrix[$day['value']][$period] ?? null)
                        <td>
                            @if ($cell)
                                <div class="cell-subject">{{ $cell['subject_name'] !== '' ? $cell['subject_name'] : '-' }}</div>
                                <div class="cell-teacher">{{ $cell['teacher_name'] !== '' ? $cell['teacher_name'] : 'غير مسند' }}</div>
                                @if ($cell['time_label'] !== '')
                                    <div class="cell-time">الوقت: {{ $cell['time_label'] }}</div>
                                @endif
                                @if ($cell['notes'] !== '')
                                    <div class="cell-notes">{{ $cell['notes'] }}</div>
                                @endif
                            @else
                                <span class="empty">لا توجد حصة</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        تم إنشاء التصدير في {{ $generatedAt->format('Y-m-d H:i') }}.
    </div>
</body>
</html>
