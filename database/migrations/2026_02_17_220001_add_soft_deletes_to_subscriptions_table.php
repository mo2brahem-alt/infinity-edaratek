<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('subscriptions', 'deleted_at')) {
                    $table->softDeletes();
                    $table->index('deleted_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'deleted_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
