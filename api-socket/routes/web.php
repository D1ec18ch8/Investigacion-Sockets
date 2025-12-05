<?php

use App\Events\SocketEvent;
use Illuminate\Support\Facades\Route;
use App\Events\EventPrivate;
use Illuminate\Support\Facades\Broadcast;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/socktest', function () {
    event(new EventPrivate('Hello World', 2));
    return 'Event has been sent!';
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

