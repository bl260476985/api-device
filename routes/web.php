<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::Group(['middleware' => ['web', 'adminlogger']], function () {
    Route::any('admin/debug/test', ['uses' => 'DebugController@test']);
    Route::any('admin/debug/test2', ['uses' => 'DebugController@test2']);
});

Route::Group(['namespace' => 'Admin', 'prefix' => 'admin/v1', 'middleware' => ['web', 'adminlogger']], function () {
    Route::Group(['namespace' => 'Station', 'prefix' => 'station'], function () {
        Route::match(['post', 'get'], 'station/search', ['middleware' => ['adminlogin'], 'uses' => 'StationController@search']);
        Route::match(['post', 'get'], 'station/add', ['middleware' => ['adminlogin'], 'uses' => 'StationController@add']);
        Route::match(['post', 'get'], 'station/update', ['middleware' => ['adminlogin'], 'uses' => 'StationController@update']);
    });
});

