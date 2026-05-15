<?php

namespace App\Services\Auth;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Support\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserApprovalService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function initialStateForRole(?string $roleName): array
    {
        if ($this->roleRequiresApproval($roleName)) {
            return [
                'is_active' => false,
                'approval_status' => User::APPROVAL_PENDING,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'approval_notes' => null,
            ];
        }

        return [
            'is_active' => true,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'approval_notes' => null,
        ];
    }

    public function roleRequiresApproval(?string $roleName): bool
    {
        return in_array((string) $roleName, User::APPROVAL_REQUIRED_ROLES, true);
    }

    public function notifyPendingApproval(User $user, ?string $source = null): void
    {
        if (! $user->requiresSuperAdminApproval()) {
            return;
        }

        $roleLabel = $user->hasSystemRole('school_manager') ? 'مدير مدرسة' : 'مشرف';

        $this->notificationService->notifyUser(
            (int) $user->id,
            'user.approval.pending',
            'تم استلام طلب الانضمام',
            'تم إرسال طلب الانضمام للمسؤول، وسيتم تفعيل الحساب بعد المراجعة.'
        );

        $this->notificationService->notifySuperAdmins(
            type: 'user.approval.pending',
            title: 'طلب انضمام جديد بانتظار المراجعة',
            body: "تم تسجيل حساب {$roleLabel} جديد باسم {$user->name} ويحتاج إلى موافقة السوبر أدمن.",
            data: $this->notificationService->withRoute([
                'user_id' => (int) $user->id,
                'user_role' => $user->primaryRole(),
                'source' => $source,
            ], 'users.index')
        );
    }

    public function approve(User $user, User $actor, ?string $note = null): User
    {
        $this->ensureTargetCanBeReviewed($user);
        $this->ensureCanBeApproved($user);

        $approvedUser = DB::transaction(function () use ($user, $actor, $note): User {
            $user->forceFill([
                'is_active' => true,
                'approval_status' => User::APPROVAL_APPROVED,
                'approved_at' => now(),
                'approved_by' => (int) $actor->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'approval_notes' => $this->normalizeNote($note),
            ])->save();

            $latestSubscription = $user->subscriptions()
                ->orderByDesc('id')
                ->first();

            if ($latestSubscription && $latestSubscription->status !== Subscription::STATUS_ACTIVE) {
                $this->subscriptionService->activate(
                    $latestSubscription,
                    (int) $actor->id,
                    'account_super_admin_approval'
                );
            }

            return $user->refresh();
        });

        $this->notificationService->notifyUser(
            (int) $approvedUser->id,
            'user.approval.approved',
            'تمت الموافقة على الحساب',
            'تمت مراجعة طلبك والموافقة عليه، ويمكنك الآن تسجيل الدخول واستخدام المنصة.'
        );

        return $approvedUser;
    }

    public function reject(User $user, User $actor, ?string $note = null): User
    {
        $this->ensureTargetCanBeReviewed($user);
        $this->ensureCanBeRejected($user);

        $rejectedUser = DB::transaction(function () use ($user, $actor, $note): User {
            $user->forceFill([
                'is_active' => false,
                'approval_status' => User::APPROVAL_REJECTED,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => now(),
                'rejected_by' => (int) $actor->id,
                'approval_notes' => $this->normalizeNote($note),
            ])->save();

            $pendingSubscription = $user->subscriptions()
                ->where('status', Subscription::STATUS_PENDING)
                ->orderByDesc('id')
                ->first();

            if ($pendingSubscription) {
                $this->subscriptionService->cancel(
                    $pendingSubscription,
                    (int) $actor->id,
                    'account_super_admin_rejection'
                );
            }

            return $user->refresh();
        });

        $this->notificationService->notifyUser(
            (int) $rejectedUser->id,
            'user.approval.rejected',
            'تم رفض طلب الانضمام',
            'تم رفض طلب الانضمام حاليًا. يمكنك التواصل مع الإدارة العامة إذا كنت بحاجة إلى مراجعة الطلب.'
        );

        return $rejectedUser;
    }

    private function ensureTargetCanBeReviewed(User $user): void
    {
        if (! $user->requiresSuperAdminApproval()) {
            throw ValidationException::withMessages([
                'user' => 'هذا الحساب لا يخضع لمسار اعتماد السوبر أدمن.',
            ]);
        }
    }

    private function ensureCanBeApproved(User $user): void
    {
        if ($user->hasApprovedAccountAccess()) {
            throw ValidationException::withMessages([
                'user' => 'هذا الحساب معتمد ومفعّل بالفعل.',
            ]);
        }
    }

    private function ensureCanBeRejected(User $user): void
    {
        if (! $user->isPendingApproval()) {
            throw ValidationException::withMessages([
                'user' => 'يمكن رفض الحسابات التي ما تزال قيد المراجعة فقط.',
            ]);
        }
    }

    private function normalizeNote(?string $note): ?string
    {
        $normalized = trim((string) $note);

        return $normalized === '' ? null : $normalized;
    }
}
