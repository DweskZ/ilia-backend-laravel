<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; 


class EstadoController extends Controller
{
    public function index(): JsonResponse 
    {
    return response()->json([
        'success' =>true, 
        'message' =>'Api de prueba en laravel',
        'version' =>'1.0'
    ],200);
}
}
