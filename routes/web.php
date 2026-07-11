<?php

use App\Http\Controllers\EntryController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('/entries', [EntryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('entries.store');

Route::post('/entries/{entry}/vote', [VoteController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('entries.vote');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
