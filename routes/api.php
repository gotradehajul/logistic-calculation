<?php

use App\Http\Controllers\TopCalculatorController;
use App\Http\Controllers\WhatsAppParserController;
use Illuminate\Support\Facades\Route;

// Q1 — WhatsApp Message Parser
Route::post('/whatsapp/parse', [WhatsAppParserController::class, 'parse']);

// Q3 — TOP Calculator
Route::post('/top/calculate', [TopCalculatorController::class, 'calculate']);
