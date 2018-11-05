<?php

use Illuminate\Http\Request;

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

Route::Group([], function () {
    Route::any('debug', ['uses' => 'DebugController@test']);
    Route::any('debug2', ['uses' => 'DebugController@test2']);
});

Route::Group(['namespace' => 'Api\V1', 'prefix' => 'v1', 'middleware' => ['logcheck']], function () {

    Route::Group(['namespace' => 'DeviceClient', 'prefix' => 'client'], function () {
        //设备桩端相关
        Route::post('device/login', ['uses' => 'DeviceController@login']);
        Route::post('device/report', ['uses' => 'DeviceController@report',]);
        Route::post('device/setting', ['uses' => 'DeviceController@heartSet',]);
        //跟第三方对接
        Route::post('warning/report', ['middleware' => ['datacheck'], 'uses' => 'DeviceThirdController@report',]);
        //获取token
        Route::post('access/token', ['uses' => 'DeviceAccessController@token',]);
        //获取对接数据
        Route::post('access/device', ['uses' => 'DeviceAccessController@search',]);
        //和电信平台的数据对接
        Route::post('telecom/device', ['uses' => 'DeviceTelecomController@device',]);
        Route::post('telecom/status', ['uses' => 'DeviceTelecomController@status',]);
    });

});
