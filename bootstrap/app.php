<?php

use App\Support\UserFacingMessageTranslator;
use App\Support\NativeApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp/status',
            'webhooks/whatsapp/dispatch/claim',
            'webhooks/whatsapp/dispatch/result',
        ]);

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
            'native.bearer' => \App\Http\Middleware\AuthenticateNativeBearer::class,
            'native.account' => \App\Http\Middleware\EnsureNativeAccountAccess::class,
            'native.throttle' => \App\Http\Middleware\ThrottleNativeRequest::class,
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
            if (! NativeApiResponse::isNativeRequest($request)) {
                return null;
            }

            return NativeApiResponse::error('VALIDATION_FAILED', $exception->status, $exception->errors());
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! NativeApiResponse::isNativeRequest($request)) {
                return null;
            }

            return NativeApiResponse::error('UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! NativeApiResponse::isNativeRequest($request)) {
                return null;
            }

            $status = $exception->getStatusCode();

            return NativeApiResponse::error($status === 404 ? 'NOT_FOUND' : 'FORBIDDEN', $status);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! NativeApiResponse::isNativeRequest($request)) {
                return null;
            }

            return NativeApiResponse::error('INTERNAL_ERROR', 500);
        });

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
