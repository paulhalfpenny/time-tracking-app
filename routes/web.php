<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\Timesheet\DayView;
use Illuminate\Support\Facades\Route;

// Auth routes (unauthenticated)
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback')->middleware('throttle:google-oauth');
Route::get('/auth/error', fn () => view('auth.error'))->name('auth.error');
Route::get('/login', fn () => view('auth.login'))->name('auth.login');
Route::post('/auth/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('auth.logout')->middleware('auth');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('timesheet'));
    Route::get('/timesheet', DayView::class)->name('timesheet');
});
