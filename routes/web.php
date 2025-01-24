<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('save', 'App\Http\Controllers\PaymentController@save')->name('save');
Route::post('webhook/{id}', 'App\Http\Controllers\PaymentController@webHookWallet')->name('webhook');
