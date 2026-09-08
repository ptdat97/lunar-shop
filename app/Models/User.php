<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Concerns\IsLunarUser;

class User extends Authenticatable implements LunarUser
{
    use HasApiTokens, HasFactory, IsLunarUser, Notifiable;

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
