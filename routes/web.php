<?php

use App\Http\Controllers\AdminEntryController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GalleryController::class, 'index'])->name('gallery');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/submit', [EntryController::class, 'create'])->name('entries.create');

Route::post('/entries', [EntryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('entries.store');

Route::post('/entries/{entry}/vote', [VoteController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('entries.vote');

Route::get('/admin/entries/{entry}/hide', [AdminEntryController::class, 'hide'])
    ->middleware('signed')
    ->name('admin.entries.hide');

