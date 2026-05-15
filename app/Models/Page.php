<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
    protected $guarded = [];
    
    // دالة مساعدة لجلب رابط الصفحة في الموقع
    public function getUrlAttribute()
    {
        return url('/p/' . $this->slug);
    }
}