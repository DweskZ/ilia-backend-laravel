<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->sentence(3)),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'level' => 'beginner',
            'status' => 'published',
            'instructor_id' => AppUser::factory()->state(['role' => 'instructor']),
        ];
    }
}
