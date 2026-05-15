<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subjects = DB::table('school_subjects')
            ->select(['id', 'school_id', 'code'])
            ->orderBy('school_id')
            ->orderBy('id')
            ->get()
            ->groupBy('school_id');

        foreach ($subjects as $schoolId => $rows) {
            $used = [];
            $max = 0;

            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row->code ?? '')));
                if ($code === '') {
                    continue;
                }

                $used[$code] = true;
                if (preg_match('/^SUB-(\d+)$/', $code, $matches) === 1) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            $next = $max + 1;

            foreach ($rows as $row) {
                $currentCode = strtoupper(trim((string) ($row->code ?? '')));
                if ($currentCode !== '') {
                    continue;
                }

                do {
                    $candidate = sprintf('SUB-%04d', $next);
                    $next++;
                } while (isset($used[$candidate]));

                DB::table('school_subjects')
                    ->where('id', $row->id)
                    ->where('school_id', $schoolId)
                    ->update([
                        'code' => $candidate,
                        'updated_at' => now(),
                    ]);

                $used[$candidate] = true;
            }
        }
    }

    public function down(): void
    {
        // no-op: codes are now part of active data
    }
};
