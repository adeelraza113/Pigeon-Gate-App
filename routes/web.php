<?php

use Illuminate\Support\Facades\Route;
use App\Events\TestBroadcast;
// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/test-broadcast', function () {
    broadcast(new TestBroadcast('Hello, this is a test broadcast!'));

    return 'Event has been broadcasted!';
});
