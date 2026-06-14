<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Lunar\Base\Traits\LunarUser;
use Lunar\Base\LunarUser as LunarUserInterface;

class User extends Authenticatable implements LunarUserInterface
{
    use HasFactory, Notifiable, LunarUser;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}