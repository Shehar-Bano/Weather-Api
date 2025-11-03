<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_email',
        'username',
        'user_address',
        'product_name',
        'product_color',
        'product_description',
        'product_price',
    ];
}
