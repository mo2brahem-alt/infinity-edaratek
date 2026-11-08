<?php

namespace Tests\Support;

use App\Models\EducationalDirectorate;
use App\Models\School;
use App\Models\Subtask;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mobile\NativeTokenService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

trait MobileSchoolFixtures
{
    protected function schools(): array
    {
        foreach (['staff', 'teacher', 'school_manager', 'supervisor', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $region = EducationalDirectorate::create(['name' => 'Synthetic Region', 'governorate' => 'Riyadh']);
        $result = [];
        foreach (['A', 'B'] as $suffix) {
            $manager = User::factory()->create(['role' => 'school_manager']);
            $manager->assignRole('school_manager');
            $supervisor = User::factory()->create(['role' => 'supervisor']);
            $supervisor->assignRole('supervisor');
            $school = School::create(['directorate_id' => $region->id, 'name' => 'School '.$suffix,
                'school_id' => 'SYNTHETIC-'.$suffix, 'status' => School::STATUS_ACTIVE,
                'supervision_status' => School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION,
                'manager_user_id' => $manager->id, 'supervisor_id' => $supervisor->id]);
            $manager->update(['school_id' => $school->id]);
            $staff = User::factory()->create(['role' => 'staff', 'school_id' => $school->id]);
            $staff->assignRole('staff');
            $result[$suffix] = compact('school', 'manager', 'supervisor', 'staff');
        }
        return $result;
    }

    protected function task(array $context, ?User $assignee = null, array $attributes = []): Subtask
    {
        $ticket = Ticket::create(['school_id' => $context['school']->id, 'created_by' => $context['supervisor']->id,
            'assigned_to' => $context['manager']->id, 'title' => 'Parent', 'description' => 'Synthetic parent', 'status' => Ticket::STATUS_OPEN]);
        return Subtask::create(array_merge(['ticket_id' => $ticket->id, 'school_id' => $context['school']->id,
            'created_by' => $context['manager']->id, 'assigned_to' => ($assignee ?? $context['staff'])->id,
            'title' => 'Synthetic task', 'description' => 'Detail', 'status' => Subtask::STATUS_OPEN], $attributes));
    }

    protected function nativeToken(User $user): string
    {
        return app(NativeTokenService::class)->issue($user, (string) Str::uuid(), 'Phase4 fixture')->plainTextToken;
    }
}
