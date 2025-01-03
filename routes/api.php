<?php

use App\Helper\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/health', function () {
    $healthCheck = new HealthCheck();
    $isDatabaseHealthy = $healthCheck->checkDatabaseConnection();
    $isLogstashHealthy = $healthCheck->checkLogstashConnection();

    $checks = [$isDatabaseHealthy, $isLogstashHealthy];

    if (in_array(false, $checks)) {
        return response([
            'status' => 'Unhealthy',
            'details' => [
                'database' => $isDatabaseHealthy,
                'logstash' => $isLogstashHealthy,
            ],
        ], 503);
    }

    return ['status' => 'Healthy'];
});
