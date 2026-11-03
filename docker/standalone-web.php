<?php

use Illuminate\Support\Facades\Route;

// This file belongs only to the Laravel application created by the installer.
Route::get('/', fn () => redirect()->route('qopenpanel.home'));
