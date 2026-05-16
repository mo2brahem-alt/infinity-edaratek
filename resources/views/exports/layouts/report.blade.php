<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle ?? 'تقرير رسمي' }}</title>
    <style>
        @page { size: A4 landscape; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            direction: rtl;
            font-family: "DejaVu Sans", "Cairo", "Tajawal", Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            font-size: 12px;
            line-height: 1.7;
            background: #ffffff;
        }
        .report-shell { width: 100%; }
        .report-header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .report-brand, .report-meta { display: table-cell; vertical-align: middle; }
        .report-brand { width: 64%; }
        .report-meta { width: 36%; text-align: left; color: #475569; font-size: 11px; }
        .report-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            vertical-align: middle;
            margin-left: 10px;
        }
        .report-school { display: inline-block; vertical-align: middle; }
        .report-school-name { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
        .report-school-code { margin: 2px 0 0; color: #64748b; }
        .report-title { margin: 0 0 4px; font-size: 22px; font-weight: 900; color: #115e59; }
        .report-subtitle { margin: 0; color: #475569; }
        .report-context {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px;
        }
        .report-context td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
        }
        .report-context strong {
            display: inline-block;
            min-width: 90px;
            color: #334155;
        }
        .report-note {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            color: #0f172a;
        }
        .report-note p { margin: 0; }
        .report-note p + p { margin-top: 6px; }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report-table th,
        table.report-table td {
            border: 1px solid #cbd5e1;
            padding: 9px 8px;
            vertical-align: top;
            text-align: right;
            word-wrap: break-word;
        }
        table.report-table thead th {
            background: #e2e8f0;
            font-weight: 800;
        }
        table.report-table tbody th {
            background: #f8fafc;
            width: 110px;
        }
        .report-footer {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 10.5px;
            display: table;
            width: 100%;
        }
        .report-footer div { display: table-cell; vertical-align: middle; }
        .report-footer .left { text-align: left; }
        @stack('styles')
    </style>
</head>
<body>
    <main class="report-shell">
        <header class="report-header">
            <div class="report-brand">
                @if (! empty($schoolLogoImage))
                    <img src="{{ $schoolLogoImage }}" alt="شعار المدرسة" class="report-logo">
                @endif
                <div class="report-school">
                    <h1 class="report-school-name">{{ $school?->name ?? 'المدرسة' }}</h1>
                    <p class="report-school-code">
                        @if (! empty($school?->school_id))
                            كود المدرسة: {{ $school->school_id }}
                        @else
                            منصة إدارتك
                        @endif
                    </p>
                </div>
            </div>
            <div class="report-meta">
                <h2 class="report-title">{{ $documentTitle ?? 'تقرير رسمي' }}</h2>
                <p class="report-subtitle">{{ $documentSubtitle ?? 'مستند رسمي صادر من منصة إدارتك.' }}</p>
            </div>
        </header>

        @yield('content')

        <footer class="report-footer">
            <div>
                {{ $school?->name ?? 'المدرسة' }}
                @if (! empty($exportedBy?->name))
                    - تم التصدير بواسطة: {{ $exportedBy->name }}
                @endif
            </div>
            <div class="left">
                تم إنشاء هذا المستند بواسطة منصة إدارتك في {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}.
            </div>
        </footer>
    </main>
</body>
</html>
