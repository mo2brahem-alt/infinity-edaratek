<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Staff/Dashboard', [
            'user' => array_merge(
                $user->only(['id', 'name', 'email', 'school_id']),
                [
                    'can_manage_student_structure' => $user->canManageStudentStructure(),
                    'can_manage_student_attendance' => $user->canManageStudentAttendance(),
                    'can_manage_academic_planning' => $user->canManageAcademicPlanning(),
                    'can_manage_student_leaves' => $user->canManageStudentLeaves(),
                ]
            ),
            'permissions' => [
                'can_manage_student_structure' => $user->canManageStudentStructure(),
                'can_manage_student_attendance' => $user->canManageStudentAttendance(),
                'can_manage_academic_planning' => $user->canManageAcademicPlanning(),
                'can_manage_student_leaves' => $user->canManageStudentLeaves(),
            ],
        ]);
    }
}
