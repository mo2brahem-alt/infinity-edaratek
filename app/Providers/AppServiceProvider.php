<?php

namespace App\Providers;

use App\Models\AssociationRequest;
use App\Models\SchoolSupervisionRequest;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Policies\AssociationRequestPolicy;
use App\Policies\SchoolSupervisionRequestPolicy;
use App\Policies\SubtaskPolicy;
use App\Policies\TicketPolicy;
use App\Support\PublicStorageLinkMaintainer;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        App::setLocale('ar');
        Carbon::setLocale('ar');
        Date::setLocale('ar');

        if (! App::runningUnitTests()) {
            app(PublicStorageLinkMaintainer::class)->ensure();
        }

        Vite::prefetch(concurrency: 3);
        $this->configureApiRateLimiters();

        Gate::define('manage-system-roles', fn ($user): bool => (bool) $user?->hasSystemRole('super_admin'));
        Gate::define('manage-school-users', fn ($user): bool => (bool) $user?->hasSystemRole('school_manager'));
        Gate::define('manage-student-leaves', fn ($user): bool => (bool) $user?->canManageStudentLeaves());
        Gate::define('manage-school-exams', fn ($user): bool => (bool) $user?->canManageSchoolExams());
        Gate::define('manage-leave-types', fn ($user): bool => (bool) $user?->canManageLeaveTypes());
        Gate::define('manage-school-calendar', fn ($user): bool => (bool) $user?->canManageSchoolCalendar());
        Gate::define('manage-school-holidays', fn ($user): bool => (bool) $user?->canManageSchoolHolidays());
        Gate::define('import-school-default-data', fn ($user): bool => (bool) $user?->canImportSchoolDefaultData());

        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Subtask::class, SubtaskPolicy::class);
        Gate::policy(AssociationRequest::class, AssociationRequestPolicy::class);
        Gate::policy(SchoolSupervisionRequest::class, SchoolSupervisionRequestPolicy::class);
    }

    private function configureApiRateLimiters(): void
    {
        RateLimiter::for('api.system', function (Request $request) {
            $perMinute = max(1, (int) config('features.api.system_rate_limit_per_minute', 60));

            return Limit::perMinute($perMinute)->by($this->rateLimitKey($request, 'system'));
        });

        RateLimiter::for('api.school', function (Request $request) {
            $perMinute = max(1, (int) config('features.api.school_rate_limit_per_minute', 120));

            return Limit::perMinute($perMinute)->by($this->rateLimitKey($request, 'school'));
        });
    }

    private function rateLimitKey(Request $request, string $prefix): string
    {
        $userId = (string) ($request->user()?->id ?? 'guest');
        $schoolId = (string) ($request->user()?->school_id ?? 'na');
        $ip = (string) $request->ip();

        return "{$prefix}|{$userId}|{$schoolId}|{$ip}";
    }
}
