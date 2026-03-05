<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SteamAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SteamAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'password',
        'auth_token',
    ];

    protected $hidden = [
        'password',
        'auth_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'auth_token' => 'encrypted',
        ];
    }
}
