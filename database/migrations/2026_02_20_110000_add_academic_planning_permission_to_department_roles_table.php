<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('department_roles')) {
            return;
        }

        if (!Schema::hasColumn('department_roles', 'can_manage_academic_planning')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $table->boolean('can_manage_academic_planning')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('department_roles') || !Schema::hasColumn('department_roles', 'can_manage_academic_planning')) {
            return;
        }

        Schema::table('department_roles', function (Blueprint $table): void {
            $table->dropColumn('can_manage_academic_planning');
        });
    }
};
