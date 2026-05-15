<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            if (! Schema::hasColumn('schools', 'default_template_key')) {
                $table->string('default_template_key')
                    ->nullable()
                    ->after('default_data_imported_by');
            }

            if (! Schema::hasColumn('schools', 'default_template_name')) {
                $table->string('default_template_name')
                    ->nullable()
                    ->after('default_template_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            if (Schema::hasColumn('schools', 'default_template_name')) {
                $table->dropColumn('default_template_name');
            }

            if (Schema::hasColumn('schools', 'default_template_key')) {
                $table->dropColumn('default_template_key');
            }
        });
    }
};
