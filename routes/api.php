<?php

use App\Http\Controllers\API\GeneralUserAuthController;
use App\Http\Controllers\API\SendBotController;
use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/registerWithPhone', [SendBotController::class, 'sendCodeToGroup'])->middleware('throttle:10,1');

Route::post('/loginWithPhone', [GeneralUserAuthController::class, 'loginWithPhone']);
