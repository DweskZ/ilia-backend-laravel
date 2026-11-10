<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; 

class CursoController extends Controller
{
    //Get /api/cursos

    public function index(): JsonResponse 
    {
        return response()->json([
            'success' => true,
            'message' => 'Lista de cursos',
            'data' => [
                [
                    'id' => 1,
                    'nombre' => 'Ingenieria de Software',
                    'codigo' => 'IS-001',
                    'creditos' => 4
                ]
            ]
         ],200);
    }

    //Get /api.cursor/{1}
    public function show($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Curso encontrado',
            'date' => [
                'id' => $id,
                'nombre' => 'Ingenieria de Software',
                'codigo' => 'IS-001',
                'creditos' => 4
            ]
        ],200);
    }
}
