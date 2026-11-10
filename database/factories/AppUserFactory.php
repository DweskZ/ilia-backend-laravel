<?php

namespace Database\Factories;

use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppUser> */
class AppUserFactory extends Factory
{
    protected $model = AppUser::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'password_hash' => bcrypt('secret'),
            'role' => 'teacher',
            'is_active' => true,
        ];
    }
}
