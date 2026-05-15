<?php

use App\Support\UserFacingMessageTranslator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases used across routes.
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'managed_school' => \App\Http\Middleware\EnsureManagerOwnsSchool::class,
            'active_school_association' => \App\Http\Middleware\EnsureActiveSchoolAssociation::class,
            'student_structure_access' => \App\Http\Middleware\EnsureStudentStructureAccess::class,
            'student_attendance_access' => \App\Http\Middleware\EnsureStudentAttendanceAccess::class,
            'student_leave_access' => \App\Http\Middleware\EnsureStudentLeaveAccess::class,
            'academic_planning_access' => \App\Http\Middleware\EnsureAcademicPlanningAccess::class,
            'school_exams_access' => \App\Http\Middleware\EnsureSchoolExamsAccess::class,
            'school_reports_access' => \App\Http\Middleware\EnsureSchoolReportsAccess::class,
            'school_certificates_access' => \App\Http\Middleware\EnsureSchoolCertificatesAccess::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\AddSecurityHeaders::class,
            \App\Http\Middleware\EnsureApprovedAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $translatedErrors = UserFacingMessageTranslator::translateValidationErrorBag($exception->errors());
            $firstError = collect($translatedErrors)->flatten()->first();

            return response()->json([
                'message' => $firstError ?: UserFacingMessageTranslator::translate($exception->getMessage(), $exception->status),
                'errors' => $translatedErrors,
            ], $exception->status);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => UserFacingMessageTranslator::translate($exception->getMessage(), $exception->getStatusCode()),
            ], $exception->getStatusCode(), $exception->getHeaders());
        });
    })->create();
