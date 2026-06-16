<?php

namespace App\Models;

use App\Mail\ResetPasswordMail;
use App\Mail\AdminResetPasswordMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'registered_device_token'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function assignRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();

        if (!$roleModel) {
            $roleModel = Role::create(['name' => $role]);
        }

        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }

    public function removeRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();

        if ($roleModel) {
            $this->roles()->detach($roleModel->id);
        }
    }

    public function sendPasswordResetNotification($token)
    {
        if ($this->hasRole('admin')) {
            $url = route('admin.password.reset', [
                'token' => $token,
                'email' => $this->email,
            ]);

            Mail::to($this->email)->send(new AdminResetPasswordMail($url, $this));
            return;
        }

        $url = route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ]);

        $student = $this->student;

        Mail::to($this->email)->send(new ResetPasswordMail($url, $student));
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }
}
