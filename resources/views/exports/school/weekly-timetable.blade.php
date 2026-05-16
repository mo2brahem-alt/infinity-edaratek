@extends('exports.layouts.report')

@push('styles')
    .cell-subject {
        font-weight: 800;
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
@endpush

@section('content')
    <table class="report-context">
        <tr>
            <td><strong>الترم:</strong> {{ $term->name }}</td>
            <td><strong>المرحلة:</strong> {{ $stage->name }}</td>
        </tr>
        <tr>
            <td><strong>الصف:</strong> {{ $grade_name ?: ($classroom->grade_name ?? '-') }}</td>
            <td><strong>الفصل:</strong> {{ $classroom->name }}</td>
        </tr>
        <tr>
            <td><strong>نسخة الجدول:</strong> {{ $timetableVersionName !== '' ? $timetableVersionName : 'النسخة الأساسية' }}</td>
            <td><strong>تاريخ التصدير:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <div class="report-note">
        <p>
            <strong>العطلة الأسبوعية:</strong>
            {{ count($weeklyOffLabels) > 0 ? implode('، ', $weeklyOffLabels->all()) : 'لا توجد عطلة أسبوعية مسجلة.' }}
        </p>
        <p>هذا الجدول يمثل النمط الأسبوعي الثابت، وتُحترم الإجازات الرسمية والتقويم المدرسي عند التطبيق الفعلي للحصص.</p>
    </div>

    <table class="report-table">
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
@endsection
