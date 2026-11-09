<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateFileMigration extends Model
{
    protected $guarded = [];
    protected $hidden = ['source_path', 'destination_path', 'source_hash', 'observed_source_metadata'];
    protected function casts(): array { return ['observed_source_metadata' => 'array', 'source_size' => 'integer']; }
}
