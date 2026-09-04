<?php

use App\Http\Controllers\SentimentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SentimentController::class, 'index'])->name('home');
Route::post('/classify', [SentimentController::class, 'classify'])->name('classify');
Route::get('/about', function () {
    return view('about');
});
