<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)->nullable()->after('price');
            }

            if (! Schema::hasColumn('plans', 'yearly_price')) {
                $table->decimal('yearly_price', 10, 2)->nullable()->after('monthly_price');
            }

            if (! Schema::hasColumn('plans', 'included_users_count')) {
                $table->unsignedInteger('included_users_count')->default(0)->after('yearly_price');
            }

            if (! Schema::hasColumn('plans', 'extra_user_monthly_price')) {
                $table->decimal('extra_user_monthly_price', 10, 2)->default(0)->after('included_users_count');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'school_id')) {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('plan_id')
                    ->constrained('schools')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('subscriptions', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable()->after('status')->index();
            }

            if (! Schema::hasColumn('subscriptions', 'base_price')) {
                $table->decimal('base_price', 10, 2)->nullable()->after('billing_cycle');
            }

            if (! Schema::hasColumn('subscriptions', 'included_users_count')) {
                $table->unsignedInteger('included_users_count')->default(0)->after('base_price');
            }

            if (! Schema::hasColumn('subscriptions', 'extra_user_monthly_price')) {
                $table->decimal('extra_user_monthly_price', 10, 2)->default(0)->after('included_users_count');
            }

            $table->index(['school_id', 'status'], 'subscriptions_school_status_index');
        });

        if (! Schema::hasTable('subscription_user_addons')) {
            Schema::create('subscription_user_addons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->unsignedInteger('added_seats_count');
                $table->decimal('extra_user_monthly_price', 10, 2);
                $table->decimal('daily_price', 10, 2);
                $table->unsignedInteger('remaining_days');
                $table->decimal('amount', 10, 2);
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('status')->default('ACTIVE');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'status'], 'subscription_user_addons_school_status_index');
                $table->index(['subscription_id', 'status'], 'subscription_user_addons_subscription_status_index');
                $table->index('ends_at');
            });
        }

        $this->backfillPlanPricing();
        $this->backfillSubscriptionSnapshots();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_user_addons');

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'school_id') && Schema::hasColumn('subscriptions', 'status')) {
                $table->dropIndex('subscriptions_school_status_index');
            }

            if (Schema::hasColumn('subscriptions', 'extra_user_monthly_price')) {
                $table->dropColumn('extra_user_monthly_price');
            }

            if (Schema::hasColumn('subscriptions', 'included_users_count')) {
                $table->dropColumn('included_users_count');
            }

            if (Schema::hasColumn('subscriptions', 'base_price')) {
                $table->dropColumn('base_price');
            }

            if (Schema::hasColumn('subscriptions', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }

            if (Schema::hasColumn('subscriptions', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
        });

        Schema::table('plans', function (Blueprint $table): void {
            foreach (['extra_user_monthly_price', 'included_users_count', 'yearly_price', 'monthly_price'] as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillPlanPricing(): void
    {
        DB::table('plans')
            ->orderBy('id')
            ->get(['id', 'role_type', 'price', 'billing_cycle'])
            ->each(function (object $plan): void {
                $price = (float) ($plan->price ?? 0);
                $cycle = strtoupper((string) ($plan->billing_cycle ?? 'MONTHLY'));
                $isYearly = $cycle === 'YEARLY';
                $isManager = in_array(strtoupper((string) $plan->role_type), ['SCHOOL_MANAGER', 'MANAGER'], true);

                DB::table('plans')
                    ->where('id', $plan->id)
                    ->update([
                        'monthly_price' => $isYearly ? 0 : $price,
                        'yearly_price' => $isYearly ? $price : $price * 12,
                        'included_users_count' => $isManager ? 10 : 0,
                        'extra_user_monthly_price' => 0,
                    ]);
            });
    }

    private function backfillSubscriptionSnapshots(): void
    {
        DB::table('subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->leftJoin('users', 'subscriptions.user_id', '=', 'users.id')
            ->orderBy('subscriptions.id')
            ->get([
                'subscriptions.id',
                'plans.price',
                'plans.billing_cycle',
                'plans.included_users_count',
                'plans.extra_user_monthly_price',
                'users.school_id',
            ])
            ->each(function (object $subscription): void {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'school_id' => $subscription->school_id ?: null,
                        'billing_cycle' => strtoupper((string) ($subscription->billing_cycle ?? 'MONTHLY')),
                        'base_price' => (float) ($subscription->price ?? 0),
                        'included_users_count' => (int) ($subscription->included_users_count ?? 0),
                        'extra_user_monthly_price' => (float) ($subscription->extra_user_monthly_price ?? 0),
                    ]);
            });
    }
};
