<?php

use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('playground', [PlaygroundController::class, 'index'])->name('playground');
    Route::post('playground', [PlaygroundController::class, 'store'])->name('playground.run');

    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/runs', [PromptRunController::class, 'store'])->name('projects.runs.store');
});

require __DIR__.'/settings.php';
