<?php

use App\Http\Controllers\PanelController as Panel;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Panel::class, 'login'])->name('login');
Route::post('/login', [Panel::class, 'authenticate'])->middleware('throttle:6,1');
Route::post('/setup', [Panel::class, 'setup'])->middleware('throttle:3,1');
Route::middleware('auth')->group(function () {
    Route::get('/', [Panel::class, 'index']);
    Route::post('/logout', [Panel::class, 'logout']);
    Route::post('/resources', [Panel::class, 'store']);
    Route::delete('/resources/{resource}', [Panel::class, 'destroy']);
});
