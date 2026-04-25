<?php

use App\Http\Controllers\QuizController;
use Illuminate\Support\Facades\Route;

Route::get('/',      [QuizController::class, 'index'])->name('index');

Route::get('/q1',    [QuizController::class, 'q1'])->name('q1');
Route::post('/q1',   [QuizController::class, 'parseMessage'])->name('q1.parse');

Route::get('/q2',    [QuizController::class, 'q2'])->name('q2');

Route::get('/q3',    [QuizController::class, 'q3'])->name('q3');
Route::post('/q3',   [QuizController::class, 'calculateTop'])->name('q3.calculate');
