<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PromptRunController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('agents', AgentController::class);

    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/prompts', [ProjectController::class, 'prompts'])->name('projects.prompts');
    Route::post('projects/{project}/runs', [PromptRunController::class, 'store'])->name('projects.runs.store');

    // Reload the project's docs/documentations folder into the database.
    Route::post('projects/{project}/documentations/sync', [DocumentationController::class, 'sync'])
        ->name('projects.documentations.sync');
    Route::resource('projects.documentations', DocumentationController::class)->scoped();
});

require __DIR__.'/settings.php';
