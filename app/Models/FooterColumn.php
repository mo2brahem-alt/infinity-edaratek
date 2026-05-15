<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FooterColumn extends Model
{
    use HasFactory;

    // السماح بتعديل كافة الحقول
    protected $guarded = [];

    // العلاقة مع العناصر (الروابط)
    public function items()
    {
        return $this->hasMany(FooterItem::class)->orderBy('order');
    }
}