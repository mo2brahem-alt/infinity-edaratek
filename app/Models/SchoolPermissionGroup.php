<?php

namespace App\Models;

use App\Support\SchoolPermissionCatalog;
use Illuminate\Database\Eloquent\Model;

class SchoolPermissionGroup extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'group_type',
        'permission_names',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permission_names' => 'array',
        ];
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'school_permission_group_user')
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasPermission(string $permissionName): bool
    {
        return in_array($permissionName, (array) ($this->permission_names ?? []), true);
    }

    public function groupTypeLabel(): string
    {
        return SchoolPermissionCatalog::groupTypeLabel((string) $this->group_type);
    }
}
