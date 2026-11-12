<?php

namespace App\Services\LuisFigueroa;

use Illuminate\Support\Str;

/**
 * Deterministic rubric for the automatic review of a prompt ("pedido").
 * Five criteria, 20 points each. The fixed delay simulates the cost of a real
 * review (e.g. calling a language model) so sync vs async timing is visible.
 */
class RevisorPedidos
{
    private const MATERIAS = ['matematica', 'fraccion', 'suma', 'resta', 'lengua', 'lectura', 'escritura', 'ciencias', 'naturales', 'historia', 'geografia', 'ingles', 'fisica', 'quimica', 'biologia', 'sociales', 'arte'];

    private const TAREAS = ['explica', 'crea', 'redacta', 'propone', 'disena', 'genera', 'resume', 'elabora', 'escribe', 'ensena', 'corrige', 'evalua', 'compara', 'describe', 'prepara'];

    private const SALIDAS = ['lista', 'tabla', 'ejemplo', 'paso', 'ejercicio', 'resumen', 'parrafo', 'esquema', 'pregunta', 'rubrica', 'plan de clase', 'actividad'];

    public function __construct(private readonly float $retardoSegundos = 0.0) {}

    /** @return array{puntaje: int, retroalimentacion: string} */
    public function revisar(string $pedido): array
    {
        if ($this->retardoSegundos > 0) {
            usleep((int) ($this->retardoSegundos * 1_000_000));
        }

        $t = Str::lower(Str::ascii($pedido));

        $criterios = [
            'nivel' => [(bool) preg_match('/\b(grado|curso|nivel|primaria|secundaria|bachillerato|universitari\w*|estudiantes de|ninos de)\b/', $t),
                'Indique el nivel o grado de los estudiantes.'],
            'materia' => [$this->contieneAlguna($t, self::MATERIAS),
                'Indique la materia o el tema que se trabaja.'],
            'tarea' => [$this->contieneAlguna($t, self::TAREAS),
                'Use un verbo que exprese una tarea concreta (explica, crea, redacta...).'],
            'salida' => [$this->contieneAlguna($t, self::SALIDAS),
                'Describa la salida esperada (lista, tabla, ejemplos, pasos...).'],
            'longitud' => [mb_strlen(trim($pedido)) >= 80,
                'Amplíe el pedido: con menos de 80 caracteres suele faltar contexto.'],
        ];

        $puntaje = 0;
        $faltantes = [];
        foreach ($criterios as [$cumple, $sugerencia]) {
            $cumple ? $puntaje += 20 : $faltantes[] = $sugerencia;
        }

        $retro = $faltantes === []
            ? 'Pedido completo: indica nivel, materia, tarea, salida esperada y contexto suficiente.'
            : 'Para mejorar: '.implode(' ', $faltantes);

        return ['puntaje' => $puntaje, 'retroalimentacion' => $retro];
    }

    /** @param list<string> $palabras */
    private function contieneAlguna(string $texto, array $palabras): bool
    {
        foreach ($palabras as $p) {
            if (str_contains($texto, $p)) {
                return true;
            }
        }

        return false;
    }
}
