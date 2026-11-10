<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; 


class EstudianteController extends Controller
{
    public function index(): JsonResponse 
    {
        return response()->json([
        'success' => true,
        'message' => 'Lista de estudiantes',
        'data' => [
            [
                'id' => 1,
                'nombre' => 'Luis Figueroa',
                'email' => 'xxx@gmail.com',
                'edad' => 500
             ]
        ]
        ], 200);
    }

    public function show($id): JsonResponse 
    {
        return response()->json([
            'success' => true,
            'message' => 'Estudiante encontrado',
            'data' => [
                'id' => $id,
                'nombre' => 'Luis Figueroa',
                'email' => 'xxx@gmail.com',
                'edad' => 500
            ]
        ],200);
    }
}
