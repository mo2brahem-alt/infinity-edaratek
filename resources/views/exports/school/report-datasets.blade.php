@extends('exports.layouts.report')

@section('content')
    @if (! empty($details))
        <table class="report-context">
            <tbody>
                @foreach ($details as $label => $value)
                    <tr>
                        <td><strong>{{ $label }}</strong></td>
                        <td>{{ $value === null || $value === '' ? '-' : $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach (($datasets ?? []) as $dataset)
        <section style="margin-top: {{ $loop->first ? '0' : '22px' }};">
            <div class="report-note">
                <p><strong>{{ $dataset['title'] ?? 'تقرير' }}</strong></p>
                <p>إجمالي السجلات: {{ (int) ($dataset['total'] ?? count($dataset['rows'] ?? [])) }}</p>
            </div>

            @if (empty($dataset['rows']))
                <div class="report-note">
                    <p>لا توجد بيانات لعرضها.</p>
                </div>
            @else
                <table class="report-table">
                    <thead>
                        <tr>
                            @foreach (($dataset['columns'] ?? []) as $column)
                                <th>{{ $column['label'] ?? $column['key'] ?? '-' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($dataset['rows'] ?? []) as $row)
                            <tr>
                                @foreach (($dataset['columns'] ?? []) as $column)
                                    @php($key = (string) ($column['key'] ?? ''))
                                    <td>
                                        @php($value = $row[$key] ?? '')
                                        @if (is_array($value))
                                            {{ implode('، ', array_map('strval', $value)) }}
                                        @elseif (is_scalar($value) || $value === null)
                                            {{ $value === null || $value === '' ? '-' : $value }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
@endsection
