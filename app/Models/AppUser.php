<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Platform user. It is Authenticatable and can own Sanctum tokens so the API can
 * identify the docente / instructor behind each request. The `role` column
 * already existed: `teacher` (docente) and `instructor` (administrador).
 */
class AppUser extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    public const ROL_DOCENTE = 'teacher';

    public const ROL_ADMIN = 'instructor';

    protected $table = 'app_user';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'created_at' => 'datetime'];
    }

    /** The password column is `password_hash`, not Laravel's default `password`. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function esAdmin(): bool
    {
        return $this->role === self::ROL_ADMIN;
    }
}
