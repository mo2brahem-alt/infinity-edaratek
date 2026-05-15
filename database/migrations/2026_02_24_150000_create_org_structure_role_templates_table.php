<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('org_structure_role_templates')) {
            Schema::create('org_structure_role_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('name');
                $table->unique('code');
                $table->index(['is_active', 'name']);
            });
        }

        if (Schema::hasTable('department_roles') && !Schema::hasColumn('department_roles', 'org_structure_role_template_id')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $table->foreignId('org_structure_role_template_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('org_structure_role_templates')
                    ->nullOnDelete();

                $table->index(
                    ['department_id', 'org_structure_role_template_id', 'is_active'],
                    'department_roles_department_template_active_idx'
                );
            });
        }

        if (!Schema::hasTable('department_roles') || !Schema::hasTable('org_structure_role_templates')) {
            return;
        }

        $templatesByNormalizedName = DB::table('org_structure_role_templates')
            ->select(['id', 'name'])
            ->get()
            ->mapWithKeys(function ($template): array {
                $normalized = Str::lower(trim((string) $template->name));

                if ($normalized === '') {
                    return [];
                }

                return [$normalized => (int) $template->id];
            })
            ->all();

        $departmentRoles = DB::table('department_roles')
            ->select(['id', 'name', 'org_structure_role_template_id'])
            ->orderBy('id')
            ->get();

        foreach ($departmentRoles as $departmentRole) {
            if (!empty($departmentRole->org_structure_role_template_id)) {
                continue;
            }

            $name = trim((string) $departmentRole->name);
            if ($name === '') {
                continue;
            }

            $normalized = Str::lower($name);

            $templateId = $templatesByNormalizedName[$normalized] ?? null;
            if (!$templateId) {
                $templateId = (int) DB::table('org_structure_role_templates')->insertGetId([
                    'name' => $name,
                    'code' => null,
                    'is_active' => true,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $templatesByNormalizedName[$normalized] = $templateId;
            }

            DB::table('department_roles')
                ->where('id', (int) $departmentRole->id)
                ->update(['org_structure_role_template_id' => $templateId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('department_roles') && Schema::hasColumn('department_roles', 'org_structure_role_template_id')) {
            Schema::table('department_roles', function (Blueprint $table): void {
                $table->dropIndex('department_roles_department_template_active_idx');
                $table->dropConstrainedForeignId('org_structure_role_template_id');
            });
        }

        Schema::dropIfExists('org_structure_role_templates');
    }
};

