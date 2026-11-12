<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AppUser;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class LuisFigueroaDemoSeeder extends Seeder
{
    public const INSTRUCTOR_ID = '11111111-1111-4111-8111-111111111111';

    public const DOCENTE_ID = '22222222-2222-4222-8222-222222222222';

    public const CURSO_ID = '33333333-3333-4333-8333-333333333333';

    public const ACTIVIDAD_ID = '44444444-4444-4444-8444-444444444444';

    public const DOCENTE2_ID = '55555555-5555-4555-8555-555555555555';

    public const ENTREGA_DOCENTE_ID = '66666666-6666-4666-8666-666666666666';

    public const ENTREGA_DOCENTE2_ID = '77777777-7777-4777-8777-777777777777';

    public function run(): void
    {
        // Fictitious password from the environment (LUISFIGUEROA_DEMO_PASSWORD); never hardcoded.
        $clave = config('luisfigueroa.demo_password');
        if (! $clave) {
            throw new \RuntimeException('Defina LUISFIGUEROA_DEMO_PASSWORD en .env (ver .env.example).');
        }

        AppUser::updateOrCreate(['id' => self::INSTRUCTOR_ID], [
            'email' => 'instructor.demo@ilia.test',
            'full_name' => 'Instructor Demo',
            'password_hash' => bcrypt($clave),
            'role' => 'instructor',
            'is_active' => true,
        ]);

        AppUser::updateOrCreate(['id' => self::DOCENTE_ID], [
            'email' => 'docente.demo@ilia.test',
            'full_name' => 'Docente Demo',
            'password_hash' => bcrypt($clave),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        AppUser::updateOrCreate(['id' => self::DOCENTE2_ID], [
            'email' => 'docente2.demo@ilia.test',
            'full_name' => 'Docente Dos Demo',
            'password_hash' => bcrypt($clave),
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

        // One entrega per docente, so the 2.4 access rules can be demonstrated.
        foreach ([
            [self::ENTREGA_DOCENTE_ID, self::DOCENTE_ID, 'Actua como tutor y explica las fracciones a estudiantes de quinto grado.'],
            [self::ENTREGA_DOCENTE2_ID, self::DOCENTE2_ID, 'Actua como tutor y propone tres ejercicios de lectura para tercer grado.'],
        ] as [$id, $docente, $pedido]) {
            $entrega = Submission::findOrNew($id);
            $entrega->forceFill([
                'id' => $id,
                'activity_id' => self::ACTIVIDAD_ID,
                'user_id' => $docente,
                'prompt' => $pedido,
                'attempt' => 1,
                'observation' => null,
                'status' => 'recibida',
                'sent_at' => '2026-11-10T19:30:00+00:00',
            ])->save();
        }

        $this->command?->info('instructor_id = '.self::INSTRUCTOR_ID);
        $this->command?->info('docente_id    = '.self::DOCENTE_ID);
        $this->command?->info('docente2_id   = '.self::DOCENTE2_ID);
        $this->command?->info('curso_id      = '.self::CURSO_ID);
        $this->command?->info('actividad_id  = '.self::ACTIVIDAD_ID);
    }
}
