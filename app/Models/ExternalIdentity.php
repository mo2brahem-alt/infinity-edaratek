<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExternalIdentity extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['firebase_uid', 'provider_uid'];

    protected function casts(): array
    {
        return ['linked_at' => 'immutable_datetime', 'last_verified_at' => 'immutable_datetime'];
    }
}
