<?php

use App\Http\Controllers\Api\TagsController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::patch('tasks/{id}/restore', [TaskController::class, 'restore']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::patch('tasks/{task}/uncomplete', [TaskController::class, 'uncomplete'] );
    Route::delete('tasks/{task}/archive', [TaskController::class, 'archive']);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('tags', TagsController::class);
});
