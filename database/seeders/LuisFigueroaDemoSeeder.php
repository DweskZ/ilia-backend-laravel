<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Course;
use Illuminate\Database\Seeder;

class LuisFigueroaDemoSeeder extends Seeder
{
    public const INSTRUCTOR_ID = '11111111-1111-4111-8111-111111111111';

    public const DOCENTE_ID = '22222222-2222-4222-8222-222222222222';

    public const CURSO_ID = '33333333-3333-4333-8333-333333333333';

    public const ACTIVIDAD_ID = '44444444-4444-4444-8444-444444444444';

    public function run(): void
    {
        AppUser::updateOrCreate(['id' => self::INSTRUCTOR_ID], [
            'email' => 'instructor.demo@ilia.test',
            'full_name' => 'Instructor Demo',
            'password_hash' => bcrypt('demo-1234'),
            'role' => 'instructor',
            'is_active' => true,
        ]);

        AppUser::updateOrCreate(['id' => self::DOCENTE_ID], [
            'email' => 'docente.demo@ilia.test',
            'full_name' => 'Docente Demo',
            'password_hash' => bcrypt('demo-1234'),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        Course::updateOrCreate(['id' => self::CURSO_ID], [
            'slug' => 'ia-para-docentes-demo',
            'title' => 'IA para docentes (demo)',
            'summary' => 'Curso de demostracion para la practica IS-803.',
            'description' => 'Curso de demostracion para probar el contrato de entregas.',
            'level' => 'beginner',
            'status' => 'published',
            'instructor_id' => self::INSTRUCTOR_ID,
        ]);

        Activity::updateOrCreate(['id' => self::ACTIVIDAD_ID], [
            'course_id' => self::CURSO_ID,
            'title' => 'Taller 1: redactar un pedido para la IA',
            'max_attempts' => 3,
        ]);

        $this->command?->info('instructor_id = '.self::INSTRUCTOR_ID);
        $this->command?->info('docente_id    = '.self::DOCENTE_ID);
        $this->command?->info('curso_id      = '.self::CURSO_ID);
        $this->command?->info('actividad_id  = '.self::ACTIVIDAD_ID);
    }
}
