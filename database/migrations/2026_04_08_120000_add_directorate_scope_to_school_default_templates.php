<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeScopedTable(
            table: 'school_default_stage_templates',
            oldUnique: 'sd_stage_scope_name_unique',
            newUnique: 'sd_stage_dir_scope_name_unique',
            newActiveIndex: 'sd_stage_dir_scope_active_index',
            addUnique: true,
        );

        $this->upgradeScopedTable(
            table: 'school_default_academic_year_templates',
            oldUnique: 'sd_year_scope_name_unique',
            newUnique: 'sd_year_dir_scope_name_unique',
            newActiveIndex: 'sd_year_dir_scope_active_index',
            addUnique: true,
        );

        $this->upgradeScopedTable(
            table: 'school_default_holiday_templates',
            oldUnique: null,
            newUnique: null,
            newActiveIndex: 'sd_holiday_dir_scope_active_index',
            addUnique: false,
        );

        $this->upgradeScopedTable(
            table: 'school_default_leave_type_templates',
            oldUnique: 'sd_leave_scope_name_unique',
            newUnique: 'sd_leave_dir_scope_name_unique',
            newActiveIndex: 'sd_leave_dir_scope_active_index',
            addUnique: true,
        );

        $this->upgradeScopedTable(
            table: 'school_default_subject_templates',
            oldUnique: 'sd_subject_scope_name_unique',
            newUnique: 'sd_subject_dir_scope_name_unique',
            newActiveIndex: 'sd_subject_dir_scope_active_index',
            addUnique: true,
        );
    }

    public function down(): void
    {
        $this->downgradeScopedTable(
            table: 'school_default_subject_templates',
            legacyUnique: 'sd_subject_scope_name_unique',
            currentUnique: 'sd_subject_dir_scope_name_unique',
            currentActiveIndex: 'sd_subject_dir_scope_active_index',
            restoreUnique: true,
        );

        $this->downgradeScopedTable(
            table: 'school_default_leave_type_templates',
            legacyUnique: 'sd_leave_scope_name_unique',
            currentUnique: 'sd_leave_dir_scope_name_unique',
            currentActiveIndex: 'sd_leave_dir_scope_active_index',
            restoreUnique: true,
        );

        $this->downgradeScopedTable(
            table: 'school_default_holiday_templates',
            legacyUnique: null,
            currentUnique: null,
            currentActiveIndex: 'sd_holiday_dir_scope_active_index',
            restoreUnique: false,
        );

        $this->downgradeScopedTable(
            table: 'school_default_academic_year_templates',
            legacyUnique: 'sd_year_scope_name_unique',
            currentUnique: 'sd_year_dir_scope_name_unique',
            currentActiveIndex: 'sd_year_dir_scope_active_index',
            restoreUnique: true,
        );

        $this->downgradeScopedTable(
            table: 'school_default_stage_templates',
            legacyUnique: 'sd_stage_scope_name_unique',
            currentUnique: 'sd_stage_dir_scope_name_unique',
            currentActiveIndex: 'sd_stage_dir_scope_active_index',
            restoreUnique: true,
        );
    }

    private function upgradeScopedTable(
        string $table,
        ?string $oldUnique,
        ?string $newUnique,
        string $newActiveIndex,
        bool $addUnique
    ): void {
        $this->ensureDirectorateColumn($table);
        $this->ensureDirectorateForeignKey($table);

        if ($addUnique && $oldUnique && $this->indexExists($table, $oldUnique)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($oldUnique): void {
                $tableBlueprint->dropUnique($oldUnique);
            });
        }

        if ($addUnique && $newUnique && !$this->indexExists($table, $newUnique)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($newUnique): void {
                $tableBlueprint->unique(
                    ['country_id', 'education_type_id', 'directorate_id', 'name'],
                    $newUnique
                );
            });
        }

        if (!$this->indexExists($table, $newActiveIndex)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($newActiveIndex): void {
                $tableBlueprint->index(
                    ['country_id', 'education_type_id', 'directorate_id', 'is_active'],
                    $newActiveIndex
                );
            });
        }
    }

    private function downgradeScopedTable(
        string $table,
        ?string $legacyUnique,
        ?string $currentUnique,
        string $currentActiveIndex,
        bool $restoreUnique
    ): void {
        if ($currentUnique && $this->indexExists($table, $currentUnique)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($currentUnique): void {
                $tableBlueprint->dropUnique($currentUnique);
            });
        }

        if ($this->indexExists($table, $currentActiveIndex)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($currentActiveIndex): void {
                $tableBlueprint->dropIndex($currentActiveIndex);
            });
        }

        if ($this->foreignKeyExists($table, $this->directorateForeignKeyName($table))) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table): void {
                $tableBlueprint->dropForeign($this->directorateForeignKeyName($table));
            });
        }

        if (Schema::hasColumn($table, 'directorate_id')) {
            Schema::table($table, function (Blueprint $tableBlueprint): void {
                $tableBlueprint->dropColumn('directorate_id');
            });
        }

        if ($restoreUnique && $legacyUnique && !$this->indexExists($table, $legacyUnique)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($legacyUnique): void {
                $tableBlueprint->unique(
                    ['country_id', 'education_type_id', 'name'],
                    $legacyUnique
                );
            });
        }
    }

    private function ensureDirectorateColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'directorate_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint): void {
            $tableBlueprint->foreignId('directorate_id')->nullable()->after('education_type_id');
        });
    }

    private function ensureDirectorateForeignKey(string $table): void
    {
        $foreignKeyName = $this->directorateForeignKeyName($table);

        if ($this->foreignKeyExists($table, $foreignKeyName)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKeyName): void {
            $tableBlueprint->foreign('directorate_id', $foreignKeyName)
                ->references('id')
                ->on('educational_directorates')
                ->nullOnDelete();
        });
    }

    private function directorateForeignKeyName(string $table): string
    {
        return match ($table) {
            'school_default_stage_templates' => 'sd_stage_directorate_fk',
            'school_default_academic_year_templates' => 'sd_year_directorate_fk',
            'school_default_holiday_templates' => 'sd_holiday_directorate_fk',
            'school_default_leave_type_templates' => 'sd_leave_directorate_fk',
            default => 'sd_subject_directorate_fk',
        };
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => (($row->name ?? null) === $index));
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
