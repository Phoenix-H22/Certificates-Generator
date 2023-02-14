<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class subscriped_users extends Model
{
    use HasFactory;
    protected $table = 'subscriped_users';
    protected $fillable = [
        'name',
        'email',
    ];
}
