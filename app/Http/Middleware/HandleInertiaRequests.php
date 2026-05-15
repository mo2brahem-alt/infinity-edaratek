<?php

namespace App\Http\Middleware;

use App\Support\SharedUiCache;
use App\Support\UserFacingMessageTranslator;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Support\Header;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $shared = parent::share($request);
        $shared['errors'] = Inertia::always(fn () => $this->resolveValidationErrors($request));

        return [
            ...$shared,
            'auth' => [
                'user' => $user
                    ? array_merge($user->toArray(), [
                        'primary_role' => $user->primaryRole(),
                        'can_manage_student_structure' => $user->canManageStudentStructure(),
                        'can_manage_student_attendance' => $user->canManageStudentAttendance(),
                        'can_manage_academic_planning' => $user->canManageAcademicPlanning(),
                        'can_manage_school_exams' => $user->canManageSchoolExams(),
                        'can_manage_school_reports' => $user->canManageSchoolReports(),
                        'can_export_school_reports' => $user->canExportSchoolReports(),
                        'can_manage_student_leaves' => $user->canManageStudentLeaves(),
                        'can_manage_leave_types' => $user->canManageLeaveTypes(),
                        'can_manage_school_calendar' => $user->canManageSchoolCalendar(),
                        'can_manage_school_holidays' => $user->canManageSchoolHolidays(),
                        'can_access_certificates' => $user->canAccessCertificates(),
                        'can_issue_certificates' => $user->canIssueCertificates(),
                        'can_print_certificates' => $user->canPrintCertificates(),
                        'can_cancel_certificates' => $user->canCancelCertificates(),
                        'can_manage_certificate_signatures' => $user->canManageCertificateSignatures(),
                    ])
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'message' => fn () => $request->session()->get('message'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'features' => [
                'student_leaves_enabled' => (bool) config('features.student_leaves.enabled', true),
            ],
            'app_settings' => SharedUiCache::appSettings(),
            'headerMenus' => SharedUiCache::headerMenus(),
            'footerColumns' => SharedUiCache::footerColumns(),
        ];
    }

    public function resolveValidationErrors(Request $request): object
    {
        if (! $request->hasSession() || ! $request->session()->has('errors')) {
            return (object) [];
        }

        /** @var array<string, MessageBag> $bags */
        $bags = $request->session()->get('errors')->getBags();

        return (object) collect($bags)->map(function (MessageBag $bag) {
            return (object) collect($bag->messages())->map(function (array $errors) {
                $translatedErrors = UserFacingMessageTranslator::translateValidationErrors($errors);

                return $this->withAllErrors ? $translatedErrors : ($translatedErrors[0] ?? null);
            })->toArray();
        })->pipe(function ($resolvedBags) use ($request) {
            if ($resolvedBags->has('default') && $request->header(Header::ERROR_BAG)) {
                return [$request->header(Header::ERROR_BAG) => $resolvedBags->get('default')];
            }

            if ($resolvedBags->has('default')) {
                return $resolvedBags->get('default');
            }

            return $resolvedBags->toArray();
        });
    }
}
