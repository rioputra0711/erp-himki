<?php

namespace App\Models;

use App\Enums\PeranSistem;
use App\Support\ServerClock;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->name === null && $user->username !== null) {
                $user->name = $user->username;
            }
        });
    }

    public function personel(): HasOne
    {
        return $this->hasOne(Personel::class);
    }

    public function peranSistem(): ?PeranSistem
    {
        $nama = $this->getRoleNames()->first();

        return $nama === null ? null : PeranSistem::tryFrom($nama);
    }

    public function catatLogin(): void
    {
        $this->forceFill(['last_login_at' => ServerClock::now()])->saveQuietly();
    }
}
