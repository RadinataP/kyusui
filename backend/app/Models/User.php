<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role_id', 'status'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Status akun default (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md`
     * section 6). Nilai ini juga menjadi DEFAULT di level database; menyatakannya
     * di model memastikan instance Eloquent yang baru dibuat sudah membawa
     * nilai tersebut tanpa harus `refresh()`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'ACTIVE',
    ];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function owner()
    {
        return $this->hasOne(Owner::class);
    }

    public function courier()
    {
        return $this->hasOne(Courier::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

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
}
