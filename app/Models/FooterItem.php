<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FooterItem extends Model
{
    use HasFactory;

    // السماح بتعديل كافة الحقول
    protected $guarded = [];

    // العلاقة مع العمود (الرئيسي)
    public function column()
    {
        return $this->belongsTo(FooterColumn::class, 'footer_column_id');
    }
}