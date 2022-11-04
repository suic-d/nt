<?php

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('level_report/update', 'App\Http\Controllers\Api\LevelReportController@update');

Route::get('raid/raid_logs', 'App\Http\Controllers\Api\RaidController@raidLogs');
Route::get('raid/adv_logs', 'App\Http\Controllers\Api\RaidController@advertLogs');
Route::get('raid/raids', 'App\Http\Controllers\Api\RaidController@getRaids');
Route::get('raid/gears', 'App\Http\Controllers\Api\RaidController@getGears');
Route::get('raid/raid_kills', 'App\Http\Controllers\Api\RaidController@getRaidKills');
Route::get('raid/gear_kills', 'App\Http\Controllers\Api\RaidController@getGearKills');
