<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $coins
 */
class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'username',
        'coins',
    ];

    public function admin(): bool
    {
        return $this->role === 'admin';
    }

    public function moderator(): bool
    {
        return $this->role === 'moderator';
    }
}
