<?php

use App\Events\SocketEvent;
use App\Events\EventPrivate;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserManageController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/socktest', function () {
    event(new EventPrivate('Hello World', 2));
    return 'Event has been sent!';
});

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/users/manage', [UserManageController::class, 'index'])->name('users.manage');
    Route::patch('/users/manage/{user}', [UserManageController::class, 'update'])->name('users.manage.update');
    Route::post('/users/manage/lock', [UserManageController::class, 'lock'])->name('users.manage.lock');
    Route::post('/users/manage/unlock', [UserManageController::class, 'unlock'])->name('users.manage.unlock');
});

