<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Application role model used for basic user-role assignment
 * (separate from Spatie Permission roles). Links users via the
 * `user_roles` pivot table.
 */
class Role extends Model
{
    use HasFactory;

    /**
     * All users assigned this role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }
}
