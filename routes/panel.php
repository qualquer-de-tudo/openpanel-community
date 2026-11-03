<?php

use Illuminate\Support\Facades\Route;
use QOpenPanel\Community\Http\Controllers\MaintenanceController;
use QOpenPanel\Community\Http\Controllers\PanelController as Panel;
use QOpenPanel\Community\Http\Controllers\TemplateController;
use QOpenPanel\Community\Http\Middleware\HandleInertiaRequests;
use QOpenPanel\Community\Http\Middleware\PanelSession;
use QOpenPanel\Community\Http\Middleware\RequirePanelUser;
use QOpenPanel\Community\Http\Middleware\SetLocale;

Route::prefix(config('qopenpanel.path'))->name('qopenpanel.')->middleware(['web', PanelSession::class, SetLocale::class, HandleInertiaRequests::class])->group(function () {
    Route::get('login', [Panel::class, 'login'])->name('login');
    Route::post('login', [Panel::class, 'authenticate'])->middleware('throttle:6,1');
    Route::post('setup', [Panel::class, 'setup'])->middleware('throttle:3,1');
    Route::middleware(RequirePanelUser::class)->group(function () {
        Route::get('/', [Panel::class, 'index'])->name('home');
        Route::get('maintenance/releases', [MaintenanceController::class, 'releases'])->middleware('throttle:openpanel-maintenance-read');
        Route::post('maintenance/request', [MaintenanceController::class, 'request'])->middleware('throttle:openpanel-maintenance-write');
        Route::post('logout', [Panel::class, 'logout']);
        Route::post('resources', [Panel::class, 'store']);
        Route::delete('resources/{resource}', [Panel::class, 'destroy']);
        Route::post('templates/install', [TemplateController::class, 'store'])->name('templates.install');
        Route::post('templates/{resource}/update', [TemplateController::class, 'update'])->whereNumber('resource')->name('templates.update');
        Route::post('templates/{resource}/rollback', [TemplateController::class, 'rollback'])->whereNumber('resource')->name('templates.rollback');
        Route::get('templates/{resource}/download', [TemplateController::class, 'download'])->whereNumber('resource')->name('templates.download');
    });
});
