<?php

use App\Http\Controllers\api\CursoController;
use App\Http\Controllers\api\EstadoController;
use App\Http\Controllers\api\EstudianteController;
use App\Http\Controllers\api\LuisFigueroa\EntregaController;
use App\Http\Controllers\api\LuisFigueroa\IntegracionEntregaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/estado', [EstadoController::class, 'index']);

Route::get('/cursos', [CursoController::class, 'index']);
Route::get('/cursos/{id}', [CursoController::class, 'show']);

Route::get('/estudiantes', [EstudianteController::class, 'index']);
Route::get('/estudiantes/{id}', [EstudianteController::class, 'show']);

// IS-803 · Tema 2.2 · Luis Figueroa: contrato JSON de entregas de taller
Route::post('/entregas', [EntregaController::class, 'store']);
Route::get('/entregas/{id}', [EntregaController::class, 'show']);

// IS-803 · Tema 2.3 · Luis Figueroa: traducción de dos orígenes al contrato común
Route::post('/integracion/entregas/web', [IntegracionEntregaController::class, 'web']);
Route::post('/integracion/entregas/movil', [IntegracionEntregaController::class, 'movil']);
