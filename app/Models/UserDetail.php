<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_token', 'address', 'city', 'lat', 'lon',
    ];
}
