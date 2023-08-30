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
    route::apiResource('tasks', TaskController::class);
    route::apiResource('tags', TagsController::class);
    route::patch('complete_task/{id}', 'App\Http\Controllers\Api\TaskController@complete');
    route::patch('uncomplete_task/{id}', 'App\Http\Controllers\Api\TaskController@uncomplete');
});
