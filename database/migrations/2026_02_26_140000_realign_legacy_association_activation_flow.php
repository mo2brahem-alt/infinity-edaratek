<?php

use App\Models\AssociationRequest;
use App\Models\School;
use App\Models\SchoolSupervisionRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('association_requests')
            || !Schema::hasTable('school_supervision_requests')
            || !Schema::hasTable('schools')) {
            return;
        }

        DB::transaction(function (): void {
            $approvedAssociations = DB::table('association_requests')
                ->where('status', AssociationRequest::STATUS_APPROVED)
                ->orderBy('id')
                ->get([
                    'id',
                    'school_id',
                    'manager_user_id',
                    'supervisor_user_id',
                    'created_at',
                    'approved_at',
                ]);

            foreach ($approvedAssociations as $association) {
                $hasConfirmedSupervision = DB::table('school_supervision_requests')
                    ->where('school_id', $association->school_id)
                    ->where('status', SchoolSupervisionRequest::STATUS_ACTIVE_ASSOCIATION)
                    ->exists();

                if (!$hasConfirmedSupervision) {
                    $existingPending = DB::table('school_supervision_requests')
                        ->where('school_id', $association->school_id)
                        ->where('supervisor_id', $association->supervisor_user_id)
                        ->whereIn('status', [
                            SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED,
                            SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                        ])
                        ->orderByDesc('id')
                        ->first([
                            'id',
                            'status',
                            'manager_id',
                        ]);

                    if (!$existingPending) {
                        DB::table('school_supervision_requests')->insert([
                            'school_id' => $association->school_id,
                            'region_id' => DB::table('schools')
                                ->where('id', $association->school_id)
                                ->value('directorate_id'),
                            'supervisor_id' => $association->supervisor_user_id,
                            'manager_id' => $association->manager_user_id,
                            'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                            'requested_at' => $association->created_at ?? now(),
                            'manager_action_at' => $association->approved_at ?? now(),
                            'supervisor_confirmed_at' => null,
                            'notes' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } elseif ((string) $existingPending->status === SchoolSupervisionRequest::STATUS_SUPERVISOR_REQUESTED) {
                        DB::table('school_supervision_requests')
                            ->where('id', $existingPending->id)
                            ->update([
                                'status' => SchoolSupervisionRequest::STATUS_MANAGER_APPROVED,
                                'manager_id' => $association->manager_user_id,
                                'manager_action_at' => $association->approved_at ?? now(),
                                'updated_at' => now(),
                            ]);
                    } elseif ((int) ($existingPending->manager_id ?? 0) !== (int) ($association->manager_user_id ?? 0)) {
                        DB::table('school_supervision_requests')
                            ->where('id', $existingPending->id)
                            ->update([
                                'manager_id' => $association->manager_user_id,
                                'manager_action_at' => $association->approved_at ?? now(),
                                'updated_at' => now(),
                            ]);
                    }
                }

                $school = DB::table('schools')
                    ->where('id', $association->school_id)
                    ->first([
                        'id',
                        'status',
                        'supervision_status',
                        'manager_user_id',
                        'supervisor_id',
                    ]);

                if (!$school) {
                    continue;
                }

                if ((string) $school->status === School::STATUS_ACTIVE
                    && (string) $school->supervision_status === School::SUPERVISION_STATUS_ACTIVE_ASSOCIATION
                    && !$hasConfirmedSupervision) {
                    DB::table('schools')
                        ->where('id', $association->school_id)
                        ->update([
                            'status' => School::STATUS_SUSPENDED,
                            'supervision_status' => School::SUPERVISION_STATUS_WAITING_SUPERVISOR_CONFIRM,
                            'manager_user_id' => $association->manager_user_id ?: $school->manager_user_id,
                            'supervisor_id' => $association->supervisor_user_id ?: $school->supervisor_id,
                            'updated_at' => now(),
                        ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible data correction migration.
    }
};
