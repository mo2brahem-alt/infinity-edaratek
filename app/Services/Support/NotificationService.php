<?php

namespace App\Services\Support;

use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolSupervisorAssignment;
use App\Models\User;

class NotificationService
{
    public function notifyUser(int $userId, string $type, string $title, ?string $body = null, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $this->normalizeData($data),
        ]);
    }

    public function notifyUsers(array $userIds, string $type, string $title, ?string $body = null, ?array $data = null): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->notifyUser((int) $userId, $type, $title, $body, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $routeParams
     * @param array<string, mixed> $actionRouteParams
     * @return array<string, mixed>
     */
    public function withRoute(
        array $data,
        string $routeName,
        array $routeParams = [],
        ?string $actionRouteName = null,
        array $actionRouteParams = []
    ): array {
        $payload = $data;
        $payload['route_name'] = $routeName;

        if (count($routeParams) > 0) {
            $payload['route_params'] = $routeParams;
        }

        if ($actionRouteName !== null && $actionRouteName !== '') {
            $payload['action_route_name'] = $actionRouteName;
        }

        if (count($actionRouteParams) > 0) {
            $payload['action_route_params'] = $actionRouteParams;
        }

        return $payload;
    }

    public function notifySuperAdmins(string $type, string $title, ?string $body = null, ?array $data = null): void
    {
        $this->notifyUsers($this->superAdminUserIds(), $type, $title, $body, $data);
    }

    /**
     * @return array<int, int>
     */
    public function superAdminUserIds(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('role', 'super_admin')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'super_admin'));
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function responsibleSupervisorUserIdsForSchool(int $schoolId): array
    {
        $school = School::query()
            ->whereKey($schoolId)
            ->first(['id', 'directorate_id', 'supervisor_id']);

        if (!$school) {
            return [];
        }

        $candidateIds = [];

        if (!empty($school->supervisor_id)) {
            $candidateIds[] = (int) $school->supervisor_id;
        }

        $assignmentIds = SchoolSupervisorAssignment::query()
            ->where('is_active', true)
            ->where(function ($query) use ($school): void {
                $query->where('school_id', (int) $school->id)
                    ->orWhere(function ($directorateScope) use ($school): void {
                        $directorateScope
                            ->whereNull('school_id')
                            ->where('directorate_id', (int) $school->directorate_id);
                    });
            })
            ->pluck('supervisor_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $candidateIds = array_values(array_unique(array_merge($candidateIds, $assignmentIds)));

        if (count($candidateIds) === 0) {
            return [];
        }

        return User::query()
            ->whereIn('id', $candidateIds)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('role', 'supervisor')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'supervisor'));
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>|null
     */
    private function normalizeData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $normalized = $data;

        if (!isset($normalized['route_params']) || !is_array($normalized['route_params'])) {
            unset($normalized['route_params']);
        }

        if (!isset($normalized['action_route_params']) || !is_array($normalized['action_route_params'])) {
            unset($normalized['action_route_params']);
        }

        return $normalized;
    }
}
