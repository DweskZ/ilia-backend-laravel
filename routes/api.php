<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\EstadoController;
use App\Http\Controllers\api\CursoController;
use App\Http\Controllers\api\EstudianteController;
use App\Http\Controllers\api\LuisFigueroa\EntregaController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/estado',[EstadoController::class, 'index']);

Route::get('/cursos', [CursoController::class, 'index']);
Route::get('/cursos/{id}', [CursoController::class, 'show']);


Route::get('/estudiantes', [EstudianteController::class, 'index']);
Route::get('/estudiantes/{id}', [EstudianteController::class, 'show']);

// IS-803 · Tema 2.2 · Luis Figueroa: contrato JSON de entregas de taller
Route::post('/entregas', [EntregaController::class, 'store']);
Route::get('/entregas/{id}', [EntregaController::class, 'show']);
