<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\LoginController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/create/register', [RegisterController::class, 'register'])->name('create.register');