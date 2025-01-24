<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transitions extends Model
{
    use HasFactory;

    protected $casts = [
        'amount' => 'decimal:2'
    ];

}
