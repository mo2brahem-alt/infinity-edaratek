<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate->title }} - {{ $certificate->certificate_number }}</title>
    <style>
        @page { size: {{ ($template?->paper_size ?? 'A4') }} {{ ($template?->orientation ?? 'landscape') }}; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e5e7eb;
            color: #0f172a;
            font-family: "Cairo", "Tajawal", "Arial", sans-serif;
            direction: rtl;
        }
        .certificate-page {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            padding: 14mm;
            background: #fffdf7;
        }
        .certificate-card {
            position: relative;
            min-height: 182mm;
            padding: 22mm 20mm 16mm;
            border: 5px double #b78b2f;
            background:
                radial-gradient(circle at 10% 15%, rgba(183, 139, 47, .12), transparent 28%),
                radial-gradient(circle at 90% 85%, rgba(14, 165, 233, .10), transparent 30%),
                linear-gradient(135deg, rgba(255, 251, 235, .94), rgba(255, 255, 255, .98));
            overflow: hidden;
        }
        .frame-formal-simple { border-color: #334155; }
        .frame-classic-gold, .frame-excellence-luxury { border-color: #b78b2f; box-shadow: inset 0 0 0 2px rgba(183, 139, 47, .28); }
        .frame-islamic-geometry, .frame-quran-soft { border-color: #0f766e; box-shadow: inset 0 0 0 2px rgba(15, 118, 110, .18); }
        .frame-kids-soft { border-color: #38bdf8; }
        .frame-modern-minimal { border: 2px solid #334155; }
        .frame-administrative-formal { border-color: #1e3a8a; }
        .certificate-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .school-brand { display: flex; align-items: center; gap: 12px; }
        .school-logo {
            width: 24mm;
            height: 24mm;
            object-fit: contain;
            flex: 0 0 auto;
        }
        .school-name { font-size: 18px; font-weight: 800; color: #334155; }
        .certificate-number { font-size: 13px; color: #64748b; direction: ltr; text-align: left; }
        .certificate-title {
            margin: 22mm 0 6mm;
            text-align: center;
            font-family: "{{ $styles['title']['font_family'] ?? 'Reem Kufi' }}", "Cairo", sans-serif;
            font-size: {{ (int) ($styles['title']['font_size'] ?? 34) }}px;
            color: {{ $styles['title']['color'] ?? '#0f172a' }};
            font-weight: {{ $styles['title']['font_weight'] ?? '800' }};
        }
        .student-name {
            text-align: center;
            font-family: "{{ $styles['student']['font_family'] ?? 'Amiri' }}", "Cairo", serif;
            font-size: {{ (int) ($styles['student']['font_size'] ?? 42) }}px;
            color: {{ $styles['student']['color'] ?? '#0f172a' }};
            font-weight: {{ $styles['student']['font_weight'] ?? '800' }};
            margin: 0 0 8mm;
        }
        .certificate-body {
            max-width: 215mm;
            margin: 0 auto;
            text-align: center;
            line-height: 2.15;
            font-family: "{{ $styles['body']['font_family'] ?? 'Cairo' }}", "Tajawal", sans-serif;
            font-size: {{ (int) ($styles['body']['font_size'] ?? 20) }}px;
            color: {{ $styles['body']['color'] ?? '#1f2937' }};
            font-weight: {{ $styles['body']['font_weight'] ?? '500' }};
        }
        .certificate-footer { display: flex; justify-content: space-between; align-items: end; gap: 20mm; margin-top: 18mm; }
        .issued-date {
            font-family: "{{ $styles['date']['font_family'] ?? 'Cairo' }}", sans-serif;
            font-size: {{ (int) ($styles['date']['font_size'] ?? 16) }}px;
            color: {{ $styles['date']['color'] ?? '#475569' }};
            font-weight: {{ $styles['date']['font_weight'] ?? '600' }};
        }
        .signature-box { min-width: 55mm; text-align: center; color: #334155; }
        .signature-title { font-weight: 800; margin-top: 2mm; }
        .signature-image, .stamp-image { max-height: 22mm; max-width: 50mm; object-fit: contain; }
        .verify { margin-top: 8mm; font-size: 11px; color: #64748b; text-align: center; direction: ltr; }
        .certificate-document-footer {
            position: absolute;
            right: 20mm;
            left: 20mm;
            bottom: 6mm;
            display: flex;
            justify-content: space-between;
            gap: 12mm;
            color: #64748b;
            font-size: 10px;
            border-top: 1px solid rgba(100, 116, 139, .28);
            padding-top: 3mm;
        }
        .actions { position: fixed; top: 16px; left: 16px; display: flex; gap: 8px; }
        .actions button {
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: white;
            padding: 10px 16px;
            font-weight: 800;
            cursor: pointer;
        }
        @media print {
            body { background: white; }
            .certificate-page { width: auto; min-height: auto; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">طباعة / حفظ PDF</button>
    </div>
    <main class="certificate-page">
        <section class="certificate-card frame-{{ $frameKey }}">
            <header class="certificate-header">
                <div class="school-brand">
                    @if ($schoolLogoImage)
                        <img src="{{ $schoolLogoImage }}" alt="شعار المدرسة" class="school-logo">
                    @endif
                    <div>
                        <div class="school-name">{{ $school?->name }}</div>
                    <div class="issued-date">تاريخ الإصدار: {{ $issuedDate }}</div>
                    </div>
                </div>
                <div class="certificate-number">{{ $certificate->certificate_number }}</div>
            </header>

            <h1 class="certificate-title">{{ $title }}</h1>
            <h2 class="student-name">{{ $recipientName }}</h2>
            <div class="certificate-body">{!! $body !!}</div>

            <footer class="certificate-footer">
                <div class="issued-date">
                    <div>رابط التحقق:</div>
                    <div class="verify">{{ $verificationUrl }}</div>
                </div>
                <div class="signature-box">
                    @if ($signatureImage)
                        <img src="{{ $signatureImage }}" alt="التوقيع" class="signature-image">
                    @endif
                    @if ($stampImage)
                        <img src="{{ $stampImage }}" alt="الختم" class="stamp-image">
                    @endif
                    <div class="signature-title">{{ $signature?->name ?? 'إدارة المدرسة' }}</div>
                    <div>{{ $signature?->title ?? 'المسؤول المعتمد' }}</div>
                </div>
            </footer>
            <div class="certificate-document-footer">
                <span>{{ $school?->name }} - {{ $certificate->certificate_number }}</span>
                <span>تم إنشاء هذا المستند بواسطة منصة إدارتك.</span>
            </div>
        </section>
    </main>
</body>
</html>
