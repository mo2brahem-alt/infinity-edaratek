<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $exists = DB::table('settings')->where('key', 'home_page_content')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'key' => 'home_page_content',
                'value' => '[home-banner]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down() {}
};
