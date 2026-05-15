<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendancePerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_and_leave_tables_have_performance_indexes_for_reporting_queries(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Index assertions are validated on MySQL only.');
        }

        $attendanceIndexes = collect(DB::select('SHOW INDEX FROM school_student_attendances'))
            ->pluck('Key_name');

        $leaveIndexes = collect(DB::select('SHOW INDEX FROM school_student_leave_requests'))
            ->pluck('Key_name');

        $this->assertTrue(
            $attendanceIndexes->contains('ss_att_student_status_date_idx'),
            'Missing attendance performance index: ss_att_student_status_date_idx'
        );

        $this->assertTrue(
            $leaveIndexes->contains('sslr_student_status_period_idx'),
            'Missing leave performance index: sslr_student_status_period_idx'
        );
    }
}

